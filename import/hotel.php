<?php

/*
 * Hello! This ugly piece of code will parse the relevant data of hotel.com's product feed to our database
 */

require_once __DIR__ . '/../db.php';

set_time_limit(0);

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51300&encoding=utf-8&type=xml-v2-simple&fid=278357&categoryType=2&additionalType=2';
$feedId = 2; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

$prepareLocation = $db->prepare('INSERT INTO locations (feed_id, lat, lng, title, text, image, url, type, stars, price) VALUES (:feed_id, :lat, :lng, :title, :text, :image, :url, :type, :stars, :price)');

// empty existing data
$emptyLocation = $db->prepare('DELETE FROM locations WHERE feed_id = :feed_id');
$emptyLocation->execute(array(':feed_id' => 1));

// parse xml
$xml = new SimpleXMLElement($feedUrl, 0, true);
foreach($xml->xpath('/products/product') as $node)
{
	// build data to insert in db
	$location = array();
	$location[':feed_id'] = $feedId;
	$location[':lat'] = (float) $node->properties->latitude->value;
	$location[':lng'] = (float) $node->properties->longitude->value;
	$location[':title'] = (string) $node->name;
	$location[':text'] = (string) $node->description;
	$location[':image'] = (string) $node->images->image;
	$location[':url'] = (string) $node->URL;
	$location[':type'] = 'hotel';
	$location[':stars'] = (float) $node->properties->stars->value;
	$location[':price'] = (float) $node->price->amount;

	// validate data
	if(!$location[':feed_id']) continue;
	if(!$location[':lat']) continue;
	if(!$location[':lng']) continue;
	if(!$location[':title']) continue;
	if(!$location[':url']) continue;
	if(!$location[':type']) continue;
	if(!$location[':price']) continue;

	// insert into db
	$prepareLocation->execute($location);
}

// purge caches
// @todo: this assumes filecaching; find a better way to update caches
$files = glob('../cache/*.pdf');
foreach($files as $file) {
	unlink("../cache/$file");
}
