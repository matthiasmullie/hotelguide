last-minute-vakanties.be
========================

Source code for http://www.last-minute-vakanties.be

Setup
=====

* Clone this code into a folder on your local system, where <something>.dev resolves to
* Copy /config.example.php to /config.php and fill in your system's details
* Make sure /cache folder is writable to the code (or configure memcached in config.php)
* Create a database and execute the below queries to set up the schema.
* Run /import/*.php scripts to populate the database

        CREATE TABLE IF NOT EXISTS `locations` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `feed_id` int(11) unsigned NOT NULL,
          `product_id` varchar(255) NOT NULL,
          `lat` float NOT NULL,
          `lng` float NOT NULL,
          `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `text` text COLLATE utf8_unicode_ci NOT NULL,
          `text_language` char(2) COLLATE utf8_unicode_ci NOT NULL,
          `image` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `stars` float NOT NULL,
          `price` float NOT NULL,
          `price_currency` char(3) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `coordinates` (`lat`,`lng`),
          INDEX `price` (`price`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

        CREATE TABLE IF NOT EXISTS `track` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `feed_id` int(11) NOT NULL,
          `location_id` int(11) NOT NULL,
          `data` text COLLATE utf8_unicode_ci NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

Phonegap
========

This source should be Phonegap-compatible: the folder can just be copy-pasted into a Phonegap project.
There's quite a few files that are useless to Phonegap, but I'd rather keep the codebase together.
Javascript will detect if the code is running in an app and - if so - fire all requests to the production server.
