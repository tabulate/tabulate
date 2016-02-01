Using the shortcode
===================

A `Shortcode`_ is a WordPress method of adding dynamic content to posts and pages.
Tabulate provides one short code, ``[tabulate]``,
which can be used to add tables, lists, data-entry forms, and record-counts to your content.
Its parameters (which can appear in any order) are as follows:

  1. ``table`` — The name of the table in question. Required. No default.
  2. ``format`` — One of ``table``, ``list``, ``form``, ``count``, or ``record``. Optional. Defaults to ``table``.
  3. ``ident`` — Used for the Record format. Optional. No default.
  4. ``search`` — Whether to display a search form for the Table format. Optional. Defaults to ``false``.

Do note that if a table is not accessible to the browsing user then nothing will be displayed.
Keep in mind that you can grant access to non-logged-in users to view tables if you wish
(via the Grants page in the Admin Area).

 .. _shortcode: http://codex.wordpress.org/Shortcode

Table format
------------

Example: ``[tabulate table=widgets search=yes]``

The table format displays an HTML-table displaying all records from the Tabulate-table specified.

The displayed data will be paginated if there are more than a certain number of records,
and the user will be able to page through the data.

If the additional parameter ``search`` is provided (and given any value at all; ``yes`` is just a convention)
then a record-filtering form will be displayed.

List format
------------

Example: ``[tabulate format=list table=widgets]``

The list format displays a comma-separated list of all of the titles of the records from the table specified.

Form format
------------

Example: ``[tabulate format=form table=widgets]``

The form format displays a data-entry form to users who have been granted access to create records in the specified table.

The form operates exactly the same as the form in the Admin Area,
except that after submission the user is sent back to a blank form in readiness for the next data-entry
(rather than shown their saved data).
A message such as "Record saved." is displayed after submission.

Count format
------------

Example: ``There are [tabulate format=count table=widgets] Widgets in our catalogue.``

The count format displays a simple integer count of the records in the given table.

This usage of the shortcode can be used inline within a sentence.

Record format
-------------

Example: ``[tabulate format=form table=widgets ident=45]``

The record format displays a single record from a table.

To specify which record to display, either provide the ``ident`` shortcode parameter,
or set a URL parameter equal to the name of the table.
For example, ``[tabulate table=widgets format=record]`` will look for ``?widgets=45``
and display the record with a primary key value of ``45``.
