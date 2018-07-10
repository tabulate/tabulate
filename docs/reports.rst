Reports
=======

Reports in Tabulate are a way of combining your data with templates
to produce outputs in the most flexible way possible.
This means that you can display your data in non-tabular forms,
such as HTML, LaTeX, or even GPX.

A report comprises basically
a title,
a set of source SQL statements,
and an output template written in the Twig_ templating language.

.. _Twig: https://twig.symfony.com/

By default, all reports are listed in a report named *Reports*,
which also serves as an example for how to create other reports.

Example: Ordered HTML list
--------------------------

For a ``widgets`` table that has a ``name`` field, create a Report with the following template:

.. code-block:: html

    <h2>Widgets</h2>
    <ol>
    {% for widget in widgets %}
        <li>{{widget.name}}</li>
    {% endfor %}
    </ul>

Attach this SQL as a Report Source:

.. code-block:: sql

    SELECT `name` FROM `widgets` ORDER BY `name`;
