Frequenly Asked Questions
=========================

Where is the documentation?
---------------------------

Tabulate documentation is hosted on *Read The Docs* at http://tabulate.readthedocs.org/

Where should issues be reported?
--------------------------------

Please log all bugs, feature requests, and other issues in the GitHub issue
tracker at https://github.com/tabulate/tabulate/issues

What modifications does Tabulate make to the database?
------------------------------------------------------

Two database tables are created, and one option_, all prefixed with `tabulate_`.
When Tabulate is uninstalled, all of these are deleted (but custom tables are not touched).

.. _option: http://codex.wordpress.org/Option_Reference

Is row-level access control possible?
-------------------------------------

This should be done by creating a view_ (of one or more tables) and granting access to that.

.. _view: https://dev.mysql.com/doc/refman/5.7/en/create-view.html

What reasons exist for the 'Unable to create temporary export file' error?
--------------------------------------------------------------------------

If you are getting an error like "Unable to create temporary export file: ``C:\Windows\Temp\tabulate_5593a4c432a67.csv``"
or "Unable to create temporary export file: ``/tmp/tabulate_5593a4c432a67.csv``"
then you should firstly turn on `debug mode`_ and re-run the export to see if you get more information in the error message,
and then:

#. check that your database user has the `FILE privilege`_.
#. make sure your MySQL server user has write-access to the directory to which the CSV files are written,
   and your web server user has read access to the same directory.
#. If those don't work, you can change WordPress's temporary-files' directory
   by creating the ``wp-content/tmp/`` directory and adding the following to your ``wp-config.php``:
   ``define( 'WP_TEMP_DIR', ABSPATH . 'wp-content/tmp/' );``.

.. _debug mode: https://codex.wordpress.org/Debugging_in_WordPress
.. _FILE privilege: https://dev.mysql.com/doc/refman/5.7/en/privileges-provided.html#priv_file

Note that the MySQL server may be running with the ``--secure-file-priv`` variable_
(you can check this with ``SHOW VARIABLES LIKE "secure_file_priv"``), and in this
case the web server also needs to be able to read files from that same directory.
It is probably better to *disable* secure-file-priv (by setting it to an empty value)
rather than setting it to a widely-readable directory (such as ``/tmp``).

.. _variable: https://dev.mysql.com/doc/refman/5.7/en/server-system-variables.html#sysvar_secure_file_priv

Where is the developers' documentation?
---------------------------------------

For information about the development of Tabulate or integrating other plugins with it please see
`CONTRIBUTING.md`_ on GitHub.

.. _CONTRIBUTING.md: https://github.com/tabulate/tabulate/blob/master/CONTRIBUTING.md#contributing
