Installing and upgrading
========================

Installation is the same as for most WordPress plugins,
but note that you can get extra features by installing other plugins,
and upgrading requires some changes whenever the major version number increases
(e.g. 2.x.x to 3.x.x).

Install
-------

1. Install the plugin in `the usual way`_ by using your site's plugin manager.

  A. To get a quick-jump navigation box, also install the `WP REST API`_ plugin (``rest-api``).
  B. For Entity Relationship Diagram support, also install the `TFO Graphviz`_ plugin (``tfo-graphviz``).

2. Browse to the Tabulate overview page via the main menu in the WordPress admin area.
3. Create some tables. Alternatively, you can use a tool such as `PHPmyAdmin`_ or `MySQL Workbench`_.

.. _`the usual way`: http://codex.wordpress.org/Managing_Plugins#Installing_Plugins
.. _`WP REST API`: https://wordpress.org/plugins/rest-api/
.. _`TFO Graphviz`: https://wordpress.org/plugins/tfo-graphviz/
.. _`PHPmyAdmin`: http://www.phpmyadmin.net
.. _`MySQL Workbench`: http://mysqlworkbench.org/

Upgrade
-------

When upgrading, please *deactivate* and then *reactivate* the plugin.
This will ensure that all required database updates are carried out
(but will avoid the overhead of checking whether these are required on every Tabulate page load).

After version 2.0.0 you must switch to version 2 of the REST API plugin (``rest-api``).
Remove the older one (``json-rest-api``).
