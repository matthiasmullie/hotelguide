<?php

// db connection
$host = '<host>';
$db = '<db>';
$user = '<user>';
$pass = '<pass>';
$db = new PDO('mysql:host=' . $host . ';dbname=' . $db, $user, $pass, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''));

$cache = array(
	'class' => 'FileCache',
	'params' => array(
		'path' => __DIR__.'/cache/',
	)
);
