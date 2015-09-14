<?php

/*
 * Composer Autoloader
 */

call_user_func(function() {
    $autoloadPaths = array(
        __DIR__ . '/../../../autoload.php',  // composer dependency
        __DIR__ . '/../vendor/autoload.php', // stand-alone package
    );

    foreach($autoloadPaths as $path) {
        if(file_exists($path)) {
            require($path);
            break;
        }
    }

    // if we didn't find the autoloader, your autoloader is in a stupid place,
    // and you're on your own
});

/*
 * Config
 */

call_user_func(function() {
    $envParams = [
        "SSH_USER",
        "SSH_HOST",
        "SSH_PORT",

        "REMOTE_SOURCE_TMP",
        "REMOTE_SOURCE_SRDB",
        "REMOTE_SOURCE_MYSQL_HOST",
        "REMOTE_SOURCE_MYSQL_USER",
        "REMOTE_SOURCE_MYSQL_PASS",
        "REMOTE_SOURCE_MYSQL_PORT",
        "REMOTE_SOURCE_MYSQL_NAME",

        "REMOTE_DEST_TMP",
        "REMOTE_DEST_SRDB",
        "REMOTE_DEST_MYSQL_HOST",
        "REMOTE_DEST_MYSQL_USER",
        "REMOTE_DEST_MYSQL_PASS",
        "REMOTE_DEST_MYSQL_PORT",
        "REMOTE_DEST_MYSQL_NAME",

        "LOCAL_SOURCE_TMP",
        "LOCAL_SOURCE_SRDB",
        "LOCAL_SOURCE_MYSQL_HOST",
        "LOCAL_SOURCE_MYSQL_USER",
        "LOCAL_SOURCE_MYSQL_PASS",
        "LOCAL_SOURCE_MYSQL_PORT",
        "LOCAL_SOURCE_MYSQL_NAME",

        "LOCAL_DEST_TMP",
        "LOCAL_DEST_SRDB",
        "LOCAL_DEST_MYSQL_HOST",
        "LOCAL_DEST_MYSQL_USER",
        "LOCAL_DEST_MYSQL_PASS",
        "LOCAL_DEST_MYSQL_PORT",
        "LOCAL_DEST_MYSQL_NAME",
    ];

    foreach($envParams as $param) {
        defined($param) || define($param, getenv($param));
    }
});