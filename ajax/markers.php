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

	// only cache/round for large map views; specific zooms don't matter ;)
	$cacheCluster = $bounds['neLat'] - $bounds['swLat'] > 25 || $bounds['neLng'] - $bounds['swLng'] > 25;

	$clustered = false;
	if ( $cacheCluster ) {
		/*
		 * Extend bounds a little bit to a rounder number, that way similar
		 * requests can use the same cache. Round to a multiple of 1 only when
		 * caching (that way, there are less different caches and odds that
		 * that region is cached already are larger).
		 *
		 * We don't round as aggressively as we do for fetching the data in db.
		 * If clusters are calculated on much larger bounds, there would be
		 * significantly fewer displayed in the viewport.
		 */
		$bounds['neLat'] = ceil( $bounds['neLat'] );
		$bounds['neLng'] = ceil( $bounds['neLng'] );
		$bounds['swLat'] = ceil( $bounds['swLat'] );
		$bounds['swLng'] = ceil( $bounds['swLng'] );

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
			$clusterer->addLocation( $marker['lat'], $marker['lng'], $marker );
		}

		$clustered = array(
			'locations' => $clusterer->getLocations(),
			'clusters' => $clusterer->getClusters(),
		);
	}

	// save to/extend cache
	if ( $cacheCluster ) {
		$cache->set( $clusterKey, $clustered, 60 * 60 * 24 );
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
	global $cache, $db;

	/*
	 * Extend bounds a little bit to a rounder number, that way similar
	 * requests can use the same cache. Round to a multiple of 10 only when
	 * caching (that way, there are less different caches and odds that
	 * that region is cached already are larger).
	 *
	 * The bounds are aggressive and may fetch significantly more than
	 * requested, but that'll only result in fewer follow-up queries later!
	 */
	$bounds['neLat'] = ceil( $bounds['neLat'] / 10 ) * 10;
	$bounds['neLng'] = ceil( $bounds['neLng'] / 10 ) * 10;
	$bounds['swLat'] = floor( $bounds['swLat'] / 10 ) * 10;
	$bounds['swLng'] = floor( $bounds['swLng'] / 10 ) * 10;

	$markersKey = getCacheKey( $minPrice, $maxPrice, $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'] );
	$markers = $cache->get( $markersKey );

	if ( $markers === false ) {
		$prepareMarkers = $db->prepare('
			SELECT l.id, l.lat, l.lng, l.price
			FROM locations AS l
			WHERE
				l.price > :min AND l.price < :max AND
				l.lat > :swlat AND l.lat < :nelat AND
				l.lng > :swlng AND l.lng < :nelng
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
		$markers = $prepareMarkers->fetchAll();
	}

	// save to/extend cache
	$cache->set( $markersKey, $markers, 60 * 60 * 24 );

	return $markers;
}
