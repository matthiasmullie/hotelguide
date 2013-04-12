<?php

// Hello! This ugly piece of code will parse the relevant data of hotel.com's product feed to our database

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/cache/cache.php';

set_time_limit( 0 );

$db = new PDO( "mysql:host=$host;dbname=$db", $user, $pass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51300&encoding=utf-8&type=xml-v2-simple&fid=278357&categoryType=2&additionalType=2';
$feedId = 4; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

$prepareLocation = $db->prepare( 'INSERT INTO locations (feed_id, product_id, lat, lng, title, text, text_language, image, url, stars, price, price_currency) VALUES (:feed_id, :product_id, :lat, :lng, :title, :text, :text_language, :image, :url, :stars, :price, :price_currency)' );

// empty existing data
$emptyLocation = $db->prepare( 'DELETE FROM locations WHERE feed_id = :feed_id' );
$emptyLocation->execute( array( ':feed_id' => $feedId ) );

// parse xml
$xml = new SimpleXMLElement( $feedUrl, 0, true );
foreach ( $xml->xpath( '/products/product' ) as $node ) {
	// build data to insert in db
	$location = array();
	$location[':feed_id'] = $feedId;
	$location[':product_id'] = (string) $node->ID;
	$location[':lat'] = (float) $node->properties->latitude->value;
	$location[':lng'] = (float) $node->properties->longitude->value;
	$location[':title'] = (string) $node->name;
	$location[':text'] = (string) $node->description;
	$location[':text_language'] = 'en';
	$location[':image'] = (string) $node->images->image;
	$location[':url'] = (string) $node->URL;
	$location[':stars'] = (float) $node->properties->stars->value;
	$location[':price'] = (float) $node->price->amount;
	$location[':price_currency'] = 'EUR';

	// validate data
	if (
		!$location[':product_id'] ||
		!$location[':feed_id'] ||
		!$location[':lat'] ||
		!$location[':lng'] ||
		!$location[':title'] ||
		!$location[':url'] ||
		!$location[':price']
	) {
		continue;
	}

	// insert into db
	$prepareLocation->execute( $location );
}

// purge caches
$cache = Cache::load( $cache );
$keysKey = $cache->getKey( 'keys' );
$keys = $cache->get( $keysKey );
if ( $keys !== false ) {
	foreach ( $keys as $key ) {
		$cache->delete( $key );
	}
}
$cache->delete( $keysKey );
