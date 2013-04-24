<?php

// Hello! This ugly piece of code will parse the relevant data of expedia.com's product feed to our database

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/cache/cache.php';

set_time_limit( 0 );
ini_set( 'memory_limit', '1G' );

$db = new PDO( "mysql:host=$host;dbname=$db", $user, $pass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51300&encoding=utf-8&type=xml-v2-simple&fid=273779&categoryType=2&additionalType=2';
$feedId = 1; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

$prepareLocation = $db->prepare( 'INSERT INTO locations (feed_id, product_id, lat, lng, title, text, text_language, image, url, url_mobile, stars, price, price_currency) VALUES (:feed_id, :product_id, :lat, :lng, :title, :text, :text_language, :image, :url, :url_mobile, :stars, :price, :price_currency)' );

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
	$location[':stars'] = (float) $node->properties->stars->value;
	$location[':price'] = (float) $node->properties->totalPrice->value; // (float) $node->price->amount;
	$location[':price_currency'] = (string) $node->price->currency;

	/*
	 * Urls:
	 * * TT url normal: http://tc.tradetracker.net/?c=5592&m=273779&a=51300&u=http%3A%2F%2Fwww.expedia.nl%2Fpubspec%2Fscripts%2Feap.asp%3FGOTO%3DHOTDETAILS%26HotID%3D864%26eapid%3D1843-11
	 * * Url normal: http://www.expedia.nl/San-Francisco-Hotels-The-Huntington-Hotel.h864.Hotelinfo?eapid=1843-11&affcid=nl.network.tradetracker.51300&rm1=a2&
	 * * Url mobile: http://www.expedia.nl/MobileHotel/ModifySearch?hotelId=864&checkInDate=2013-04-21&checkOutDate=2013-04-25&room1=2&sourcePage=offers
	 * * TT url mobile: http://tc.tradetracker.net/?c=5592&m=273779&a=51300&u=http%3A%2F%2Fwww.expedia.nl%2FMobileHotel%2FModifySearch%3FhotelId%3D864%26checkInDate%3D2013-04-19%26checkOutDate%3D2013-04-20%26room1%3D2%26sourcePage%3Doffers
	 */
	$location[':url'] = (string) $node->URL;
	if ( preg_match( '/HotID%3D([0-9]+)/', $location[':url'], $match ) ) {
		$mobileUrl = 'http://www.expedia.nl/MobileHotel/ModifySearch?hotelId='. $match[1] .'&checkInDate='. date( 'Y-m-d' ) .'&checkOutDate='. date( 'Y-m-d', strtotime( 'tomorrow' ) ) .'&room1=2&sourcePage=offers&eapid=1843-11';
		$location[':url_mobile'] = 'http://tc.tradetracker.net/?c=5592&m=273779&a=51300&u=' . urlencode( $mobileUrl );
	}

	// change image size to url with better size
	$location[':image'] = str_replace( '_t.jpg', '_b.jpg', $location[':image'] );

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
		<ID>d9cfc5f7fcbddc648e8a08161879649af3c31704</ID>
		<name>Saint Georges Hotel</name>
		<price>
			<currency>EUR</currency>
			<amount>129.69</amount>
		</price>
		<URL>http://tc.tradetracker.net/?c=5592&amp;m=273779&amp;a=51300&amp;u=http%3A%2F%2Fwww.expedia.nl%2Fpubspec%2Fscripts%2Feap.asp%3FGOTO%3DHOTDETAILS%26HotID%3D1%26eapid%3D1843-11</URL>
		<images>
			<image>http://media.expedia.com/hotels/1000000/10000/100/1/1_6_t.jpg</image>
		</images>
		<description><![CDATA[Dit hotel ligt in het hartje van Londen, op loopafstand van Oxford Circus, London Palladium Theater en BT-toren. Andere bezienswaardigheden in de nabije omgeving zijn Koninklijke Muziekacademie en Trafalgar Square.]]></description>
		<categories/>
		<properties>
			<totalPrice>
				<value>155.62</value>
			</totalPrice>
			<currency>
				<value>EUR</value>
			</currency>
			<hotelType>
				<value>Merchant</value>
			</hotelType>
			<stars>
				<value>4</value>
			</stars>
			<streetAddress>
				<value>Langham Place, Regent Street</value>
			</streetAddress>
			<city>
				<value>London</value>
			</city>
			<province>
				<value>England</value>
			</province>
			<country>
				<value>United Kingdom</value>
			</country>
			<zipCode>
				<value></value>
			</zipCode>
			<latitude>
				<value>51.517809</value>
			</latitude>
			<longitude>
				<value>-0.143121</value>
			</longitude>
			<hotelID>
				<value>1</value>
			</hotelID>
			<amenity1>
				<value>Air conditioning</value>
			</amenity1>
			<amenity2>
				<value>High-speed Internet</value>
			</amenity2>
			<amenity3>
				<value></value>
			</amenity3>
			<amenity4>
				<value></value>
			</amenity4>
			<amenity5>
				<value></value>
			</amenity5>
		</properties>
	</product>
	...
</products>
*/