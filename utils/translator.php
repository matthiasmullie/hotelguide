<?php

require_once 'cache/cache.php';
include '../config.php';

class Translator {
	/**
	 * @var string
	 */
	protected $from, $to;

	/**
	 * #var Cache
	 */
	protected $cache;

	/**
	 * @param string[optional] $from
	 * @param string[optional] $to
	 */
	public function __construct( $from = null, $to = null ) {
		if ( $from ) {
			$this->setFrom( $from );
		}
		if ( $to ) {
			$this->setTo( $to );
		}

		include '../config.php'; // here's where $cache comes from
		$this->cache = Cache::load( $cache );
	}

	/**
	 * Set language that source data will be provided in.
	 *
	 * @param string $from
	 */
	public function setFrom( $from ) {
		// ISO 639-1
		if ( strpos( $from, '-' ) !== false ) {
			$from = substr( $from, 0, 2 );
		} elseif ( strpos( $from, '_' ) !== false ) {
			$from = substr( $from, -2 );
		}
		$this->from = strtolower( substr( $from, -2 ) );
	}

	/**
	 * Set language that text should be translated to.
	 *
	 * @param string $to
	 */
	public function setTo( $to ) {
		// ISO 639-1
		if ( strpos( $to, '-' ) !== false ) {
			$to = substr( $to, 0, 2 );
		} elseif ( strpos( $to, '_' ) !== false ) {
			$to = substr( $to, -2 );
		}
		$this->to = strtolower( substr( $to, -2 ) );
	}

	/**
	 * Translate a piece of text
	 *
	 * @param string $text
	 * @return string|bool $text translated text or boolean on failure
	 */
	public function translate( $text ) {
		// validate from & to
		if ( !$this->from || !$this->to ) {
			throw new Exception( 'Languages (from: "'. $this->from .'", to: "' . $this->to .'") not accurately set.' );
		}

		// same language, no need to translate
		if ( $this->from == $this->to ) {
			return $text;
		}

		// trim to 500 chars
		$text = strip_tags( $text );
		if ( strlen( $text) > 500 ) {
			$text = substr( $text, 0, 500 );
			$text = substr( $text, 0, strrpos( $text, ' ' ) ) . '…';
		}

		// check if already-translated result is in cache
		$key = $this->cache->getKey( 'translate', md5( $text ), $this->from, $this->to );
		$cache = $this->cache->get( $key );
		if ( $cache !== false ) {
			return $cache;
		}

		$curl = curl_init();
		curl_setopt_array( $curl, array(
			CURLOPT_URL => 'http://mymemory.translated.net/api/get?' . http_build_query( array(
				'q' => $text,
				'langpair' => $this->from . '|' . $this->to,
				'of' => 'json',
				'mt' => 1, // machine translation
				'de' => 'mymemory@last-minute-vakanties.be', // point of contact
				'ip' => $_SERVER['REMOTE_ADDR'], // visitor's IP
			) ),
			CURLOPT_HEADER => false,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );

		$response = json_decode( $response );
		if ( !$response || !isset( $response->responseStatus ) || $response->responseStatus != 200 ) {
			// failure
			return false;
		}

		$translation = $response->responseData->translatedText;

		// cache data
		$this->cache->set( $key, $translation, strtotime( '1 month' ) );

		return $translation;
	}
}
