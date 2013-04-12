<?php

// Hello! This ugly piece of code will parse the relevant data of weekendesk.be's product feed to our database

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/cache/cache.php';

set_time_limit( 0 );
ini_set( 'memory_limit', '1G' );

$db = new PDO( "mysql:host=$host;dbname=$db", $user, $pass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51193&encoding=utf-8&type=xml-v2-simple&fid=388219&categoryType=2&additionalType=2';
$feedId = 3; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

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
	$location[':text_language'] = 'nl';
	$location[':image'] = (string) $node->images->image;
	$location[':url'] = (string) $node->URL;
	$location[':stars'] = round( (float) $node->properties->stars->value / 2 );
	$location[':price'] = (float) $node->price->amount;
	$location[':price_currency'] = (string) $node->price->currency;

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

/*
XML excerpt:

<products>
	<product>
		<ID>3750</ID>
		<name>Overnachting &amp; ontbijt in een landhuis nabij Chantilly</name>
		<price>
			<currency>EUR</currency>
			<amount>135.00</amount>
		</price>
		<URL>http://www.weekendesk.be/reis/?tt=2536_388219_51193_&amp;r=http%3A%2F%2Fwww.weekendesk.be%2Fmvc%2Fweekend.jsp%3Fweekend%3D3750</URL>
		<images>
			<image>http://static.booking.weekendesk.fr/image_cache/A412000/412296/412296_621_357_FSImage_1_ManoirdeGressy_Facade_exterieure.jpg</image>
		</images>
		<description><![CDATA[1 overnachting in een tweepersoons kamer klassiek, Ontbijt, Toegang tot de sauna, Toegang tot de fitnesszaal]]></description>
		<categories>
			<category>Temidden van de natuur logeren tijdens het</category>
		</categories>
		<properties>
			<hotelID>
				<value>28</value>
			</hotelID>
			<hotelName>
				<value>Manoir de Gressy</value>
			</hotelName>
			<region>
				<value>Parijs en Ile-de-France</value>
			</region>
			<city>
				<value>Gressy</value>
			</city>
			<country>
				<value>Frankrijk</value>
			</country>
			<countrycode>
				<value>FR</value>
			</countrycode>
			<rating>
				<value>4</value>
			</rating>
			<hotelrating>
				<value>8.195870206489676</value>
			</hotelrating>
			<productURL2>
				<value>http://www.weekendesk.be/reis/?tt=2536_388219_51193_&amp;r=http%3A%2F%2Fwww.weekendesk.be%2Fmvc%2Fhotel.jsp%3Fhotel%3D28</value>
			</productURL2>
			<longitude>
				<value>2.674093</value>
			</longitude>
			<latitude>
				<value>48.965008</value>
			</latitude>
			<packageprice>
				<value>248.00</value>
			</packageprice>
			<packagepromotion>
				<value></value>
			</packagepromotion>
		</properties>
	</product>
	...
</productd>
*/
