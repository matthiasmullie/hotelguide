<?php

require_once __DIR__.'/../utils/model.php';

$feedId = isset( $_GET['feedId'] ) ? $_GET['feedId'] : false;
$productId = isset( $_GET['productId'] ) ? $_GET['productId'] : false;
$currency = isset( $_GET['currency'] ) ? $_GET['currency'] : 'EUR';
$language = isset( $_GET['language'] ) ? $_GET['language'] : 'en';
$mobile = (int) isset( $_GET['mobile'] ) && $_GET['mobile'];

if ( $feedId != false && $productId !== false ) {
	$data = Model::getDetails( $feedId, $productId, $currency, $language );

	Model::track( 'clickthrough', $feedId, $productId );

	if ( $data !== false ) {
		// redirect to location url
		$url = ( $mobile && $data['url_mobile'] ) ? $data['url_mobile'] : $data['url'];
		header( 'Location:'. $url );
		exit;
	}
}

// if we made it here, something's wrong
header( 'HTTP/1.1 500 Internal Server Error' );
