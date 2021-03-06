<?php

require_once __DIR__.'/model.php';

class Translator {
	/**
	 * @var string
	 */
	protected $from, $to;

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
	}

	/**
	 * Set language that source data will be provided in.
	 *
	 * @param string $from
	 */
	public function setFrom( $from ) {
		// ISO 639-1
		$this->from = strtolower( substr( $from, 0, 2 ) );
	}

	/**
	 * Set language that text should be translated to.
	 *
	 * @param string $to
	 */
	public function setTo( $to ) {
		// ISO 639-1
		$this->to = strtolower( substr( $to, 0, 2 ) );
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
		$key = Model::getCache()->getKey( 'translate', md5( $text ), $this->from, $this->to );
		$cache = Model::getCache()->get( $key );
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
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 700 // don't wait forever
		) );
		$response = curl_exec( $curl );
		curl_close( $curl );

		$response = json_decode( $response );
		// failure
		if ( !$response || !isset( $response->responseStatus ) || $response->responseStatus != 200 ) {
			return false;
		}

		$translation = $response->responseData->translatedText;

		// cache data
		Model::getCache()->set( $key, $translation, strtotime( '1 month' ) );

		return $translation;
	}
}
