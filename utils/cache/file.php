<?php

require_once __DIR__.'/cache.php';

class FileCache extends Cache {
	protected $path = null;

	public function __construct( $params ) {
		if ( !isset( $params['path'] ) ) {
			throw new Exception( 'Missing contructor params for '.get_class() );
		}

		$this->path = $params['path'];
	}

	public function get( $key ) {
		$file = $this->getFile( $key );
		if ( file_exists( $file ) ) {
			// in $file, there'll be the vars $expire & $value
			$expire = null;
			$value = null;
			include( $file );

			// if data is expired, remove key
			if ( $expire !== 0 && $expire <= time() ) {
				$this->delete( $key );
			}

			return unserialize( $value );
		}

		return false;
	}

	public function add( $key, $value, $expire = 0 ) {
		$file = $this->getFile( $key );

		// add doesn't overwrite existing
		if ( !file_exists( $file ) ) {
			return $this->set( $key, $value, $expire );
		}

		return false;
	}

	public function set( $key, $value, $expire = 0 ) {
		$file = $this->getFile( $key );

		if ( $expire !== 0 ) {
			$expire = time() + $expire;
		}

		$value = var_export( serialize( $value ), true );
		file_put_contents( $file, "<?php\n\$expire=$expire;\n\$value=$value;\n" );
		return true;
	}

	public function delete( $key ) {
		$file = $this->getFile( $key );
		return !file_exists( $file ) || unlink( $file );
	}

	public function flush() {
		if ( !file_exists( $this->path ) || !is_dir( $this->path ) ) {
			throw new Exception( "Invalid cache path '$this->path'.");
		}

		$success = true;

		$dir = opendir( $this->path );
		while ( false !== ( $filename = readdir( $dir ) ) ) {
			$success &= @unlink( $this->path .'/'. $filename );
		}

		return $success;
	}

	protected function getFile( $key ) {
		if ( !file_exists( $this->path ) || !is_dir( $this->path ) ) {
			throw new Exception( "Invalid cache path '$this->path'.");
		}

		return $this->path.'/'.$key.'.php';
	}
}
