# Tabulate
* Contributors: samwilson
* Donate link: https://www.bushheritage.org.au/donatenow
* Tags: MySQL, databases, tables, data, CRUD, importing, CSV
* Requires at least: 4.2
* Tested up to: 4.2
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

1. Tables can be filtered by any column or columns, and with a range of
   operators ('contains', 'is', 'empty', 'greater than', 'less than', and the
   negations of all of these). Multiple filters are conjunctive (i.e. with a
   logical *and*).
1. Access can be granted to *read*, *create*, *update*, *delete*, and *import*
   records in any or all tables. (This can be done by anyone with the
   *promote_users* capability.)
2. CSV data can be imported, with the UI supporting column mapping, data
   validation, and previewing prior to final import.
3. Data can be exported to CSV, including after filters have been applied. 
4. A quick-jump navigation box (located top right of every page) can be
   activated by installing the [WP REST API](https://wordpress.org/plugins/json-rest-api/)
   plugin.
5. Records in tables with *date* columns can be viewed in a calendar.
6. Entity Relationship Diagrams (drawn with [GraphViz](http://graphviz.org/))
   can be automatically generated, with any specified subset of tables. Foreign
   keys are displayed as directed edges. This feature is only available if the
   [TFO Graphviz plugin](https://wordpress.org/plugins/tfo-graphviz/) is installed.

## Installation

1. Follow the [usual plugin installation procedure](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).
2. To get a quick-jump navigation box, also install the
   [WP REST API](https://wordpress.org/plugins/json-rest-api/) plugin.
3. For Entity Relationship Diagram support, also install the
   [TFO Graphviz](https://wordpress.org/plugins/tfo-graphviz/) plugin.
4. Create some new database tables in your WordPress database, using a tool such
   as [PHPmyAdmin](http://www.phpmyadmin.net) or [MySQL Workbench](http://mysqlworkbench.org/).
5. Browse to the Tabulate overview page via the main menu in the WordPress admin
   interface.

## Frequently Asked Questions

### How should issues be reported?

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker: https://github.com/tabulate/tabulate/issues

### What modifications does Tabulate make to the database?

None. Some [options](http://codex.wordpress.org/Option_Reference) are created,
all prefixed with `tabulate_`. These store the granted permissions (one per
Tabulate grant; i.e. five so far).

### Is row-level access control possible?

This should be done by creating a [view](https://dev.mysql.com/doc/refman/5.1/en/create-view.html)
(of one or more tables) and granting access to that.

## Screenshots

1. The main screen of a single table, with provision for searching and navigating.
2. The permission-granting interface. All roles are shown across the top, and
   all tables down the left side.

## Changelog

A full list of changes can be found at
https://github.com/tabulate/tabulate/commits/master

Prior to version 1, changes are not being lsited here (there are too many of
them, and nothing is stable yet).

## Upgrade Notice

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you'll be warned of this, don't worry).
