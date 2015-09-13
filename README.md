# Wordpress Deploy DatabaseSync

A library for syncing Wordpress databases.  Can be used as part of a deployment
system.

This project is meant to be a composable component. It does one thing, sync
Wordpress databases. If you want to do more, as part of a deployment system,
then check out the other projects in the `Wordpress\Deploy` namespace.

## Dependencies

* The `mysql` linux command must be available via the command-line, on the source
and the destination machines.  You'll get a `RuntimeException` if you try to call
`DatabaseSync::sync` without it.

* The `mysqldump` linux command must be available via the command-line, on the
source and the destination machines.  You'll get a `RuntimeException` if you
try to call `DatabaseSync::sync` without it.

* The `gzip` linux command must be available via the command-line, on the source
and the destination machines.  You'll get a `RuntimeException` if you try to call
`DatabaseSync::sync` without it.

* All other dependencies are defined in `composer.json`.

## Install

```
curl -s http://getcomposer.org/installer | php
php composer.phar require cullylarson/wp-deploy-database-sync
```