<?php

require_once __DIR__.'/cache.php';

class MemcachedCache extends Cache {
	protected $client = null;

	public function __construct( $params ) {
		if ( !isset( $params['host'] ) || !isset( $params['host'] ) ) {
			throw new Exception( 'Missing contructor params for '.get_class() );
		}

		$this->client = new Memcached;
		$this->client->addServer( $params['host'], $params['port'] );
	}

	public function get( $key ) {
		$value = $this->client->get( $key );
		if ( $value ) {
			$value = unserialize( $value );
		}

		return $value;
	}

	public function add( $key, $value, $expire = 0 ) {
		$value = serialize( $value );
		return $this->client->add( $key, $value, $expire );
	}

	public function set( $key, $value, $expire = 0 ) {
		$value = serialize( $value );
		return $this->client->set( $key, $value, $expire );
	}

	public function delete( $key ) {
		return $this->client->delete( $key );
	}

	public function flush() {
		return $this->client->flush();
	}
}
