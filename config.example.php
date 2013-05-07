<?php

// db connection
$dbhost = '<host>';
$dbname = '<db>';
$dbuser = '<user>';
$dbpass = '<pass>';

$cache = array(
	'class' => 'FileCache',
	'params' => array(
		'path' => __DIR__.'/cache/cache/',
	)
);
