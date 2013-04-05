last-minute-vakanties.be
========================

Source code for http://www.last-minute-vakanties.be

Setup
=====

* Clone this code into a folder on your local system, where <something>.dev resolves to
* Copy config.example.php to config.php and fill in your system's details
* Make sure /cache folder is writable to our code
* Create a database and execute the below query to set up the schema.
* Run /import/*.php scripts to populate the database

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
