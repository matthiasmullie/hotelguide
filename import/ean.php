<?php

require_once __DIR__.'/../utils/model.php';

// Hello! This ugly piece of code will parse the relevant data of ean.com's product feed to our database

// feed data
$feedId = 5; // id in db - quick and dirty in code, let's just assume this won't change in db ;)


/*
 * Download/extract data files
 */

$dirpath = __DIR__.'/../cache/import/ean';

// clear import cache
function deltree( $dir ) {
	$files = array_diff( scandir( $dir ), array( '.', '..' ) );
	foreach ( $files as $file ) {
		is_dir( "$dir/$file" ) ? deltree( "$dir/$file" ) : unlink( "$dir/$file" );
	}
	rmdir( $dir );
}
if ( file_exists( $dirpath ) ) {
	deltree( $dirpath );
}

// create cache patch
mkdir( $dirpath );


$files = array(
	// all hotel info
	'allhotels' => 'https://www.ian.com/affiliatecenter/include/Hotel_All_Active.zip',

	// hotel translations
	'translatefr' => 'https://www.ian.com/affiliatecenter/include/Hotel_Description_fr_FR.zip', // fr
	'translatenl' => 'https://www.ian.com/affiliatecenter/include/Hotel_Description_nl_NL.zip', // nl

	// stars
	'stars' => 'https://www.ian.com/affiliatecenter/include/Star_Rating_Value.zip',

	// images
	'images' => 'https://www.ian.com/affiliatecenter/include/hotelimages.zip',
);

// download & extract zip files
foreach ( $files as $folder => $file ) {
	$pathZip = $dirpath.'/'.basename( $file );
	$pathExtract = $dirpath.'/'.$folder;

	// download zip files
	$local = fopen( $pathZip, 'w' );
	$curl = curl_init( $file );
	curl_setopt( $curl, CURLOPT_FILE, $local );
	curl_exec( $curl );
	curl_close( $curl );
	fclose( $local );

	// extract
	$zip = new ZipArchive;
	if ( $zip->open( $pathZip ) ) {
		$zip->extractTo( $pathExtract );
	}
	$zip->close();

	// map extracted file names
	$localFile = glob( "$pathExtract/*.txt" );
	$files[$folder] = $localFile[0];
}


/*
 * Temporarily parse data files to sqlite
 */

$db = new PDO( "sqlite:$dirpath/ean.db" );

// build similar db structure in sqlite to import data into
$db->exec( 'CREATE TABLE locations (id INTEGER PRIMARY KEY, lat FLOAT, lng FLOAT, image VARCHAR(255), stars FLOAT )' );
$db->exec( 'CREATE TABLE language (id INTEGER, language VARCHAR(2), title VARCHAR(255), text TEXT ); CREATE INDEX id ON language (id);' );
$db->exec( 'CREATE TABLE currency (id INTEGER, currency VARCHAR(3), price FLOAT ); CREATE INDEX id ON currency (id);' );

$statementLocations = $db->prepare( '
	INSERT INTO locations (id, lat, lng)
	VALUES (:id, :lat, :lng)
' );
$statementLanguage = $db->prepare( '
	INSERT INTO language (id, language, title, text)
	VALUES (:id, :language, :title, :text)
' );
$statementCurrency = $db->prepare( '
	INSERT INTO currency (id, currency, price)
	VALUES (:id, :currency, :price)
' );
$statementStar = $db->prepare( '
	UPDATE locations
	SET stars = :stars
	WHERE id = :id
' );
$statementImage = $db->prepare( '
	UPDATE locations
	SET image = :image
	WHERE id = :id
' );

// parse all hotels data to sqlite
$file = fopen( $files['allhotels'], 'r' );
$keys = fgetcsv( $file, null, '|' );
while ( !feof( $file ) && $column = fgetcsv( $file, null, '|' ) ) {
	$column = array_combine( $keys, $column );

	$statementLocations->execute( array(
		':id' => $column['HotelID'],
		':lat' => $column['Latitude'],
		':lng' => $column['Longitude'],
	) );
	$statementLanguage->execute( array(
		':id' => $column['HotelID'],
		':language' => 'en',
		':title' => $column['Name'],
		':text' => $column['PropertyDescription'],
	) );
	$statementCurrency->execute( array(
		':id' => $column['HotelID'],
		':currency' => 'USD',
		':price' => $column['LowRate'],
	) );
}

// parse additional languages (files in utf-16)
foreach ( array( 'fr' => 'translatefr', 'nl' => 'translatenl' ) as $language => $import ) {
	$file = fopen( $files[$import], 'r' );
	$keys = explode( '|', trim( @iconv( 'utf-16', 'utf-8//IGNORE', fgets( $file ) ) ) );
	while ( !feof( $file ) && $column = explode( '|', trim( @iconv( 'utf-16', 'utf-8//IGNORE', fgets( $file ) ) ) ) ) {
		if ( count( $keys ) != count( $column ) ) {
			continue;
		}
		$column = array_combine( $keys, $column );

		$statementLanguage->execute( array(
			':id' => $column['HotelID'],
			':language' => $language,
			':title' => $column['Name'],
			':text' => $column['PropertyDescription'],
		) );
	}
}

// parse stars
$file = fopen( $files['stars'], 'r' );
$keys = fgetcsv( $file, null, '|' );
while ( !feof( $file ) && $column = fgetcsv( $file, null, '|' ) ) {
	$column = array_combine( $keys, $column );

	$statementStar->execute( array(
		':id' => $column['HotelID'],
		':stars' => $column['PropertyRating'] == 'NotAvailable' ? 0 : $column['PropertyRating'],
	) );
}

// parse images
$file = fopen( $files['images'], 'r' );
$keys = fgetcsv( $file, null, '|' );
while ( !feof( $file ) && $column = fgetcsv( $file, null, '|' ) ) {
	$column = array_combine( $keys, $column );

	if ( $column['DefaultImage'] == 'True' ) {
		$statementImage->execute( array(
			':id' => $column['HotelID'],
			':image' => $column['URL'],
		) );
	}
}


/*
 * Read from sqlite & write to real DB
 */

$callback = function( $entry ) use ( $db ) {
	$location = array();
	$location[':product_id'] = (string) $entry['id'];
	$location[':lat'] = (float) $entry['lat'];
	$location[':lng'] = (float) $entry['lng'];
	$location[':image'] = (string) $entry['image'];
	$location[':stars'] = (float) $entry['stars'];
	$location[':url'] = 'http://www.travelnow.com/templates/426957/hotels/'. $location[':product_id'] .'/overview?currency=<currency>&lang=<language>';
	$location[':url_mobile'] = null;

	$statement = $db->prepare( 'SELECT * FROM currency WHERE id = :id' );
	$statement->execute( array( ':id' => $location[':product_id'] ) );
	$currencies = array();
	while ( $currency = $statement->fetch() ) {
		$currencies[] =
			array(
				':currency' => (string) $currency['currency'],
				':price' => (float) $currency['price'],
			);
	}

	$statement = $db->prepare( 'SELECT * FROM language WHERE id = :id' );
	$languages = array();
	$statement->execute( array( ':id' => $location[':product_id'] ) );
	while ( $language = $statement->fetch() ) {
		$languages[] =
			array(
				':language' => $language['language'],
				':title' => (string) $language['title'],
				':text' => (string) $language['text'],
			);
	}

	return array( $location, $currencies, $languages );
};

$entries = $db->query( 'SELECT * FROM locations' );
Model::updateFeed( $feedId, $entries, $callback );
