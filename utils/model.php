<?php

class Model {
	/**
	 * @var PDO
	 */
	protected static $db;

	/**
	 * @var Cache
	 */
	protected static $cache;

	/**
	 * @return PDO
	 */
	public static function getDB() {
		if ( self::$db === null ) {
			include __DIR__.'/../config.php';

			self::$db = new PDO( "mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, array( PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"' ) );
		}

		return self::$db;
	}

	/**
	 * @return Cache
	 */
	public static function getCache() {
		if ( self::$cache === null ) {
			require_once __DIR__.'/cache/cache.php';
			include __DIR__.'/../config.php';

			self::$cache = Cache::load( $cache );
		}

		return self::$cache;
	}

	/**
	 * @param string $sql
	 * @param array $params
	 * @return PDOStatement
	 */
	public static function query( $sql, array $params = null ) {
		$statement = self::getDB()->prepare( $sql );
		$statement->execute( (array) $params );
		return $statement;
	}

	/**
	 * Find all markers within given bounds and price range.
	 *
	 * @param string $currency
	 * @param int $minPrice
	 * @param int $maxPrice
	 * @param array $bounds array( 'neLat' => Y2, 'neLng' => X2, 'swLat' => Y1, 'swLng' => X1 )
	 * @param bool[optional] $spanBoundsLat Indicates the bounds span latitude 90 to -90 gap
	 * @param bool[optional] $spanBoundsLng Indicates the bounds span latitude 180 to -180 gap
	 * @return array
	 */
	public static function getMarkers( $currency, $minPrice, $maxPrice, $bounds, $spanBoundsLat = false, $spanBoundsLng = false ) {
		// there may be a lot of data
		ini_set( 'memory_limit', '1G' );

		$where = array();

		// price bounds
		$where[] = 'curr.price >= :min AND curr.price <= :max AND curr.currency = :currency';

		// rough, z-order based, position
		$ne = self::zorder( $bounds['neLat'], $bounds['neLng'] );
		$sw = self::zorder( $bounds['swLat'], $bounds['swLng'] );
		$where[] = ( $spanBoundsLat || $spanBoundsLng ) ? 'loc.zorder >= :sw OR loc.zorder < :ne' : 'loc.zorder >= :sw AND loc.zorder < :ne';

		/*
		 * Bounds will usually be part of the map where the left side of the viewport
		 * is more western and right side is more eastern. It could happen though that
		 * the right side displays a next part of the map though, starting from the west.
		 * In that case, part of the center will not be visible, only both sides, and
		 * neLat will actually be lower than swLat.
		 */
		$location = 'MBRWithin(loc.coordinate, GeomFromText(CONCAT("Polygon((", :nelat, " ", :nelng, ",", :nelat, " ", :swlng, ",", :swlat, " ", :swlng, ",", :swlat, " ", :nelng, ",", :nelat, " ", :nelng, "))")))';
		$where[] = ( $spanBoundsLat || $spanBoundsLng ) ? "!$location" : $location;

		$sql = '
			SELECT *
			FROM locations AS loc
			INNER JOIN currency AS curr ON curr.id = loc.id
			WHERE ('. implode( ') AND (', $where ) .')';

		$params =
			array(
				':min' => $minPrice,
				':max' => $maxPrice,
				':currency' => $currency,
				':ne' => $ne,
				':sw' => $sw,
				':nelat' => ( $spanBoundsLat ? $bounds['swLat'] : $bounds['neLat'] ),
				':nelng' => ( $spanBoundsLat ? $bounds['swLng'] : $bounds['neLng'] ),
				':swlat' => ( $spanBoundsLng ? $bounds['neLat'] : $bounds['swLat'] ),
				':swlng' => ( $spanBoundsLng ? $bounds['neLng'] : $bounds['swLng'] ),
			);

		return self::query( $sql, $params )->fetchAll();
	}

	/**
	 * Fetch all details.
	 *
	 * @param array $entries Format: array( array( 'feed_id' => a, 'product_id' => b ), array( 'feed_id' => x, 'product_id' => y ), ... )
	 * @param string $currency
	 * @param string $language
	 * @return array
	 */
	public static function getDetails( $feedId, $productId, $currency, $language ) {
		$sql = '
			SELECT *
			FROM locations AS loc
			INNER JOIN currency AS curr ON curr.id = loc.id
			INNER JOIN language AS lang ON lang.id = loc.id
			WHERE loc.feed_id = :feed_id AND loc.product_id = :product_id AND curr.currency = :currency
			GROUP BY loc.feed_id, loc.product_id
			ORDER BY lang.language = :language';

		$params = array(
			':feed_id' => $feedId,
			':product_id' => $productId,
			':currency' => $currency,
			':language' => $language,
		);

		return self::query( $sql, $params )->fetch();
	}

	/**
	 * Update entries for a certain feed.
	 *
	 * @param int $feedId
	 * @param Iterator $entries
	 * @param callable $callback
	 */
	public static function updateFeed( $feedId, $entries, /* callable */ $callback ) {
		// this might take awhile
		set_time_limit( 0 );

		$db = self::getDB();
		$statementLocation = $db->prepare( '
			INSERT IGNORE INTO locations (feed_id, product_id, lat, lng, coordinate, zorder, image, stars, url, url_mobile)
			VALUES (:feed_id, :product_id, :lat, :lng, GeomFromText(CONCAT("Point(", :lat, " ", :lng, ")")), :zorder, :image, :stars, :url, :url_mobile)
		' );
		$statementCurrency = $db->prepare( '
			INSERT IGNORE INTO currency (id, currency, price)
			VALUES (:id, :currency, :price)
		' );
		$statementLanguage = $db->prepare( '
			INSERT IGNORE INTO language (id, language, title, text)
			VALUES (:id, :language, :title, :text)
		' );

		$db->beginTransaction();

		// remove existing entries for this feed
		$sql = '
			DELETE loc, curr, lang
			FROM locations AS loc
			INNER JOIN currency AS curr ON curr.id = loc.id
			INNER JOIN language AS lang ON lang.id = loc.id
			WHERE loc.feed_id = :feed_id';
		self::query( $sql, array( 'feed_id' => $feedId ) );

		foreach ( $entries as $entry ) {
			// let callback parse this entry
			list( $location, $currencies, $languages ) = $callback( $entry );

			// validate data
			if ( empty( $location ) || empty( $currencies ) || empty( $languages ) ) {
				continue;
			}

			// add feed id
			$location[':feed_id'] = $feedId;

			// calculate zorder value
			$location[':zorder'] = (int) self::zorder( $location[':lat'], $location[':lng'] );

			if ( $statementLocation->execute( $location ) ) {
				$location[':id'] = $db->lastInsertId();
				if ( !$location[':id'] ) {
					continue;
				}

				foreach ( $currencies as $currency ) {
					$currency[':id'] = $location[':id'];
					$statementCurrency->execute( $currency );
				}

				foreach ( $languages as $language ) {
					$language[':id'] = $location[':id'];
					$statementLanguage->execute( $language );
				}
			}
		}

		$db->commit();

		self::getCache()->flush();
	}

	/**
	 * Log a certain action.
	 *
	 * @param string $action
	 * @param int $feedId
	 * @param mixed $productId
	 * @return bool
	 */
	public static function track( $action, $feedId, $productId ) {
		$statement = self::getDB()->prepare( '
			INSERT INTO track (action, feed_id, product_id, data, time)
			VALUES (:action, :feed_id, :product_id, :data, :time)
		' );

		return $statement->execute( array(
			':action' => $action,
			':feed_id' => $feedId,
			':product_id' => $productId,
			':data' => serialize( $_SERVER ),
			':time' => date( 'Y-m-d H:i:s' ),
		) );
	}

	/**
	 * Calculate z-order value for the given coordinate, to improve search.
	 *
	 * @param float $lat
	 * @param float $lng
	 * @return int
	 */
	protected static function zorder( $lat, $lng ) {
		// lat range -90 to 90 -> 0 to 180 & similar for lng
		$lat += 90;
		$lng += 180;

		/*
		 * Convert coordinates into an 31-bit integer (warning: rounding errors):
		 * * coordinate 0 (originally -90 or -180) will be int(0)
		 * * coordinate 180 or 360 (originally 90 or 180) will be int(2^16)
		 *
		 * We're rounding to 16-bit because we'll interleave both values later,
		 * resulting in 32-bit.
		 *
		 * FYI: 2^16 = 65536
		 */
		$lat = (int) ( $lat * 65535 / 360 );
		$lng = (int) ( $lng * 65535 / 360 );

		$zorder = 0;
		$bit = 1;
		$position = 0;

		// interleave lat & lng bits to form zorder value
		while ( $bit <= $lat || $bit <= $lng ) {
			if ( $bit & $lat ) {
				$zorder = $zorder | 1 << ( 2 * $position + 1 );
			}
			if ( $bit & $lng ) {
				$zorder = $zorder | 1 << ( 2 * $position );
			}

			$position += 1;
			$bit = 1 << $position;
		}

		return $zorder;
	}
}
