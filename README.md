# Tabulate
* Contributors: samwilson
* Donate link: https://www.bushheritage.org.au/donatenow
* Tags: MySQL, databases, tables, data, CRUD, import, export, CSV, shortcode, OpenStreetMap, KML
* Requires at least: 4.7
* Tested up to: 4.9
* Requires PHP: 5.6
* Stable tag: trunk
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage relational tabular data using the full power of your MySQL database.
CSV import/export; access-control; foreign-keys; and lots more.

## Description

[![Documentation Status](https://readthedocs.org/projects/tabulate/badge/?version=latest)](http://tabulate.readthedocs.org/en/latest/?badge=latest)
[![Build Status](https://img.shields.io/travis/tabulate/tabulate.svg?style=flat-square)](https://travis-ci.org/tabulate/tabulate)
[![Total Downloads](https://img.shields.io/wordpress/plugin/dt/tabulate.svg?style=flat-square)]()
[![WordPress rating](https://img.shields.io/wordpress/plugin/r/tabulate.svg?style=flat-square)]()
[![Latest Stable Version](https://img.shields.io/wordpress/plugin/v/tabulate.svg?style=flat-square)](https://wordpress.org/plugins/tabulate)
[![WordPress version](https://img.shields.io/wordpress/v/tabulate.svg?style=flat-square)]()
[![License](https://img.shields.io/github/license/tabulate/tabulate.svg?style=flat-square)](https://github.com/tabulate/tabulate/blob/master/LICENSE.txt)
[![Scrutinizer Code Quality](https://img.shields.io/scrutinizer/g/tabulate/tabulate/master.svg?style=flat-square)](https://scrutinizer-ci.com/g/tabulate/tabulate/?branch=master)

This plugin provides a simple user-friendly interface to tables in your database.
Somewhat akin to phpMyAdmin, but easier to use and more focused on end users.

The documentation can be found at [tabulate.readthedocs.org](https://tabulate.readthedocs.org/).

Features (in no particular order):

1.  Tables can be filtered by any column or columns, and with a range of
    operators ('contains', 'is', 'empty', 'one of', 'greater than', 'less than',
    and the negations of all of these). Multiple filters are conjunctive
    (i.e. with a logical *and*).
2.  Access can be granted to *read*, *create*, *update*, *delete*, and *import*
    records in any or all tables. (This can be done by anyone with the
    *promote_users* capability.) Access can also be granted to *anonymous users*.
3.  CSV data can be imported, with the UI supporting column mapping, data
    validation, and previewing prior to final import. If an imported row has a
    value for the Primary Key, the existing row will be overwritten.
4.  Data can be exported to CSV, including after filters have been applied. 
5.  A quick-jump navigation box is located top right of every page,
    and can also be added as a Dashboard widget.
6.  Records in tables with *date* columns can be viewed in a calendar.
7.  Entity Relationship Diagrams (drawn with [GraphViz](http://graphviz.org/))
    can be automatically generated, with any specified subset of tables. Foreign
    keys are displayed as directed edges. This feature is only available if the
    [TFO Graphviz plugin](https://wordpress.org/plugins/tfo-graphviz/) is installed.
8.  All data modifications are recorded, along with optional comments that users
    can provide when updating data.
9.  The `[tabulate]` shortcode can be used to embed tables, lists, row-counts,
    and data-entry forms into WordPress content. For more details,
    [read the documentation](https://tabulate.readthedocs.org/en/latest/shortcode.html).
10. Tables with *point* columns can be exported to KML and OpenStreetMap XML.
    Also, data entry for these columns is done with a small slippy map, on which
    a marker can be placed.

Development is managed on GitHub
at [github.com/tabulate/tabulate](https://github.com/tabulate/tabulate)

## Installation

See [tabulate.readthedocs.org/en/latest/install.html](http://tabulate.readthedocs.org/en/latest/install.html)

## Frequently Asked Questions

See [tabulate.readthedocs.org/en/latest/faq.html](https://tabulate.readthedocs.org/en/latest/faq.html)

## Screenshots

1. The main screen of a single table, with provision for searching and navigating.
2. The permission-granting interface. All roles are shown across the top, and
   all tables down the left side.

## Changelog

This is a reverse-chronologically ordered list of breaking or major changes to Tabulate.
A full list of all changes can be found at (https://github.com/tabulate/tabulate/commits/master)

* November 2017: Version 2.10, removed dependency on the REST API plugin (which is not in core).
* January 2016: Version 2.5, introduced schema editing.
* October 2015: Version 2, switching to version 2 of the WP-API plugin.
* July 2015: Version 1, with basic functionality and after having
  been run for some months in a production environment by the plugin author.
* March to July 2015: Pre-release development.

Prior to version 1, no changes were listed here (there were too many of them, and
nothing was stable yet).

## Upgrade Notice

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you will be warned before this happens, don't worry).

No custom database tables are modified during upgrade, activation, deactivation,
or uninstallation.

For more information,
please see [the user manual](http://tabulate.readthedocs.org/en/latest/install.html).
