<?php

// @todo: not parsing feed for now; links don't resolve
exit;

set_time_limit( 7200 );
ini_set( 'memory_limit', '512M' );

require_once __DIR__.'/../utils/model.php';

// Hello! This ugly piece of code will parse the relevant data of expedia.com's product feed to our database

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51300&encoding=utf-8&type=xml-v2-simple&fid=251048&categoryType=2&additionalType=2';
$feedId = 2; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

// load xml
$xml = new SimpleXMLElement( $feedUrl, 0, true );

// parse data
$callback = function( SimpleXMLElement $node ) {
	$location = array();
	$location[':product_id'] = (string) $node->ID;
	$location[':lat'] = (float) $node->properties->latitude->value;
	$location[':lng'] = (float) $node->properties->longitude->value;
	$location[':image'] = (string) $node->images->image;
	$location[':stars'] = (float) $node->properties->stars->value;
	$location[':url'] = (string) $node->URL;
	$location[':url_mobile'] = null;

	$currencies = array();
	$currencies[] =
		array(
			':currency' => (string) $node->price->currency,
			':price' => (float) $node->price->amount
		);
	// @todo: temporary workaround for USD; will change later with real data
	$currencies[] =
		array(
			':currency' => 'USD',
			':price' => (float) $node->price->amount * 1.30
		);

	$languages = array();
	$languages[] =
		array(
			':language' => 'nl',
			':title' => (string) $node->name,
			':text' => (string) $node->description,
		);

	return array( $location, $currencies, $languages );
};

Model::updateFeed( $feedId, $xml->xpath( '/products/product' ), $callback );

/*
XML excerpt:

<products>
	<product>
		<ID>36140</ID>
		<name>Isis Island</name>
		<price>
			<currency>EUR</currency>
			<amount>47.00</amount>
		</price>
		<URL>http://www.worldticketcenter.nl/trade/?tt=505_251048_51300_&amp;r=http%3A%2F%2Fwww.worldticketcenter.nl%2Fhotels%3Fhotelid%3D36140</URL>
		<images>
			<image>http://h1.hotelxpert.nl/WTCImages/GTA/ASW-ISI1hotel_Exterior_1.jpg</image>
		</images>
		<description><![CDATA[Over het algeheel is dit een modern, aangenaam gebouw, mooi gelegen op een unieke locatie, met accommodatie en een decor van goede kwaliteit. Het hotel bevindt zich op haar eigen eiland in het midden van de Nijl, alleen te bereiken met een watertaxi.]]></description>
		<categories>
			<category>hotel</category>
		</categories>
		<properties>
			<region>
				<value>Aswan</value>
			</region>
			<Country>
				<value>Egypt</value>
			</Country>
			<City>
				<value>Aswan</value>
			</City>
			<Fax>
				<value>20-97-2317405</value>
			</Fax>
			<Phone>
				<value>20-97-2317400</value>
			</Phone>
			<RegionURL>
				<value>http://www.worldticketcenter.nl/trade/?tt=505_251048_51300_&amp;r=http%3A%2F%2Fwww.worldticketcenter.nl%2Fhotels%3FRegionId%3D2998302</value>
			</RegionURL>
			<ZipCode>
				<value></value>
			</ZipCode>
			<BestBooked>
				<value>1</value>
			</BestBooked>
			<Checkin>
				<value>-</value>
			</Checkin>
			<Checkout>
				<value>-</value>
			</Checkout>
			<Email>
				<value></value>
			</Email>
			<Floors>
				<value>0</value>
			</Floors>
			<latitude>
				<value>24.06379</value>
			</latitude>
			<longitude>
				<value>32.86958</value>
			</longitude>
			<Rooms>
				<value>0</value>
			</Rooms>
			<stars>
				<value>5</value>
			</stars>
			<Suites>
				<value>0</value>
			</Suites>
			<URL>
				<value>http://www.worldticketcenter.nl/trade/?tt=505_251048_51300_</value>
			</URL>
			<ZooverRating>
				<value>0</value>
			</ZooverRating>
			<Address>
				<value>AMBONARTI ISLAND ASWAN</value>
			</Address>
			<delete>
				<value>false</value>
			</delete>
			<State>
				<value></value>
			</State>
		</properties>
	</product>
	...
</products>
*/
