<?php

/**
 * Plugin Name: Tabulate
 * Description: A simple interface to any non-WP database tables.
 * Version: 0.0.1
 * Author: Sam WIlson
 * Author URI: http://samwilson.id.au/
 * License: GPL-2.0+
 */
require __DIR__ . '/vendor/autoload.php';
global $wpdb;

$menus = new \WordPress\Tabulate\Menus($wpdb);
$menus->init();

