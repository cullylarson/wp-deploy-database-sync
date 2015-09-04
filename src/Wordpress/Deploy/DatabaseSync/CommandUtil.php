<?php

namespace Wordpress\Deploy\DatabaseSync;

class CommandUtil {
    public static function buildSrdbCommand($srdbPath, $dbParams, $search, $replace) {
        $command = sprintf(
            "php %s --host=%s --user=%s --pass=%s --port=%s --name=%s --search=%s --replace=%s",
            $srdbPath,
            escapeshellarg($dbParams['host']),
            escapeshellarg($dbParams['username']),
            escapeshellarg($dbParams['password']),
            escapeshellarg($dbParams['port']),
            escapeshellarg($dbParams['name']),
            escapeshellarg($search),
            escapeshellarg($replace)
        );

        return $command;
    }

    public static function buildDumpCommand($dbParams, $outputFilePath) {
        $mysqlOptions = self::buildMysqlCommandOptions($dbParams);
        $command = sprintf("mysqldump %s | gzip -> %s",
            $mysqlOptions,
            escapeshellarg($outputFilePath)
        );

        return $command;
    }

    public static function buildImportCommandFromGunzipFile($dbParams, $inputGzipedFilePath) {
        $mysqlCommand = self::buildMysqlCommand($dbParams);

        $command = sprintf("gunzip -c %s | %s",
            escapeshellarg($inputGzipedFilePath),
            $mysqlCommand
        );

        return $command;
    }

    public static function buildMysqlCommand($dbParams) {
        $mysqlOptions = self::buildMysqlCommandOptions($dbParams);

        return "mysql {$mysqlOptions}";
    }

    private static function buildMysqlCommandOptions($dbParams) {
        $options = sprintf(
            "--host=%s --user=%s --password=%s --port=%s %s",
            escapeshellarg($dbParams['host']),
            escapeshellarg($dbParams['username']),
            escapeshellarg($dbParams['password']),
            escapeshellarg($dbParams['port']),
            escapeshellarg($dbParams['name'])
        );

        return $options;
    }
}