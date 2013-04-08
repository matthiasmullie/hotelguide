<?php

// this file is really, really, really hacky ;)

require_once '../config.php';
require_once '../utils/clusterer.php';
require_once '../utils/cache/cache.php';

ini_set('memory_limit', '1G');

// init db & cache objects
$db = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
$cache = Cache::load( $cache );

$clustered = cluster(
	array(
		'neLat' => isset( $_GET['neLat'] ) ? $_GET['neLat'] : 0,
		'neLng' => isset( $_GET['neLng'] ) ? $_GET['neLng'] : 0,
		'swLat' => isset( $_GET['swLat'] ) ? $_GET['swLat'] : 0,
		'swLng' => isset( $_GET['swLng'] ) ? $_GET['swLng'] : 0
	),
	isset( $_GET['min'] ) ? (int) $_GET['min'] : 50,
	isset( $_GET['max'] ) ? (int) $_GET['max'] : 300,
	isset( $_GET['minPts'] ) ? $_GET['minPts'] : 1,
	isset( $_GET['nbrClusters'] ) ? $_GET['nbrClusters'] : 50
);
echo json_encode( $clustered );

/**
 * Build clusters/markers according to given params
 *
 * @param array $bounds array( 'neLat' => Y2, 'neLng' => X2, 'swLat' => Y1, 'swLng' => X1 )
 * @param int $minPrice
 * @param int $maxPrice
 * @param int $minPts
 * @param int $nbrClusters
 * @return array
 */
function cluster( $bounds, $minPrice, $maxPrice, $minPts, $nbrClusters ) {
	global $cache;

	/*
	 * Only cache/round for large map views; specific zooms don't matter;
	 * data will be processed much faster since the resultset is small
	 */
	$cacheCluster = $bounds['neLat'] - $bounds['swLat'] > 25 || $bounds['neLng'] - $bounds['swLng'] > 25;

	$bounds = roundBounds( $bounds );

	$clustered = false;
	if ( $cacheCluster ) {
		$clusterKey = getCacheKey( $minPrice, $maxPrice, $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'], $minPts, $nbrClusters );
		$clustered = $cache->get( $clusterKey );
	}

	// clustered data not in cache; fetch from db
	if ( $clustered === false ) {
		$markers = getMarkers( $bounds, $minPrice, $maxPrice );

		// build clusterer
		$clusterer = new Clusterer( $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'] );
		$clusterer->setMinClusterLocations( $minPts );
		$clusterer->setNumberOfClusters( $nbrClusters );

		foreach ( $markers as $marker ) {
			$clusterer->addLocation( $marker['lat'], $marker['lng'], array( 'id' => $marker['id'], 'price' => $marker['price'] ) );
		}

		$clustered = array(
			'locations' => $clusterer->getLocations(),
			'clusters' => $clusterer->getClusters(),
			'bounds' => $bounds
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
 * @return array
 */
function getMarkers( $bounds, $minPrice, $maxPrice ) {
	global $db;

	$where = array( 'l.price >= :min AND l.price <= :max' );

	/*
	 * Bounds will usually be part of the map where the left side of the viewport
	 * is more western and right side is more eastern. It could happen though that
	 * the right side displays a next part of the map though, starting from the west.
	 * In that case, part of the center will not be visible, only both sides, and
	 * neLat will actually be lower than swLat.
	 */
	$where[] = $bounds['neLat'] >= $bounds['swLat'] ? 'l.lat > :swlat AND l.lat < :nelat' : 'l.lat > :swlat OR l.lat < :nelat';
	$where[] = $bounds['neLng'] >= $bounds['swLng'] ? 'l.lng > :swlng AND l.lng < :nelng' : 'l.lng > :swlng OR l.lng < :nelng';

	$prepareMarkers = $db->prepare('
		SELECT l.id, l.lat, l.lng, l.price
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

/**
 * Extend bounds a little bit to a rounder number, that way similar
 * requests can use the same cache. Round to a multiple of X only when
 * caching (that way, there are less different caches and odds that
 * that region is cached already are larger).
 *
 * Rounding should be different depending on how much of the map is displayed.
 * If most of the map is showing, rounding can be more rough. This is important
 * because at high zoom levels (= zoomed in), we don't want the clusters to be
 * calculated on really rough bounds: if e.g. we only see lat 54.2 to 54.7 in
 * our viewport, we don't want the clusters to be calculated on lat 50 to 60.
 *
 * @param $bounds
 * @param int $multiple
 * @return mixed
 */
function roundBounds( $bounds ) {
	$totalLat = $bounds['neLat'] > $bounds['swLat'] ? $bounds['neLat'] - $bounds['swLat'] : 180 - ( $bounds['swLat'] - $bounds['neLat'] );
	$totalLng = $bounds['neLng'] > $bounds['swLng'] ? $bounds['neLng'] - $bounds['swLng'] : 360 - ( $bounds['swLng'] - $bounds['neLng'] );

	$exponentLat = preg_replace( '/([0-9\.]+e)/', '', sprintf( '%e', $totalLat) );
	$exponentLng = preg_replace( '/([0-9\.]+e)/', '', sprintf( '%e', $totalLng) );

	$multipleLat = pow( 2, $exponentLat );
	$multipleLng = pow( 2, $exponentLng );

	$bounds['neLat'] = ceil( $bounds['neLat'] / $multipleLat ) * $multipleLat;
	$bounds['neLng'] = ceil( $bounds['neLng'] / $multipleLng ) * $multipleLng;
	$bounds['swLat'] = floor( $bounds['swLat'] / $multipleLat ) * $multipleLat;
	$bounds['swLng'] = floor( $bounds['swLng'] / $multipleLng ) * $multipleLng;

	return $bounds;
}
