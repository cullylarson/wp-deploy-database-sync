# Wordpress Deploy DatabaseSync

A library for syncing Wordpress databases.  Supports syncs to and from local
and remote machines, and even remote to remote syncs. Useful as part of a
deployment system.

This project is meant to be a composable component. It does one thing, sync
Wordpress databases. If you want to do more, as part of a deployment strategy,
then check out the other projects in the `Wordpress\Deploy` namespace.

Sync's are performed using an SSH connection, not via a remote `mysql` command
connection. So, the connection remains secure, and you don't have to open up
the database port to the world.

## Dependencies
    
* The following command-line commands must be available on the source
machine. You'll get a `RuntimeException` if they aren't.
    * `mysqldump`
    * `gzip`

* The following command-line commands must be available on the destination
machine. You'll get a `RuntimeException` if they aren't.
    * `mysql`
    * `gunzip`
    * `php`
    
* [interconnectit/Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB)
must be installed on the Destination machine, if you intend to do search and replace.
The `interconnectit/search-replace-db/srdb.cli.php` command-line script in that library
is used to do the actual search and replace in the database.

* All other dependencies are defined in `composer.json`.

## Install

```
curl -s http://getcomposer.org/installer | php
php composer.phar require cullylarson/wp-deploy-database-sync
```

## Usage

You'll do everything using the `Wordpress\Deploy\DatabaseSync::sync` function.

### Constructor Options

The `Wordpress\Deploy\DatabaseSync` constructor takes an array of options. The options
you provide will depend on the type of sync you want to perform (i.e. local to local,
local to remote, remote to local, or remote to remote). If you were to provide all of the
options, it would look like this:

```
[
    'source' => [
        'tmp' => "",
        'local' => false,
        'ssh' => null,
        'keep_dump' => false,
        'db' => [
            'host' => "",
            'username' => "",
            'password' => "",
            'name' => "",
            'port' => "",
        ],
    ],
    'dest' => [
        'tmp' => "",
        'srdb' => "",
        'local' => false,
        'ssh' => null,
        'keep_dump' => false,
        'db' => [
            'host' => "",
            'username' => "",
            'password' => "",
            'name' => "",
            'port' => "",
        ]
    ],
    'local_tmp' => "",
    'search_replace' => []
]
```

The definition of these options is as follows:

* **local_tmp** (string) (semi-required)_ Required for remote to remote syncs. When
a remote to remote sync is performed, a dump file is copied from the source machine
to the local machine, and then from the local machine to the destination machine.
To do this, a folder for temporary files is used on the local machine.

* **search_replace** (array) (optional, default:[]) If you want to do a search and
replace on the data in the database, provide it here.  The keys of this array are
the text that will be searched for, and the values are what the search text will
be replaced with. The search and replace is done in such a way as to preserve
serialized data, since Wordpres is stupid and uses a crap-ton of serialized data.

* __source__ and __dest__ _(array) (required)_ These define options for the source
and destination machines you will be syncing between.  Each of these options is an
associative array with the following values:
    * __tmp__ _(string, required)_ Path to a folder where temporary files can
    be stored on this machine. If this is a local to local sync, the _tmp_ can
    be the same for the source and the dest, they won't clash with each other.
    * __srdb__ _(string) (semi-required for 'dest')_ Path to the `srdb.cli.php` file on
    the `dest` machine. This is only required if you want to do search and replace.
    * __local__ _(boolean) (required)_ Defines whether this machine is local (e.g. the
    machine you want to sync to or from). This cannot be true if you provide a value
    for __ssh__. You must provide this value, even if it's set to false (i.e you must
    explicitly define the machine as local).
    * __ssh__ _(resource|null) (optional, default:null)_ An SSH connection resource
    (c.f. [ssh2_connect](ssh2_connect)) if this machine is remote, or null if it's
    local. If you provide a connection resource, you must set __local__ to _false_.
    * **keep_dump** _(boolean) (optional, default:false_ If true, the mysql dump
    file generated while performing the sync will be left on this machine. So, if
    this is the source machine, the dump from this machine will stay in the __tmp__
    folder.  And if this is the dest machine, the dump file from _source_ machine
    will be kept in the destination's __tmp__ folder. _NOTE: at no point is a dump
    generated for the destination machine, so this is not a backup of the destination._
    * __db__ _(array) (required)_ Info. about the database on this machine.
        * __host__ _(string) (required)_ The host, as viewed by the source or dest
        machine itself. In other words, if the dest machine thinks the host is
        'localhost', you can use 'localhost' here. This is because the dump and
        import are done on the machine's themselves, via SSH.
        * __username__ _(string) (required)_
        * __password__ _(string) (required)_
        * __name__ _(string) (required)_ The name of the database.
        * __port__ _(string) (optional, default: 3306)_
    
### Sync

The sync itself is performed by the `Wordpress\Deploy\DatabaseSync::sync` function.
When this function is called, the following will happen:

1. The database is dumped to a file in the __tmp__ folder on the source machine.
2. The dump file is copied to the __tmp__ folder on the destination.  If this is
a remote to remote sync, then the following extra steps are taken:
    1. The dump file is copied from the source machine to the local machine's **local_tmp**
    folder.
    2. The dump file is copied from the local machine to the destination machine.
    3. The dump file is removed from the local machine.
3. The dump file is removed from the source, unless the **keep_dump** option is set to
_true_ in the source machine options.
4. The dump file is imported into the destination machine's database. NOTE: This will
completely overwrite the destination database.
5. The dump file is removed from the destination machine, unless the **keep_dump** option
is set to _true_ on the dest machine options.
6. If the **search_replace** option is provided, then the text from each key in this array
will be replaced with the text from the corresponding value in the array. E.g.:

    ```
    $search_replace = [
        "look for this" => "replace with this",
        "and look for this" => "so it can be replaced with this",
        "localhost:8080" => "productionurl.com",
    ];
    ```
    
Since the transfer is done using the `mysqldump` and `mysql` commands on the
source and dest machines, no remote `mysql` command connection is made. Everything
is done over SSH.
    
The `Wordpress\Deploy\DatabaseSync::sync` function can optionally accept a callback
function.  This callback will be called whenever the sync function wants to post
a status update (e.g. "I'm running", "Here's the output of the rsync command",
"Something went wrong", etc.).  It allows you to have some control over whether
and how messages are handled.

The callback must take one parameter, an instance of `Wordpress\Deploy\DatabaseSync\Status`.
An example is below.

## Examples

### Create SSH Connections

The following code could be used to create SSH connections for some of the examples below:

```
<?php

$sourceSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa'));
ssh2_auth_agent($sourceSsh, "my_username");

$destSsh = ssh2_connect("localhost", 22, array('hostkey'=>'ssh-rsa'));
ssh2_auth_agent($destSsh, "my_username");
```

NOTE:  If you're using RSA for the examples below, and you get an auth error,
you might need to run this command:
       
    $ eval `ssh-agent -s` && ssh-add

### Local to Local Sync

```
<?php

use Wordpress\Deploy\DatabaseSync;

$options = [
   'source' => [
       'tmp' => "/tmp",
       'local' => true,
       'db' => [
           'host' => "localhost",
           'username' => "source_db_username",
           'password' => "source_db_password",
           'name' => "source_db_name",
       ],
   ],
   'dest' => [
       'tmp' => "/tmp",
       'srdb' => "path/to/interconnectit/search-replace-db/srdb.cli.php",
       'local' => true,
       'db' => [
           'host' => "localhost",
           'username' => "dest_db_username",
           'password' => "dest_db_password",
           'name' => "dest_db_name",
       ]
   ],
];

$dbSync = new DatabaseSync($options);
$success = $dbSync->sync();
```

### Local to Remote Sync

```
<?php

use Wordpress\Deploy\DatabaseSync;

$options = [
   'source' => [
       'tmp' => "/tmp",
       'local' => true,
       'db' => [
           'host' => "localhost",
           'username' => "source_db_username",
           'password' => "source_db_password",
           'name' => "source_db_name",
       ],
   ],
   'dest' => [
       'tmp' => "/tmp",
       'srdb' => "path/to/dest/interconnectit/search-replace-db/srdb.cli.php",
       // notice that the only thing that makes this remote is that
       // 'local' is false, and 'ssh' is a resource.
       'local' => false,
       'ssh' => $destSsh,
       'db' => [
           // this is the localhost, as viewed from the destination machine
           'host' => "localhost",
           'username' => "dest_db_username",
           'password' => "dest_db_password",
           'name' => "dest_db_name",
       ]
   ],
];

$dbSync = new DatabaseSync($options);
$success = $dbSync->sync();
```

### Remote to Local

```
<?php

use Wordpress\Deploy\DatabaseSync;

$options = [
   'source' => [
       'tmp' => "/tmp",
       'local' => false,
       'ssh' => $sourceSsh,
       'db' => [
           // this is the localhost, as viewed from the source machine
           'host' => "localhost",
           'username' => "source_db_username",
           'password' => "source_db_password",
           'name' => "source_db_name",
       ]
   ],
   'dest' => [
       'tmp' => "/tmp",
       'srdb' => "path/to/interconnectit/search-replace-db/srdb.cli.php",
       // this is the only indicator that this is a local destination
       'local' => true,
       'db' => [
           'host' => "localhost",
           'username' => "dest_db_username",
           'password' => "dest_db_password",
           'name' => "dest_db_name",
       ],
   ],
];

$dbSync = new DatabaseSync($options);
$success = $dbSync->sync();
```

### Remote to Remote

```
<?php

use Wordpress\Deploy\DatabaseSync;

$options = [
   'source' => [
       'tmp' => "/tmp",
       'local' => false,
       'ssh' => $sourceSsh,
       'db' => [
           // this is the localhost, as viewed from the source machine
           'host' => "localhost",
           'username' => "source_db_username",
           'password' => "source_db_password",
           'name' => "source_db_name",
       ]
   ],
   'dest' => [
        'tmp' => "/tmp",
        'srdb' => "path/to/dest/interconnectit/search-replace-db/srdb.cli.php",
        'local' => false,
        'ssh' => $destSsh,
        'db' => [
            // this is the localhost, as viewed from the destination machine
            'host' => "localhost",
            'username' => "dest_db_username",
            'password' => "dest_db_password",
            'name' => "dest_db_name",
        ]
   ],
];

$dbSync = new DatabaseSync($options);
$success = $dbSync->sync();
```

### Status Callback

You can provide a callback function to `sync` and get status messsages (e.g "Dumping
the database", "Transferring the file", etc.).  Here's an example:

```
<?php

use Wordpress\Deploy\DatabaseSync;
use Wordpress\Deploy\DatabaseSync\Status;

$statusCallback = function(Status $status) {
    echo $status->Timestamp . " -- ";
    
    if( $status->isError() ) echo "ERROR: ";
    if( $status->isWarning() ) echo "WARNING: ";
    if( $status->isRawOutput() ) echo "================\n";
    
    echo $status->Message;
    
    if( $status->isRawOutput() ) echo "================\n";
}

$options = [...];
$dbSync = new DatabaseSync($options);
$dbSync->sync($statusCallback);
```