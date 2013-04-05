<?php

// this file is really, really, really hacky ;)

require_once '../config.php';
require_once '../utils/clusterer.php';
require_once '../utils/cache/cache.php';

ini_set('memory_limit', '1G');

$min = isset( $_GET['min'] ) ? (int) $_GET['min'] : 50;
$max = isset( $_GET['max'] ) ? (int) $_GET['max'] : 300;
$neLat = isset( $_GET['neLat'] ) ? $_GET['neLat'] : 0;
$neLng = isset( $_GET['neLng'] ) ? $_GET['neLng'] : 0;
$swLat = isset( $_GET['swLat'] ) ? $_GET['swLat'] : 0;
$swLng = isset( $_GET['swLng'] ) ? $_GET['swLng'] : 0;
$minPts = isset( $_GET['minPts'] ) ? $_GET['minPts'] : 1;
$nbrClusters = isset( $_GET['nbrClusters'] ) ? $_GET['nbrClusters'] : 50;

// only cache/round for large map views; specific zooms don't matter ;)
$cacheCluster = $neLat - $swLat > 25 || $neLng - $swLng > 25;

// init db & cache object
$db = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
$cache = Cache::load( $cache );

/*
 * Extend bounds a little bit to a rounder number, that way similar
 * requests can use the same cache. Round to a multiple of 5 only when
 * caching (that way, there are less different caches and odds that
 * that region is cached already are larger)
 */
$round = function( $value, $func ) {
	return $func( $value / 10 ) * 10;
};
$neLatRound = $round($neLat, 'ceil');
$swLatRound = $round($swLat, 'floor');
$neLngRound = $round($neLng, 'ceil');
$swLngRound = $round($swLng, 'floor');

$clustered = false;
$clusterKey = $cache->getKey( $min, $max, $neLatRound, $neLngRound, $swLatRound, $swLngRound, $minPts, $nbrClusters );
if ( $cacheCluster ) {
	$clustered = $cache->get( $clusterKey );
}

// data not in cache; fetch from db
if ( $clustered === false ) {
	$markersKey = $cache->getKey( $min, $max, $neLatRound, $neLngRound, $swLatRound, $swLngRound );
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
				':min' => $min,
				':max' => $max,
				':nelat' => $neLatRound,
				':nelng' => $neLngRound,
				':swlat' => $swLatRound,
				':swlng' => $swLngRound,
			)
		);
		$markers = $prepareMarkers->fetchAll();

		$cache->set( $markersKey, $markers );
	}

	// build clusterer
	$clusterer = new Clusterer( $neLat, $neLng, $swLat, $swLng );
	$clusterer->setMinClusterLocations( $minPts );
	$clusterer->setNumberOfClusters( $nbrClusters );
	foreach ( $markers as $marker ) {
		$clusterer->addLocation( $marker['lat'], $marker['lng'], $marker );
	}

	$clustered = array(
		'locations' => $clusterer->getLocations(),
		'clusters' => $clusterer->getClusters(),
	);

	// save to cache
	if ( $cacheCluster ) {
		$cache->set( $clusterKey, $clustered, 60 * 60 * 24 );
	}
}

echo json_encode( $clustered );
