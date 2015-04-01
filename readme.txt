=== Tabulate ===
Contributors: samwilson
Donate link: https://www.bushheritage.org.au/donatenow
Tags: mysql, databases, tables, data, crud
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

Access can be granted to *read*, *create*, *update*, and *delete* records in
any or all tables. (This can be done by anyone with the `promote_users`
capability.)

== Installation ==

Follow the usual plugin installation procedure.

== Frequently Asked Questions ==

= How should issues be reported? =

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker: https://github.com/tabulate/tabulate/issues

= What modifications does Tabulate make to the database? =

None. Some options are created, all prefixed with `tabulate_`. These store the
granted permissions.

== Screenshots ==

1. The main screen of a single table, with provision for searching and navigating.

== Changelog ==

A full list of changes can be found at
https://github.com/tabulate/tabulate/commits/master

Prior to version 1, changes are not being logged (there are too many of them,
and nothing is stable yet).

== Upgrade Notice ==

No special action needs to be taken to upgrade. Tabulate can be deactivated and
reactivated without losing any data; if uninstalled, it will remove everything
that it's added (but you'll be warned of this, don't worry).
