<?php

abstract class Cache {
	public static function load( $params ) {
		$class = isset( $params['class'] ) ? (string) $params['class'] : '';
		$file = strtolower( preg_replace( '/Cache/', '', $class ) ).'.php';
		$params = isset( $params['params'] ) ? (array) $params['params'] : array();

		@include_once $file;
		if ( !class_exists( $class ) ) {
			throw new Exception( "Invalid cache class'$class'." );
		}

		return new $class( $params );
	}

	abstract public function get( $key );
	abstract public function add( $key, $value, $expire );
	abstract public function set( $key, $value, $expire );
	abstract public function delete( $key );

	public function getKey( $arguments ) {
		return implode( ':', func_get_args() );
	}
}
