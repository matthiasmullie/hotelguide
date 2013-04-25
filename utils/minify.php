<?php

/*
 * After executing our request, this will read the response, find CSS/JS files referenced there,
 * and minify them. The minifier can be installed by running composer install.
 */

// get outputted contents
$content = ob_get_clean();
$dirname = dirname( $_SERVER['SCRIPT_FILENAME'] );

// include minifier (and check if exists; if not installed, we should not attempt to minify)
$minifier = @include 'vendor/matthiasmullie/minify/Minify.php';
if ( !$minifier ) {
	exit( $content );
}
include 'vendor/matthiasmullie/minify/Exception.php';
use MatthiasMullie\Minify;

$replace = array();

// find JS files
if ( preg_match_all( '/src=(["\'])(.+?\.js).*\\1/', $content, $js ) ) {
	include 'vendor/matthiasmullie/minify/JS.php';

	foreach ( $js[2] as $i => $file ) {
		$cacheFile = '/cache/js/'.basename( $file );
		$cachePath = str_replace( '//', '/', $dirname.'/'.$cacheFile );
		$path = str_replace( '//', '/', $dirname.'/'.$file );

		// check if original file exists & is local file
		if ( !file_exists( $path ) ) {
			continue;
		}

		// minify if cache file does not yet exist or is older than source file
		if ( !file_exists( $cachePath ) || filemtime( $cachePath ) < filemtime( $path ) ) {
			$minifier = new Minify\JS( $path );
			$minifier->minify( $cachePath );
		}

		// add new (cache) file path, to replace the original reference in the output
		$replace[$js[0][$i]] = str_replace( $file, $cacheFile.'?t='.filemtime( $cachePath ), $js[0][$i] );
	}
}

// find CSS files
if ( preg_match_all( '/href=(["\'])(.+?\.css).*\\1/', $content, $css ) ) {
	include 'vendor/matthiasmullie/minify/CSS.php';

	foreach ( $css[2] as $i => $file ) {
		$cacheFile = '/cache/css/'.basename( $file );
		$cachePath = str_replace( '//', '/', $dirname.'/'.$cacheFile );
		$path = str_replace( '//', '/', $dirname.'/'.$file );

		// check if original file exists & is local file
		if ( !file_exists( $path ) ) {
			continue;
		}

		// minify if cache file does not yet exist or is older than source file
		if ( !file_exists( $cachePath ) || filemtime( $cachePath ) < filemtime( $path ) ) {
			$minifier = new Minify\CSS( $path );
			$minifier->minify( $cachePath );
		}

		// add new (cache) file path, to replace the original reference in the output
		$replace[$css[0][$i]] = str_replace( $file, $cacheFile.'?t='.filemtime( $cachePath ), $css[0][$i] );
	}
}

$content = str_replace( array_keys( $replace ), array_values( $replace ), $content );
exit( $content );
