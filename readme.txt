=== Tabulate ===
Contributors: samwilson
Donate link: https://www.bushheritage.org.au/donatenow
Tags: MySQL, databases, tables, data, CRUD, importing, CSV
Requires at least: 4.1
Tested up to: 4.1.1
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Tabulate is a simple user-friendly interface to tables in the database.
Somewhat akin to phpMyAdmin, but including only data-modification features.

== Description ==

This plugin provides a simple user-friendly interface to tables in the database.
Somewhat akin to phpMyAdmin, but including only data-modification features.

Access can be granted to *read*, *create*, *update*, *delete*, and *import*
records in any or all tables. (This can be done by anyone with the
`promote_users` capability.)

CSV data can be imported, with the UI supporting column mapping, data
validation, and previewing prior to final import.

A quick-jump navigation box (located top right of every page) can be activated
by installing the [WP REST API](https://wordpress.org/plugins/json-rest-api/)
plugin.

== Installation ==

Follow the usual plugin installation procedure.

To get a quick-jump navigation box, also install the
[WP REST API](https://wordpress.org/plugins/json-rest-api/) plugin.

== Frequently Asked Questions ==

= How should issues be reported? =

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker: https://github.com/tabulate/tabulate/issues

= What modifications does Tabulate make to the database? =

None. Some [options](http://codex.wordpress.org/Option_Reference) are created,
all prefixed with `tabulate_`. These store the granted permissions (one per
Tabulate grant; i.e. five so far).

== Screenshots ==

1. The main screen of a single table, with provision for searching and navigating.
2. The permission-granting interface. All roles are shown across the top, and
   all tables down the left side.

== Changelog ==

A full list of changes can be found at
https://github.com/tabulate/tabulate/commits/master

Prior to version 1, changes are not being logged (there are too many of them,
and nothing is stable yet).

== Upgrade Notice ==

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you'll be warned of this, don't worry).
