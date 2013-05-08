<?php

require_once __DIR__.'/../utils/model.php';

// Hello! This ugly piece of code will parse the relevant data of hotel.com's product feed to our database

// feed data
$feedUrl = 'http://pf.tradetracker.net/?aid=51300&encoding=utf-8&type=xml-v2-simple&fid=278357&categoryType=2&additionalType=2';
$feedId = 4; // id in db - quick and dirty in code, let's just assume this won't change in db ;)

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

	$currencies = array();
	$currencies[] =
		array(
			':currency' => (string) $node->price->currency,
			':price' => (float) $node->price->amount,
		);
	// @todo: temporary workaround for USD; will change later with real data
	$currencies[] =
		array(
			':currency' => 'USD',
			':price' => (float) $node->price->amount * 1.31
		);

	$languages = array();
	$languages[] =
		array(
			':language' => 'en',
			':title' => (string) $node->name,
			':text' => (string) $node->description,
			':url' => (string) $node->URL,
			':url_mobile' => null, // will be filled in later
		);

	/*
	 * Urls:
	 * * TT url normal: http://tc.tradetracker.net/?c=2620&m=278357&a=51300&u=http%3A%2F%2Fnl.hotels.com%2Fho115783%2Fthe-fairmont-san-francisco-san-francisco-verenigde-staten%2F%3Fwapb1%3Dhotelcontentfeed
	 * * Url normal: http://nl.hotels.com/ho115783/the-fairmont-san-francisco-san-francisco-verenigde-staten/
	 * * Url mobile: http://nl.hotels.com/mobile/hotelDetails.html?hotelId=115783
	 * * TT url mobile: http://tc.tradetracker.net/?c=2620&m=278357&a=51300&u=http%3A%2F%2Fnl.hotels.com%2Fmobile%2FhotelDetails.html%3FhotelId%3D115783%26wapb1%3Dhotelcontentfeed
	 */
	foreach ( $languages as &$language ) {
		$mobileUrl = 'http://nl.hotels.com/mobile/hotelDetails.html?hotelId=' . $location[':product_id'] . '&wapb1=hotelcontentfeed';
		$language[':url_mobile'] = 'http://tc.tradetracker.net/?c=2620&m=278357&a=51300&u=' . urlencode( $mobileUrl );
	}

	return array( $location, $currencies, $languages );
};

Model::updateFeed( $feedId, $xml->xpath( '/products/product' ), $callback );

/*
XML excerpt:

<products>
	<product>
		<ID>204542</ID>
		<name>Tierra del Sol Resort, Spa &amp; Country Club</name>
		<price>
			<currency>EUR</currency>
			<amount>272.12</amount>
		</price>
		<URL>http://tc.tradetracker.net/?c=2620&amp;m=278357&amp;a=51300&amp;u=http%3A%2F%2Fnl.hotels.com%2Fho204542%2Ftierra-del-sol-resort-spa-country-club-noord-aruba%2F%3Fwapb1%3Dhotelcontentfeed</URL>
		<images>
			<image>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_70_b.jpg</image>
		</images>
		<description><![CDATA[Location. Tierra del Sol Resort, Spa & Country Club is located near the beach in Noord and close to Tierra del Sol Golf Course, Arashi Beach, and California Lighthouse. Nearby points of interest also include Alto Vista Chapel and Malmok Beach. Property Features. Tierra del Sol Resort, Spa & Country Club&apos;s restaurant serves breakfast, lunch, and dinner. A poolside bar and a bar/lounge are open for drinks. Room service is available during limited]]></description>
		<categories/>
		<properties>
			<accessibilityEquipmentForTheDe>
				<value>No</value>
			</accessibilityEquipmentForTheDe>
			<accessibleBathroom>
				<value>No</value>
			</accessibleBathroom>
			<accessiblePathOfTravel>
				<value>No</value>
			</accessiblePathOfTravel>
			<adventureHotel>
				<value>Yes</value>
			</adventureHotel>
			<apartmentHotel>
				<value>No</value>
			</apartmentHotel>
			<beachHotel>
				<value>No</value>
			</beachHotel>
			<boutiqueHotel>
				<value>No</value>
			</boutiqueHotel>
			<brailleOrRaisedSignage>
				<value>No</value>
			</brailleOrRaisedSignage>
			<budgetValue>
				<value>No</value>
			</budgetValue>
			<businessHotel>
				<value>No</value>
			</businessHotel>
			<casinoHotel>
				<value>No</value>
			</casinoHotel>
			<chain>
				<value></value>
			</chain>
			<childActivities>
				<value>No</value>
			</childActivities>
			<city>
				<value>Noord</value>
			</city>
			<country>
				<value>Aruba</value>
			</country>
			<countrysideHotel>
				<value>No</value>
			</countrysideHotel>
			<designHotel>
				<value>No</value>
			</designHotel>
			<extraImage_0>
				<value>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_68_b.jpg</value>
			</extraImage_0>
			<extraImage_1>
				<value>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_60_b.jpg</value>
			</extraImage_1>
			<extraImage_2>
				<value>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_41_b.jpg</value>
			</extraImage_2>
			<extraImage_3>
				<value>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_54_b.jpg</value>
			</extraImage_3>
			<extraImage_4>
				<value>http://media.expedia.com/hotels/1000000/530000/529200/529173/529173_64_b.jpg</value>
			</extraImage_4>
			<freeBreakfast>
				<value>No</value>
			</freeBreakfast>
			<gourmetHotel>
				<value>No</value>
			</gourmetHotel>
			<greenSustainableHotel>
				<value>Yes</value>
			</greenSustainableHotel>
			<gym>
				<value>Yes</value>
			</gym>
			<handicappedParking>
				<value>No</value>
			</handicappedParking>
			<historicHotel>
				<value>No</value>
			</historicHotel>
			<hotTub>
				<value>No</value>
			</hotTub>
				<iataArrival>
			<value>AUA</value>
			</iataArrival>
			<in_roomAccessibility>
				<value>No</value>
			</in_roomAccessibility>
			<internetAccess>
				<value>No</value>
			</internetAccess>
			<kitchen>
				<value>No</value>
			</kitchen>
			<latitude>
				<value>12.60488</value>
			</latitude>
			<longitude>
				<value>-70.04176</value>
			</longitude>
			<luxury>
				<value>No</value>
			</luxury>
			<meetingFacilities>
				<value>No</value>
			</meetingFacilities>
			<numReviews>
				<value>5</value>
			</numReviews>
			<petsAllowed>
				<value>false</value>
			</petsAllowed>
			<pool>
				<value>Yes</value>
			</pool>
			<propertyType>
				<value>ESR</value>
			</propertyType>
			<restaurant>
				<value>No</value>
			</restaurant>
			<reviewScore>
				<value>4.4</value>
			</reviewScore>
			<roll_inShower>
				<value>No</value>
			</roll_inShower>
			<romanticHotel>
				<value>No</value>
			</romanticHotel>
			<shopping>
				<value>No</value>
			</shopping>
			<spa>
				<value>No</value>
			</spa>
			<stars>
				<value>4.0</value>
			</stars>
			<state>
				<value></value>
			</state>
			<villaHotel>
				<value>No</value>
			</villaHotel>
			<wineryVineyard>
				<value>No</value>
			</wineryVineyard>
			<zipCode>
				<value></value>
			</zipCode>
		</properties>
	</product>
	...
</products>
*/
