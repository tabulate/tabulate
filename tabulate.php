<?php

/**
 * Plugin Name: Tabulate
 * Description: A simple user-friendly interface to tables in your database.
 * Version: 0.0.1
 * Author: Sam WIlson
 * Author URI: http://samwilson.id.au/
 * License: GPL-2.0+
 */
define('TABULATE_VERSION', '0.0.1');
require __DIR__ . '/vendor/autoload.php';
global $wpdb;

$menus = new \WordPress\Tabulate\Menus($wpdb);
$menus->init();

