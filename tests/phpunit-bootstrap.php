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

        "REMOTE_TMP",
        "REMOTE_SRDB",

        "LOCAL_TMP",

        "REMOTE_MYSQL_HOST",
        "REMOTE_MYSQL_USER",
        "REMOTE_MYSQL_PASS",
        "REMOTE_MYSQL_PORT",
        "REMOTE_MYSQL_NAME",

        "LOCAL_MYSQL_HOST",
        "LOCAL_MYSQL_USER",
        "LOCAL_MYSQL_PASS",
        "LOCAL_MYSQL_PORT",
        "LOCAL_MYSQL_NAME",
    ];

    foreach($envParams as $param) {
        defined($param) || define($param, getenv($param));
    }
});