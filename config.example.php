<?php

// db connection
$host = '<host>';
$db = '<db>';
$user = '<user>';
$pass = '<pass>';

$cache = array(
	'class' => 'FileCache',
	'params' => array(
		'path' => __DIR__.'/cache/',
	)
);
