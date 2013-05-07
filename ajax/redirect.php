<?php

require_once '../config.php';

$feedId = isset( $_GET['feedId'] ) ? $_GET['feedId'] : false;
$productId = isset( $_GET['productId'] ) ? $_GET['productId'] : false;
$mobile = (int) isset( $_GET['mobile'] ) && $_GET['mobile'];

if ( $feedId != false && $productId !== false ) {
	$db = new PDO( "mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );
	$prepareData = $db->prepare('
		SELECT *
		FROM locations AS l
		WHERE l.feed_id = :feed_id AND l.product_id = :product_id
	');
	$prepareData->execute( array( ':feed_id' => $feedId, ':product_id' => $productId ) );
	$data = $prepareData->fetch();

	if ( $data !== false && $data['url'] ) {
		// track click
		$prepareTrack = $db->prepare( 'INSERT INTO track (action, feed_id, product_id, data, time) VALUES (:action, :feed_id, :product_id, :data, :time)' );
		$prepareTrack->execute( array(
			':action' => 'clickthrough',
			':feed_id' => $data['feed_id'],
			':product_id' => $data['product_id'],
			':data' => serialize( $_SERVER ),
			':time' => date( 'Y-m-d H:i:s' ),
		) );

		// redirect to location url
		$url = ( $mobile && $data['url_mobile'] ) ? $data['url_mobile'] : $data['url'];
		header( 'Location:'. $url );
		exit;
	}
}

// if we made it here, something's wrong
header( 'HTTP/1.1 500 Internal Server Error' );
