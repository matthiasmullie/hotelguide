<?php

require_once '../config.php';
require_once '../utils/translator.php';

if ( isset( $_GET['id'] ) ) {
	$id = (int) $_GET['id'];

	$db = new PDO( "mysql:host=$host;dbname=$db", $user, $pass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );
	$prepareData = $db->prepare('
		SELECT *
		FROM locations AS l
		WHERE l.id = :id
	');
	$prepareData->execute( array( ':id' => $id ) );
	$data = $prepareData->fetch();

	// track click
	$prepareTrack = $db->prepare( 'INSERT INTO track (action, feed_id, location_id, data, time) VALUES (:action, :feed_id, :location_id, :data, :time)' );
	$prepareTrack->execute( array(
		':action' => 'infowindow',
		':feed_id' => $data['feed_id'],
		':location_id' => $data['id'],
		':data' => serialize( $_SERVER ),
		':time' => date( 'Y-m-d H:i:s' ),
	) );

	if ( $data !== false ) {
		$mobile = (int) isset( $_GET['mobile'] ) && $_GET['mobile'];
		$host = isset( $_GET['host'] ) ? $_GET['host'] : '';
		$locale = isset( $_GET['locale'] ) ? $_GET['locale'] : 'be_NL';

		// format currency
		$formatter = new NumberFormatter( $locale, NumberFormatter::CURRENCY );
		$data['formatted_price'] = $formatter->formatCurrency( $data['price'], $data['price_currency'] );

		// translate text
		$translator = new Translator( $data['text_language'], $locale );
		$translation = $translator->translate( $data['text'] );
		$data['text'] = $translation ?: $data['text'];

		echo '
			<div id="infowindowData">
				<div id="infowindowMarker">
					<div id="infowindowTop" class="clearfix">
						<a href="'. $host .'ajax/redirect.php?id=' . $data['id'] . '&mobile=' . $mobile . '" target="_blank">
							<h2>' . $data['title'] . '</h2>
							<p>' . str_repeat('&#9733;', (int) $data['stars']) . '</p>
						</a>
					</div>
					<div id="infowindowContent">
						<a id="markerUrl" class="clearfix" href="'. $host .'ajax/redirect.php?id=' . $data['id'] . '&mobile=' . $mobile . '" target="_blank">
							<span class="leftSpan" data-l10n-id="order">Order</span>
							<span class="rightSpan" data-l10n-id="pricePerNight" data-l10n-args=\'' . json_encode( array( 'price' => $data['formatted_price'] ) ) . '\'>' . $data['formatted_price'] . '/night</span>
						</a>
						<p id="markerText">' . $data['text'] . '</p>
					</div>
					<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
						<a href="'. $host .'ajax/redirect.php?id=' . $data['id'] . '&mobile=' . $mobile . '" target="_blank"></a>
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
