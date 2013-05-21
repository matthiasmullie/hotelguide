<?php

require_once __DIR__.'/../utils/model.php';
require_once __DIR__.'/../utils/translator.php';

$feedId = isset( $_GET['feedId'] ) ? $_GET['feedId'] : false;
$productId = isset( $_GET['productId'] ) ? $_GET['productId'] : false;
$mobile = (int) isset( $_GET['mobile'] ) && $_GET['mobile'];
$host = isset( $_GET['host'] ) ? $_GET['host'] : '';
$language = isset( $_GET['language'] ) ? $_GET['language'] : 'en';
$currency = isset( $_GET['currency'] ) ? $_GET['currency'] : 'EUR';

if ( $feedId != false && $productId !== false ) {
	$data = Model::getDetails( $feedId, $productId, $currency, $language );

	Model::track( 'infowindow', $feedId, $productId );

	if ( $data !== false ) {
		// format currency
		$formatter = new NumberFormatter( $language, NumberFormatter::CURRENCY );
		$data['formatted_price'] = $formatter->formatCurrency( $data['price'], $data['currency'] );

		/*
		 * Translate text: even though we requested a specific language from the
		 * database, chances are that no content is available for that language
		 * and we were served another language.
		 */
		$translator = new Translator( $data['language'], $language );
		$translation = $translator->translate( $data['text'] );
		$data['text'] = $translation ?: $data['text'];

		echo '
			<div id="infowindowData">
				<div id="infowindowMarker">
					<div id="infowindowTop" class="clearfix">
						<a href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&language=' . $data['language'] . '&currency='. $data['currency'] .'&mobile=' . $mobile . '" target="_blank">
							<h2>' . $data['title'] . '</h2>
							<p>' . str_repeat('&#9733;', (int) $data['stars']) . '</p>
						</a>
					</div>
					<div id="infowindowContent">
						<a id="markerUrl" class="clearfix" href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&language=' . $data['language'] . '&currency='. $data['currency'] .'&mobile=' . $mobile . '" target="_blank">
							<span class="leftSpan" data-l10n-id="order">Order</span>
							<span class="rightSpan" data-l10n-id="pricePerNight" data-l10n-args=\'' . json_encode( array( 'price' => $data['formatted_price'] ) ) . '\'>' . $data['formatted_price'] . '/night</span>
						</a>
						<p id="markerText">' . $data['text'] . '</p>
					</div>
					<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
						<a href="'. $host .'ajax/redirect.php?feedId=' . $data['feed_id'] . '&productId=' . $data['product_id'] . '&language=' . $data['language'] . '&currency='. $data['currency'] .'&mobile=' . $mobile . '" target="_blank"></a>
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
