<?php

namespace Wordpress\Deploy\DatabaseSync;

class CommandUtil {
    public static function buildSrdbCommand($srdbPath, $dbParams, $search, $replace) {
        $command = sprintf(
            "%s -h '%s' -u '%s' -p '%s' -n '%s' -s '%s' -r '%s'",
            $srdbPath,
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['name']),
            escapeshellcmd($search),
            escapeshellcmd($replace)
        );

        return $command;
    }

    public static function buildDumpCommand($dbParams, $outputFilePath) {
        $command = sprintf(
            "mysqldump -h '%s' -u '%s' -p'%s' '%s' | gzip -> '%s'",
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['name']),
            escapeshellcmd($outputFilePath)
        );

        return $command;
    }

    public static function buildImportCommand($dbParams, $inputFilePath) {
        $command = sprintf(
            "mysql -h '%s' -u '%s' -p'%s' '%s' < '%s'",
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['name']),
            escapeshellcmd($inputFilePath)
        );

        return $command;
    }

    public static function buildImportCommandWithGunzip($dbParams, $inputGzipedFilePath) {
        $command = sprintf(
            "gunzip -c '%s' | mysql -h '%s' -u '%s' -p'%s' '%s'",
            escapeshellcmd($inputGzipedFilePath),
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['name'])
        );

        return $command;
    }

    public static function buildMysqlCommand($dbParams) {
        $command = sprintf(
            "mysql -h '%s' -u '%s' -p'%s' '%s'",
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['name'])
        );

        return $command;
    }
}