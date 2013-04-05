<?php

require_once '../config.php';

if ( !isset( $_GET['id'] ) ) {
	exit;
}
$id = (int) $_GET['id'];

$db = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));
$prepareData = $db->prepare('
	SELECT *
	FROM locations AS l
	WHERE l.id = :id
');
$prepareData->execute(array(':id' => $id));
$data = $prepareData->fetch();

// @todo: show error message if no data found ("onze database wordt momenteel geupdated, gelieve binnen enkele minuten opnieuw te proberen")

echo
	'<div id="infowindowMarker">
		<div id="infowindowTop" class="clearfix">
			<a href="' . $data['url'] . '">
				<h2>' . $data['title'] . '</h2>
				<p>' . str_repeat('&#9733;', (int) $data['stars']) . '</p>
			</a>
		</div>
		<div id="infowindowData">
			<a id="markerUrl" class="clearfix" href="' . $data['url'] . '"><span class="leftSpan">Bestel</span> <span class="rightSpan">€' . $data['price'] . '</span></a>
			<p id="markerText">' . $data['text'] . '</p>
		</div>
		<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
			<a href="' . $data['url'] . '"></a>
		</div>
	</div>';
