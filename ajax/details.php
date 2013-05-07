<?php

require_once '../config.php';
require_once '../utils/translator.php';

$feedId = isset( $_GET['feedId'] ) ? $_GET['feedId'] : false;
$productId = isset( $_GET['productId'] ) ? $_GET['productId'] : false;
$mobile = (int) isset( $_GET['mobile'] ) && $_GET['mobile'];
$host = isset( $_GET['host'] ) ? $_GET['host'] : '';
$language = isset( $_GET['language'] ) ? $_GET['language'] : 'en';

if ( $feedId != false && $productId !== false ) {
	$db = new PDO( "mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );
	$prepareData = $db->prepare('
		SELECT *
		FROM locations AS l
		WHERE l.feed_id = :feed_id AND l.product_id = :product_id
	');
	$prepareData->execute( array( ':feed_id' => $feedId, ':product_id' => $productId ) );
	$data = $prepareData->fetch();

	// track click
	$prepareTrack = $db->prepare( 'INSERT INTO track (action, feed_id, product_id, data, time) VALUES (:action, :feed_id, :product_id, :data, :time)' );
	$prepareTrack->execute( array(
		':action' => 'infowindow',
		':feed_id' => $data['feed_id'],
		':product_id' => $data['product_id'],
		':data' => serialize( $_SERVER ),
		':time' => date( 'Y-m-d H:i:s' ),
	) );

	if ( $data !== false ) {
		// format currency
		$formatter = new NumberFormatter( $language, NumberFormatter::CURRENCY );
		$data['formatted_price'] = $formatter->formatCurrency( $data['price'], $data['price_currency'] );

		// translate text
		$translator = new Translator( $data['text_language'], $language );
		$translation = $translator->translate( $data['text'] );
		$data['text'] = $translation ?: $data['text'];

		echo '
			<div id="infowindowData">
				<div id="infowindowMarker">
					<div id="infowindowTop" class="clearfix">
						<a href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&mobile=' . $mobile . '" target="_blank">
							<h2>' . $data['title'] . '</h2>
							<p>' . str_repeat('&#9733;', (int) $data['stars']) . '</p>
						</a>
					</div>
					<div id="infowindowContent">
						<a id="markerUrl" class="clearfix" href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&mobile=' . $mobile . '" target="_blank">
							<span class="leftSpan" data-l10n-id="order">Order</span>
							<span class="rightSpan" data-l10n-id="pricePerNight" data-l10n-args=\'' . json_encode( array( 'price' => $data['formatted_price'] ) ) . '\'>' . $data['formatted_price'] . '/night</span>
						</a>
						<p id="markerText">' . $data['text'] . '</p>
					</div>
					<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
						<a href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&mobile=' . $mobile . '" target="_blank"></a>
					</div>
				</div>
			</div>
			<div id="infowindowBottom">
				<p id="markerDisclaimer"><a class="infowindow" href="disclaimer.php" data-l10n-id="disclaimer">Disclaimer</a></p>
			</div>';
		exit;
	}
}

// if we made it here, something's wrong
header( 'HTTP/1.1 500 Internal Server Error' );
