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

	if ( $data !== false && $data['url'] ) {
		// track click
		$prepareTrack = $db->prepare( 'INSERT INTO track (feed_id, location_id, data) VALUES (:feed_id, :location_id, :data)' );
		$prepareTrack->execute( array(
			':feed_id' => $data['feed_id'],
			':location_id' => $data['id'],
			':data' => serialize( $_SERVER ),
		) );

		// redirect to location url
		header( 'Location:'. $data['url'] );
		exit;
	}
}

// if we made it here, something's wrong
header( 'HTTP/1.1 500 Internal Server Error' );
