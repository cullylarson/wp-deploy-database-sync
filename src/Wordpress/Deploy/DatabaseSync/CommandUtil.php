<?php

namespace Wordpress\Deploy\DatabaseSync;

class CommandUtil {
    public static function buildSrdbCommand($srdbPath, $dbParams, $search, $replace) {
        $command = sprintf(
            "%s --host='%s' --user='%s' --pass='%s' --port='%s' --name='%s' --search='%s' --replace='%s'",
            $srdbPath,
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['port']),
            escapeshellcmd($dbParams['name']),
            escapeshellcmd($search),
            escapeshellcmd($replace)
        );

        return $command;
    }

    public static function buildDumpCommand($dbParams, $outputFilePath) {
        $mysqlOptions = self::buildMysqlCommandOptions($dbParams);
        $command = sprintf("mysqldump %s | gzip -> '%s'",
            $mysqlOptions,
            escapeshellcmd($outputFilePath)
        );

        return $command;
    }

    public static function buildImportCommandFromSqlFile($dbParams, $inputFilePath) {
        $mysqlCommand = self::buildMysqlCommand($dbParams);

        $command = sprintf("%s < '%s'",
            $mysqlCommand,
            escapeshellcmd($inputFilePath)
        );

        return $command;
    }

    public static function buildImportCommandFromGunzipFile($dbParams, $inputGzipedFilePath) {
        $mysqlCommand = self::buildMysqlCommand($dbParams);

        $command = sprintf("gunzip -c '%s' | %s",
            escapeshellcmd($inputGzipedFilePath),
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
            "--host='%s' --user='%s' --password='%s' --port='%s' --database='%s'",
            escapeshellcmd($dbParams['host']),
            escapeshellcmd($dbParams['username']),
            escapeshellcmd($dbParams['password']),
            escapeshellcmd($dbParams['port']),
            escapeshellcmd($dbParams['name'])
        );

        return $options;
    }
}