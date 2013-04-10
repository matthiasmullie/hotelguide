last-minute-vakanties.be
========================

Source code for http://www.last-minute-vakanties.be

Setup
=====

* Clone this code into a folder on your local system, where <something>.dev resolves to
* Copy /server/config.example.php to /server/config.php and fill in your system's details
* Make sure /server/cache folder is writable to our code
* Create a database and execute the below query to set up the schema.
* Run /server/import/*.php scripts to populate the database

        CREATE TABLE IF NOT EXISTS `locations` (
          `feed_id` int(11) NOT NULL,
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `lat` float NOT NULL,
          `lng` float NOT NULL,
          `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `text` text COLLATE utf8_unicode_ci NOT NULL,
          `image` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
          `stars` float NOT NULL,
          `price` float NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `coordinates` (`lat`,`lng`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

Phonegap
========

This source should be Phonegap-compatible: the folder can just be copy-pasted into a Phonegap project.
All serverside scripts are bundled in the /server/ folder, and will only run on a webserver.
Javascript will detect if the code is running in an app and - if so - fire all requests to the production server.
