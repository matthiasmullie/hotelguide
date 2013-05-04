<?php

// this file is really, really, really hacky ;)

require_once '../config.php';
require_once '../utils/clusterer.php';
require_once '../utils/cache/cache.php';

ini_set( 'memory_limit', '1G' );

// init db & cache objects
$db = new PDO( "mysql:host=$host;dbname=$db", $user, $pass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );
$cache = Cache::load( $cache );

$clustered = cluster(
	array(
		'neLat' => isset( $_GET['bounds']['neLat'] ) ? (float) $_GET['bounds']['neLat'] : 0,
		'neLng' => isset( $_GET['bounds']['neLng'] ) ? (float) $_GET['bounds']['neLng'] : 0,
		'swLat' => isset( $_GET['bounds']['swLat'] ) ? (float) $_GET['bounds']['swLat'] : 0,
		'swLng' => isset( $_GET['bounds']['swLng'] ) ? (float) $_GET['bounds']['swLng'] : 0
	),
	isset( $_GET['prices']['min'] ) ? (int) $_GET['prices']['min'] : 50,
	isset( $_GET['prices']['max'] ) ? (int) $_GET['prices']['max'] : 300,
	isset( $_GET['minPts'] ) ? (int) $_GET['minPts'] : 1,
	isset( $_GET['nbrClusters'] ) ? (int) $_GET['nbrClusters'] : 50,
	isset( $_GET['crossBounds']['lat'] ) ? (bool) $_GET['crossBounds']['lat'] : false,
	isset( $_GET['crossBounds']['lng'] ) ? (bool) $_GET['crossBounds']['lng'] : false
);

$language = isset( $_GET['language'] ) ? $_GET['language'] : 'en';
foreach ( $clustered['locations'] as &$data ) {
	// format currency
	$formatter = new NumberFormatter( $language, NumberFormatter::CURRENCY );
	$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 0 );
	$data['text'] = $formatter->formatCurrency( $data['price'], $data['price_currency'] );

	/*
	 * Due to some bug in ICU, rounding in currency does not always happen.
	 * Just cut remaining decimals (don't care too much about rounding up
	 * accurately in this bugged case)
	 */
	$separator = $formatter->getSymbol( NumberFormatter::DECIMAL_SEPARATOR_SYMBOL );
	$data['text'] = preg_replace( '/'. preg_quote( $separator ) .'[0-9]+/', '', $data['text'] );

	unset( $data['price_currency'] );
	/**
	 * @deprecated To be removed once iOS app has been updated
	 */
//	unset( $data['price'] );
}

header( 'Content-type: application/json' );
echo json_encode( $clustered );

/**
 * Build clusters/markers according to given params
 *
 * @param array $bounds array( 'neLat' => Y2, 'neLng' => X2, 'swLat' => Y1, 'swLng' => X1 )
 * @param int $minPrice
 * @param int $maxPrice
 * @param int $minPts
 * @param int $nbrClusters
 * @param bool[optional] $crossBoundsLat
 * @param bool[optional] $crossBoundsLng
 * @return array
 */
function cluster( $bounds, $minPrice, $maxPrice, $minPts, $nbrClusters, $crossBoundsLat = false, $crossBoundsLng = false ) {
	global $cache;

	/*
	 * Only cache/round for large map views; specific zooms don't matter;
	 * data will be processed much faster since the resultset is small
	 */
	$cacheCluster = $bounds['neLat'] - $bounds['swLat'] > 20 || $bounds['neLng'] - $bounds['swLng'] > 20;

	$clustered = false;
	if ( $cacheCluster ) {
		$clusterKey = getCacheKey( 'cluster', $minPrice, $maxPrice, $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'], $minPts, $nbrClusters );
		$clustered = $cache->get( $clusterKey );
	}

	// clustered data not in cache; fetch from db
	if ( $clustered === false ) {
		$markers = getMarkers( $bounds, $minPrice, $maxPrice, $crossBoundsLat, $crossBoundsLng );

		// build clusterer
		$clusterer = new Clusterer( $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'], $crossBoundsLat, $crossBoundsLng );
		$clusterer->setMinClusterLocations( $minPts );
		$clusterer->setNumberOfClusters( $nbrClusters );

		foreach ( $markers as $marker ) {
			$clusterer->addLocation( $marker['lat'], $marker['lng'], array( 'id' => $marker['id'], 'price' => $marker['price'], 'price_currency' => $marker['price_currency'] ) );
		}

		$clustered = array(
			'locations' => $clusterer->getLocations(),
			'clusters' => $clusterer->getClusters()
		);
	}

	// save to/extend cache
	if ( $cacheCluster ) {
		$cache->set( $clusterKey, $clustered, 0 );
	}

	return $clustered;
}

/**
 * Find all markers within given bounds and price range
 *
 * @param array $bounds array( 'neLat' => Y2, 'neLng' => X2, 'swLat' => Y1, 'swLng' => X1 )
 * @param int $minPrice
 * @param int $maxPrice
 * @param bool[optional] $crossBoundsLat
 * @param bool[optional] $crossBoundsLng
 * @return array
 */
function getMarkers( $bounds, $minPrice, $maxPrice, $crossBoundsLat = false, $crossBoundsLng = false ) {
	global $db;

	$where = array( 'l.price >= :min AND l.price <= :max' );

	/*
	 * Bounds will usually be part of the map where the left side of the viewport
	 * is more western and right side is more eastern. It could happen though that
	 * the right side displays a next part of the map though, starting from the west.
	 * In that case, part of the center will not be visible, only both sides, and
	 * neLat will actually be lower than swLat.
	 */
	$where[] = $crossBoundsLat ? 'l.lat > :swlat OR l.lat < :nelat' : 'l.lat > :swlat AND l.lat < :nelat';
	$where[] = $crossBoundsLng ? 'l.lng > :swlng OR l.lng < :nelng' : 'l.lng > :swlng AND l.lng < :nelng';

	$prepareMarkers = $db->prepare('
		SELECT l.id, l.lat, l.lng, l.price, l.price_currency
		FROM locations AS l
		WHERE ('.implode( ') AND (', $where ).')
	');

	$prepareMarkers->execute(
		array(
			':min' => $minPrice,
			':max' => $maxPrice,
			':nelat' => $bounds['neLat'],
			':nelng' => $bounds['neLng'],
			':swlat' => $bounds['swLat'],
			':swlng' => $bounds['swLng'],
		)
	);
	return $prepareMarkers->fetchAll();
}

function getCacheKey( $arguments ) {
	global $cache;
	$key = call_user_func_array( array( $cache, 'getKey' ), func_get_args() );

	// save cache key to another cache, so we know about all existing keys (so we can purge them)
	$keysKey = $cache->getKey( 'keys' );
	$keys = $cache->get( $keysKey );
	if ( $keys === false ) {
		$keys = array();
	}
	$keys[] = $key;
	$cache->set( $keysKey, array_unique( $keys ) );

	return $key;
}
