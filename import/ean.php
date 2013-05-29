<?php

// @todo: not parsing for lack of EUR currency data
exit;

require_once __DIR__.'/../utils/model.php';
require_once __DIR__.'/../utils/ean.php';

// Hello! This ugly piece of code will parse the relevant data of ean.com's product feed to our database

// feed data
$feedId = 5; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

// init EAN db
$ean = new Ean();


/*
 * Read from sqlite & write to real DB
 */

$callback = function( $entry ) use ( $ean ) {
	$location = array();
	$location[':product_id'] = (string) $entry['id'];
	$location[':lat'] = (float) $entry['lat'];
	$location[':lng'] = (float) $entry['lng'];
	$location[':image'] = (string) $entry['image'];
	$location[':stars'] = (float) $entry['stars'];
	$location[':url'] = 'http://www.travelnow.com/templates/426957/hotels/'. $location[':product_id'] .'/overview?currency=<currency>&lang=<language>';
	$location[':url_mobile'] = null;

	$statement = $ean->getDB()->prepare( 'SELECT * FROM currency WHERE id = :id' );
	$statement->execute( array( ':id' => $location[':product_id'] ) );
	$currencies = array();
	while ( $currency = $statement->fetch() ) {
		$currencies[] =
			array(
				':currency' => (string) $currency['currency'],
				':price' => (float) $currency['price'],
			);
	}

	$statement = $ean->getDB()->prepare( 'SELECT * FROM language WHERE id = :id' );
	$languages = array();
	$statement->execute( array( ':id' => $location[':product_id'] ) );
	while ( $language = $statement->fetch() ) {
		$languages[] =
			array(
				':language' => $language['language'],
				':title' => (string) $language['title'],
				':text' => (string) $language['text'],
			);
	}

	return array( $location, $currencies, $languages );
};

$entries = $ean->getDB()->query( 'SELECT * FROM locations' );
Model::updateFeed( $feedId, $entries, $callback );
