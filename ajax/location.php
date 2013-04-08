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

if ( $data !== false ) {
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
			<div id="infowindowBottom">
				<p id="markerDisclaimer"><a class="submenu" href="ajax/disclaimer.php?url='. urlencode( 'ajax/location.php?id='.$id ) .'">Disclaimer</a></p>
			</div>
			<div id="markerImage" style="background-image: url(' . $data['image'] . ')">
				<a href="' . $data['url'] . '"></a>
			</div>
		</div>';
} else {
	echo
		'<div id="infowindowContent">
			<h2>Fout!</h2>
			<p>We konden geen gegevens voor de gevraagde locatie ophalen.</p>
			<p>
				Dit probleem kan zich voordoen wanneer we onze locatie-database aan het updaten zijn met de laatste promoties.
				In dit geval zal u binnenkort wel over de gevraagde informatie kunnen beschikken, en raden we u aan om straks opnieuw te proberen.
			</p>
			<p>Mocht u dit probleem blijven ervaren, gelieve contact met ons op te nemen op <a href="mailto:info@last-minute-vakanties.be">info@last-minute-vakanties.be</a>.</p>
			<p>Alvast onze excuses!</p>
		</div>';
}
