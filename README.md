# last-minute-vakanties.be / HotelGuide

Source code for http://www.last-minute-vakanties.be

## Setup

### Instructions

* Clone this code into a folder on your local system, where <something>.dev resolves to
* Copy /config.example.php to /config.php and fill in your system's details
* Make sure /cache folder is writable to the code (in terminal: chmod -R 777 <your-folder>/cache)
* Create a database and execute the below queries to set up the schema.
* Run /import/*.php scripts to populate the database (in terminal: php <your-folder>/import/<feed-name>.php)

### SQL

        CREATE TABLE IF NOT EXISTS `locations` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `feed_id` int(2) unsigned NOT NULL,
          `product_id` varchar(255) NOT NULL,
          `lat` float NOT NULL,
          `lng` float NOT NULL,
          `coordinate` point NOT NULL,
          `zorder` int(11) unsigned NOT NULL,
          `image` varchar(255) NOT NULL,
          `stars` float NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY (`feed_id`,`product_id`),
          UNIQUE KEY (`lat`,`lng`),
          INDEX (`zorder`),
          SPATIAL KEY (`coordinate`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

        CREATE TABLE IF NOT EXISTS `currency` (
          `id` int(11) unsigned NOT NULL,
          `currency` char(3) NOT NULL,
          `price` float NOT NULL,
          PRIMARY KEY (`id`,`currency`),
          INDEX (`currency`,`price`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

        CREATE TABLE IF NOT EXISTS `language` (
          `id` int(11) unsigned NOT NULL,
          `language` char(2) NOT NULL,
          `title` varchar(255) NOT NULL,
          `text` text DEFAULT NULL,
          `url` varchar(255) NOT NULL,
          `url_mobile` varchar(255) DEFAULT NULL,
          PRIMARY KEY (`id`,`language`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

        CREATE TABLE IF NOT EXISTS `track` (
          `feed_id` int(2) NOT NULL,
          `product_id` int(11) NOT NULL,
          `action` varchar(255) DEFAULT NULL,
          `data` text NOT NULL,
          `time` DATETIME NULL
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

## Phonegap

To build the apps, we'll be using the build.phonegap.com service.
The instructions below are irrelevant to the build, but may be useful for local testing.
There's quite a few seb-specific source files that are useless to Phonegap, but I'd rather keep the codebase together.
Javascript will detect if the code is running in an app and - if so - fire all requests to the production server.

### Instructions

Download & unpack: http://phonegap.com/download/ (I'll assume you place it at ~/Sites/phonegap)

#### iOS

Download & install Xcode from Apple store
Open Xcode > Preferences > Downloads > Components. Installer Command Line Tools & simulators.

In terminal, create the Xcode project

    ~/Sites/phonegap/lib/ios/bin/create ~/Sites/hotelguide-ios us.envy.HotelGuide HotelGuide

In terminal, copy source code into Xcode project

    git clone git@github.com:matthiasmullie/hotelguide.git /tmp/hotelguide --depth 1 && rsync -a /tmp/hotelguide/ ~/Sites/hotelguide-ios/www/ && rm -rf /tmp/hotelguide/

Open what we just created in Xcode

    open -a Xcode ~/Sites/hotelguide-ios/HotelGuide.xcodeproj

Top left, choose your target (device, simulator) & click "Run".

#### Android

See http://docs.phonegap.com/en/2.6.0/guide_getting-started_android_index.md.html and figure it out :)
