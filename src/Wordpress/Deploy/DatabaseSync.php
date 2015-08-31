<?php

namespace Wordpress\Deploy;

use Wordpress\Deploy\DatabaseSync\Status;
use Wordpress\Deploy\DatabaseSync\Options;

class DatabaseSync {
    /**
     * @var Options
     */
    private $options;

    /**
     * @param array $options
     */
    public function __construct(array $options) {
        $this->options = new Options($options);
    }

    /**
     * @param \Closure|null $statusCallback
     */
    public function sync($statusCallback=null) {
        if($this->export()) $this->import();
    }

    private function export($statusCallback=null) {
        $this->doStatusCallback(new Status("Exporting the database."), $statusCallback);

        // if we have an ssh connection to the source, then export that way
        if($this->options->haveSourceSsh()) {
            return $this->exportSsh($statusCallback);
        }
        // otherwise, export from the source locally
        else {
            return $this->exportCmd($statusCallback);
        }
    }

    private function import($statusCallback=null, $sqlFilePath) {
        $this->doStatusCallback(new Status("Importing the database."), $statusCallback);

        // if we have an ssh connection to the dest, then export that way
        if($this->options->haveDestSsh()) {
            $this->importSsh($statusCallback);
        }
        // otherwise, import the the destination locally
        else {
            $this->importCmd($statusCallback, $sqlFilePath);
        }
    }

    private function exportSsh($statusCallback=null) {

    }

    private function exportCmd($statusCallback=null) {
        $this->ensureMysqlCommandSource();
        $this->ensureGzipCommandSource();

        $command = $this->buildDumpCommand();

        exec($command, $output, $ret);

        $success = !boolval($ret);

        /*
         * Get output
         */

        $this->doStatusCallback(new Status(implode("\n", $output), Status::MT_RAW_OUTPUT), $statusCallback);

        /*
         * Process errors
         */

        if(!$success) {
            $this->doStatusCallback(
                new Status("Something went wrong. Could not export the database.", Status::MT_ERROR),
                $statusCallback);
        }

        return $success;
    }

    private function buildDumpCommand() {
        $source = $this->options->getSourceOptions();

        $dumpFilename = $this->generateDumpFilename($source['name']);

        $outputFilename = "{$source['tmp']}/{$dumpFilename}.gz";

        $command = sprintf(
            "mysqldump -h '%s' -u '%s' -p'%s' '%s' | gzip -> %s",
            $source['host'],
            $source['username'],
            $source['password'],
            $source['name'],
            $outputFilename
        );

        return $command;
    }

    private function generateDumpFilename($dbName) {
        $filename = preg_replace("/[^a-zA-Z0-9]/", "", $dbName);
        if(empty($filename)) $filename = "database";

        return $filename . "-" . date("Ymd-His") . ".sql";
    }

    private function buildImportCommand($sqlFilePath) {
        $dest = $this->options->getSourceOptions();

        $command = sprintf(
            "mysql -h '%s' -u '%s' -p'%s' '%s' < %s",
            $dest['host'],
            $dest['username'],
            $dest['password'],
            $dest['name'],
            $sqlFilePath
        );

        return $command;
    }

    private function importSsh($statusCallback=null) {

    }

    private function importCmd($statusCallback=null, $sqlFilePath) {
        $this->ensureMysqlCommandDest();
        $this->ensureGzipCommandDest();

        $command = $this->buildImportCommand($sqlFilePath);

        exec($command, $output, $ret);

        $success = !boolval($ret);

        /*
         * Get output
         */

        $this->doStatusCallback(new Status(implode("\n", $output), Status::MT_RAW_OUTPUT), $statusCallback);

        /*
         * Process errors
         */

        if(!$success) {
            $this->doStatusCallback(
                new Status("Something went wrong. Could not import the database.", Status::MT_ERROR),
                $statusCallback);
        }

        return $success;
    }

    /**
     * @param Status $status
     * @param \Closure|null $statusCallback
     */
    private function doStatusCallback(Status $status, $statusCallback) {
        if(!$statusCallback) return;
        else $statusCallback($status);
    }
}