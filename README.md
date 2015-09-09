# Tabulate
* Contributors: samwilson
* Donate link: https://www.bushheritage.org.au/donatenow
* Tags: MySQL, databases, tables, data, CRUD, import, export, CSV, shortcode, OpenStreetMap, KML
* Requires at least: 4.2
* Tested up to: 4.3
* Stable tag: trunk
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage relational tabular data within the WP admin area, using the full power of
your MySQL database. CSV import/export; access-control; foreign-keys.

## Description

[![Build Status](https://img.shields.io/travis/tabulate/tabulate.svg?style=flat-square)](https://travis-ci.org/tabulate/tabulate)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/tabulate/tabulate/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/tabulate/tabulate/?branch=master)
[![Total Downloads](https://img.shields.io/wordpress/plugin/dt/tabulate.svg?style=flat-square)]()
[![WordPress rating](https://img.shields.io/wordpress/plugin/r/tabulate.svg?style=flat-square)]()
[![Latest Stable Version](https://img.shields.io/wordpress/plugin/v/tabulate.svg?style=flat-square)](https://wordpress.org/plugins/tabulate)
[![WordPress version](https://img.shields.io/wordpress/v/tabulate.svg?style=flat-square)]()
[![License](https://img.shields.io/github/license/tabulate/tabulate.svg?style=flat-square)](https://github.com/tabulate/tabulate/blob/master/LICENSE.txt)

This plugin provides a simple user-friendly interface to tables in the database.
Somewhat akin to phpMyAdmin, but including only data-modification features.

Features (in no particular order):

1.  Tables can be filtered by any column or columns, and with a range of
    operators ('contains', 'is', 'empty', 'one of', 'greater than', 'less than',
    and the negations of all of these). Multiple filters are conjunctive
    (i.e. with a logical *and*).
2.  Access can be granted to *read*, *create*, *update*, *delete*, and *import*
    records in any or all tables. (This can be done by anyone with the
    *promote_users* capability.)
3.  CSV data can be imported, with the UI supporting column mapping, data
    validation, and previewing prior to final import. If an imported row has a
    value for the Primary Key, the existing row will be overwritten.
4.  Data can be exported to CSV, including after filters have been applied. 
5.  A quick-jump navigation box (located top right of every page) can be
    activated by installing the [WP REST API](https://wordpress.org/plugins/json-rest-api/)
    plugin. The quick-jump box is also added as a Dashboard widget.
6.  Records in tables with *date* columns can be viewed in a calendar.
7.  Entity Relationship Diagrams (drawn with [GraphViz](http://graphviz.org/))
    can be automatically generated, with any specified subset of tables. Foreign
    keys are displayed as directed edges. This feature is only available if the
    [TFO Graphviz plugin](https://wordpress.org/plugins/tfo-graphviz/) is installed.
8.  All data modifications are recorded, along with optional comments that users
    can provide when updating data.
9.  The `[tabulate]` shortcode can be used to embed tables, lists, row-counts,
    and data-entry forms into WordPress content. For more details, see the
    [FAQ section](https://wordpress.org/plugins/tabulate/faq/).
10. Tables with *point* columns can be exported to KML and OpenStreetMap XML.
    Also, data entry for these columns is done with a small slippy map, on which
    a marker can be placed.

Development is managed on GitHub: https://github.com/tabulate/tabulate

## Installation

### Installing

1. Follow the [usual plugin installation procedure](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).
2. To get a quick-jump navigation box, also install the
   [WP REST API](https://wordpress.org/plugins/json-rest-api/) plugin.
3. For Entity Relationship Diagram support, also install the
   [TFO Graphviz](https://wordpress.org/plugins/tfo-graphviz/) plugin.
4. Create some new database tables in your WordPress database, using a tool such
   as [PHPmyAdmin](http://www.phpmyadmin.net) or [MySQL Workbench](http://mysqlworkbench.org/).
5. Browse to the Tabulate overview page via the main menu in the WordPress admin
   interface.

### Upgrading

When upgrading, please *deactivate* and then *reactivate* the plugin. This will
ensure that all required database updates are carried out (but will avoid the
overhead of checking whether these are required on every Tabulate page load).

## Frequently Asked Questions

### How does one use the shortcode?

A [Shortcode](http://codex.wordpress.org/Shortcode) is a WordPress method of
embedding content into posts and pages. Tabulate provides one short code, `[tabulate]`,
which can be used to add tables, lists, data-entry forms, and record-counts to
your content. Its parameters (which can appear in any order) are as follows:

1. `table` — The name of the table in question. Required. No default.
2. `format` — One of `table`, `list`, `form`, or `count`. Optional. Defaults to `table`.

Do note that if a table is not accessible to the browsing user then nothing will
be displayed. (This currently means that anonymous users can not view any
Tabulate data, because there is no way to grant them access; this will be fixed
soon.)

### Where should issues be reported?

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker at https://github.com/tabulate/tabulate/issues

### What modifications does Tabulate make to the database?

Two database tables are created, and one [option](http://codex.wordpress.org/Option_Reference),
all prefixed with `tabulate_`. When Tabulate is uninstalled, all of these are
deleted (but custom tables are not touched).

### Is row-level access control possible?

This should be done by creating a [view](https://dev.mysql.com/doc/refman/5.1/en/create-view.html)
(of one or more tables) and granting access to that.

### What reasons exist for the 'failed to create *.csv' error?

If you are getting an error like "Failed to create C:\Windows\Temp\tabulate_5593a4c432a67.csv"
or "Failed to create /tmp/tabulate_5593a4c432a67.csv"
then you should

1. firstly check that your database user has the [FILE privilege](https://dev.mysql.com/doc/refman/5.7/en/privileges-provided.html#priv_file);
2. then make sure your web server user has write-access to the system temp directory;
3. and if those don't work, add the following to your `wp-config.php`:
   `define( 'WP_TEMP_DIR', ABSPATH . 'wp-content/tmp/' );` and create the `wp-content/tmp/` directory.

### Where is the developers' documentation?

For information about the development of Tabulate or integrating other plugins
with it please see
[CONTRIBUTING.md](https://github.com/tabulate/tabulate/blob/master/CONTRIBUTING.md#contributing).

## Screenshots

1. The main screen of a single table, with provision for searching and navigating.
2. The permission-granting interface. All roles are shown across the top, and
   all tables down the left side.

## Changelog

This is a chronologically ordered list of major changes to Tabulate.
A full list of all changes can be found at https://github.com/tabulate/tabulate/commits/master

* March to July 2015: Pre-release development.
* July 2015: Version 1.0.0 released, with basic functionality and after having
  been run for some months in a production environment by the plugin author.

Prior to version 1, changes were listed here (there were too many of them, and
nothing was stable yet).

## Upgrade Notice

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you will be warned before this happens, don't worry).

No custom database tables are modified during upgrade, activation, deactivation,
or uninstallation.
