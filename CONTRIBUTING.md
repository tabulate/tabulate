# Contributing

This file is for any notes aimed at developers of Tabulate.

## Revision control

We develop in Git, using Github at https://github.com/tabulate/tabulate

Stable tags are committed to the Subversion repository, which is only used as a
deployment system: trunk is stable, the `branches` directory is not used, and
the `tags` directory is a mirror of the Git tags.

## Version numbers

Tabulate follows the Semantic Versioning guidelines: http://semver.org/

The version number is hard-coded at two places in `tabulate.php`. It would also
be in `README.md` but trunk is stable so there's no need to specify a stable
tag.

The number of the highest version of WordPress that we test against is
hard-coded in `README.md` and the Travis build file `.travis.yml`.

## Ideas for future development

Some possibilities:

* Map display, where there is spacial data in a table.
* An audit trail, logging all modifications.
* File storage, for blob types.
* Bulk changes, for modifying filtered data.
* LaTeX output.
* Tree/hierarchy display, for self-referencing foreign keys.
* A cross-platform mobile app, that can connect to a Tabulate site and create
  records.

There may be more listed in the issue queue on Github:
https://github.com/tabulate/tabulate/issues
