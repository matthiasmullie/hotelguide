<?php

require_once __DIR__.'/model.php';

class Ean {
	protected $db;

	protected $dirpath = '/tmp';

	protected $files = array(
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

	public function __construct() {
		$this->dirpath = __DIR__.'/../cache/import/ean';

		// EAN files are refreshed every day. If the db file is > 24h old, let it rebuild
		if ( file_exists( $this->dirpath ) && filemtime( $this->dirpath ) < strtotime( '-24 hours' ) ) {
			$this->deltree( $this->dirpath );
		}

		if ( !file_exists( $this->dirpath ) ) {
			mkdir( $this->dirpath );

			$this->download();
			$this->extract();
			$this->createDB();
			$this->parseAllHotels();
			$this->parseLanguages();
			$this->parseStars();
			$this->parseImages();
		}
	}

	protected function deltree( $dir ) {
		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			is_dir( "$dir/$file" ) ? $this->deltree( "$dir/$file" ) : unlink( "$dir/$file" );
		}
		rmdir( $dir );
	}

	protected function download() {
		foreach ( $this->files as $file => $url ) {
			$pathZip = $this->getZipPath( $file );

			// download zip files
			$local = fopen( $pathZip, 'w' );
			$curl = curl_init( $url );
			curl_setopt( $curl, CURLOPT_FILE, $local );
			curl_exec( $curl );
			curl_close( $curl );
			fclose( $local );
		}
	}

	protected function extract() {
		foreach ( $this->files as $file => $url ) {
			$pathZip = $this->getZipPath( $file );
			$pathExtract = $this->getExtractPath( $file );

			// extract
			$zip = new ZipArchive;
			if ( $zip->open( $pathZip ) ) {
				$zip->extractTo( $pathExtract );
			}
			$zip->close();
		}
	}

	public function getDB() {
		if ( !$this->db ) {
			$this->db = new PDO( "sqlite:$this->dirpath/ean.db" );
		}

		return $this->db;
	}

	protected function createDB() {
		$db = $this->getDB();

		$db->exec( 'CREATE TABLE locations (id INTEGER PRIMARY KEY, lat FLOAT, lng FLOAT, image VARCHAR(255), stars FLOAT )' );
		$db->exec( 'CREATE TABLE language (id INTEGER, language VARCHAR(2), title VARCHAR(255), text TEXT ); CREATE INDEX id ON language (id);' );
		$db->exec( 'CREATE TABLE currency (id INTEGER, currency VARCHAR(3), price FLOAT ); CREATE INDEX id ON currency (id);' );
	}

	protected function parseAllHotels() {
		$db = $this->getDB();

		$statementLocations = $db->prepare( 'INSERT INTO locations (id, lat, lng) VALUES (:id, :lat, :lng)' );
		$statementLanguage = $db->prepare( 'INSERT INTO language (id, language, title, text) VALUES (:id, :language, :title, :text)' );
		$statementCurrency = $db->prepare( 'INSERT INTO currency (id, currency, price) VALUES (:id, :currency, :price)' );

		$file = fopen( $this->getFileName( 'allhotels' ), 'r' );
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
			// @todo: missing EUR
		}
	}

	protected function parseLanguages() {
		$db = $this->getDB();

		$statementLanguage = $db->prepare( 'INSERT INTO language (id, language, title, text) VALUES (:id, :language, :title, :text)' );

		foreach ( array( 'fr' => 'translatefr', 'nl' => 'translatenl' ) as $language => $import ) {
			$file = fopen( $this->getFileName( $import ), 'r' );
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
	}

	protected function parseStars() {
		$db = $this->getDB();

		$statementStar = $db->prepare( 'UPDATE locations SET stars = :stars WHERE id = :id' );

		$file = fopen( $this->getFileName( 'stars' ), 'r' );
		$keys = fgetcsv( $file, null, '|' );
		while ( !feof( $file ) && $column = fgetcsv( $file, null, '|' ) ) {
			$column = array_combine( $keys, $column );

			$statementStar->execute( array(
				':id' => $column['HotelID'],
				':stars' => $column['PropertyRating'] == 'NotAvailable' ? 0 : $column['PropertyRating'],
			) );
		}
	}

	protected function parseImages() {
		$db = $this->getDB();

		$statementImage = $db->prepare( 'UPDATE locations SET image = :image WHERE id = :id' );

		$file = fopen( $this->getFileName( 'images' ), 'r' );
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
	}

	protected function getZipPath( $file ) {
		return $this->dirpath . '/' . basename( $this->files[$file] );
	}

	protected function getExtractPath( $file ) {
		return $this->dirpath . '/' . $file;
	}

	protected function getFileName( $file ) {
		$pathExtract = $this->dirpath . '/' . $file;

		// map extracted file names
		$localFile = glob( "$pathExtract/*.txt" );
		return $localFile[0];
	}
}
