<?php

// this file is really, really, really hacky ;)

require_once __DIR__.'/../utils/model.php';
require_once __DIR__.'/../utils/clusterer.php';

$bounds = array(
	'neLat' => isset( $_GET['bounds']['neLat'] ) ? (float) $_GET['bounds']['neLat'] : 0,
	'neLng' => isset( $_GET['bounds']['neLng'] ) ? (float) $_GET['bounds']['neLng'] : 0,
	'swLat' => isset( $_GET['bounds']['swLat'] ) ? (float) $_GET['bounds']['swLat'] : 0,
	'swLng' => isset( $_GET['bounds']['swLng'] ) ? (float) $_GET['bounds']['swLng'] : 0
);
$currency = isset( $_GET['currency'] ) ? $_GET['currency'] : 'EUR';
$minPrice = isset( $_GET['prices']['min'] ) ? (int) $_GET['prices']['min'] : 50;
$maxPrice = isset( $_GET['prices']['max'] ) ? (int) $_GET['prices']['max'] : 300;
$minPts = isset( $_GET['minPts'] ) ? (int) $_GET['minPts'] : 1;
$nbrClusters = isset( $_GET['nbrClusters'] ) ? (int) $_GET['nbrClusters'] : 50;
$spanBoundsLat = isset( $_GET['spanBounds']['lat'] ) ? (bool) $_GET['spanBounds']['lat'] : false;
$spanBoundsLng = isset( $_GET['spanBounds']['lng'] ) ? (bool) $_GET['spanBounds']['lng'] : false;
$language = isset( $_GET['language'] ) ? $_GET['language'] : 'en';

/*
 * Only cache/round for large map views; specific zooms don't matter;
 * data will be processed much faster since the resultset is small
 */
$cacheCluster = $bounds['neLat'] - $bounds['swLat'] > 20 || $bounds['neLng'] - $bounds['swLng'] > 20;

$clustered = false;
if ( $cacheCluster ) {
	$clusterKey = Model::getCache()->getKey( 'cluster', $currency, $minPrice, $maxPrice, $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'], $minPts, $nbrClusters );
	$clustered = Model::getCache()->get( $clusterKey );
}

// clustered data not in cache; fetch from db
if ( $clustered === false ) {
	$markers = Model::getMarkers( $currency, $minPrice, $maxPrice, $bounds, $spanBoundsLat, $spanBoundsLng );

	// build clusterer
	$clusterer = new Clusterer( $bounds['neLat'], $bounds['neLng'], $bounds['swLat'], $bounds['swLng'], $spanBoundsLat, $spanBoundsLng );
	$clusterer->setMinClusterLocations( $minPts );
	$clusterer->setNumberOfClusters( $nbrClusters );

	foreach ( $markers as $marker ) {
		$clusterer->addLocation(
			$marker['lat'],
			$marker['lng'],
			array(
				'feed_id' => $marker['feed_id'],
				'product_id' => $marker['product_id'],
				'price' => $marker['price'],
				'currency' => $marker['currency'],
			)
		);
	}

	$clustered = array(
		'locations' => $clusterer->getLocations(),
		'clusters' => $clusterer->getClusters()
	);
}

// save to/extend cache
if ( $cacheCluster ) {
	Model::getCache()->set( $clusterKey, $clustered, 0 );
}

foreach ( $clustered['locations'] as &$data ) {
	// format currency
	$formatter = new NumberFormatter( null, NumberFormatter::CURRENCY );
	$formatter->setAttribute( NumberFormatter::FRACTION_DIGITS, 0 );
	$data['text'] = $formatter->formatCurrency( $data['price'], $data['currency'] );

	/*
	 * Due to some bug in ICU, rounding in currency does not always happen.
	 * Just cut remaining decimals (don't care too much about rounding up
	 * accurately in this bugged case)
	 */
	$separator = $formatter->getSymbol( NumberFormatter::DECIMAL_SEPARATOR_SYMBOL );
	$data['text'] = preg_replace( '/'. preg_quote( $separator ) .'[0-9]+/', '', $data['text'] );

	// filter the data, we want to output as few characters as possible to the api
	$data = array(
		'feed_id' => $data['feed_id'],
		'product_id' => $data['product_id'],
		'lat' => $data['lat'],
		'lng' => $data['lng'],
		'text' => $data['text'],
	);
}

header( 'Content-type: application/json' );
echo json_encode( $clustered );
