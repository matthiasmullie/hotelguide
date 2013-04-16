<?php

require_once '../config.php';

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
	$prepareTrack = $db->prepare( 'INSERT INTO track (action, feed_id, location_id, data) VALUES (:action, :feed_id, :location_id, :data)' );
	$prepareTrack->execute( array(
		':action' => 'infowindow',
		':feed_id' => $data['feed_id'],
		':location_id' => $data['id'],
		':data' => serialize( $_SERVER ),
	) );

	if ( $data !== false ) {
		echo '
			<div id="infowindowData">
				<div id="infowindowMarker">
					<div id="infowindowTop" class="clearfix">
						<a href="/ajax/redirect.php?id=' . $data['id'] . '">
							<h2>' . $data['title'] . '</h2>
							<p>' . str_repeat('&#9733;', (int) $data['stars']) . '</p>
						</a>
					</div>
					<div id="infowindowContent">
						<a id="markerUrl" class="clearfix" href="/ajax/redirect.php?id=' . $data['id'] . '"><span class="leftSpan">Bestel</span> <span class="rightSpan">€' . $data['price'] . '</span></a>
						<p id="markerText" data-language="' . $data['text_language'] . '">' . $data['text'] . '</p>
					</div>
					<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
						<a href="/ajax/redirect.php?id=' . $data['id'] . '"></a>
					</div>
				</div>
			</div>
			<div id="infowindowBottom">
				<p id="markerDisclaimer"><a class="infowindow" href="/disclaimer.php">Disclaimer</a></p>
			</div>';
		exit;
	}
}

// if we made it here, something's wrong
header( 'HTTP/1.1 500 Internal Server Error' );
