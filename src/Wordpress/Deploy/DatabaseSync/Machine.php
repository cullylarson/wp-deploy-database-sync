<?php

namespace Wordpress\Deploy\DatabaseSync;

use Wordpress\Deploy\DatabaseSync\ExportFile;
use Wordpress\Deploy\DatabaseSync\Status;

class Machine {
    private $options;
    private $exportFile;
    private $statusCallback;

    public function __construct(array $options, ExportFile $exportFile, Status $statusCallback=null) {
        $this->options = $options;
        $this->exportFile = $exportFile;
        $this->statusCallback = $statusCallback;
    }

    public function export() {
        if($this->ssh) {
            $this->exportSsh();
        }
    }

    private function exportSsh() {

    }

    private function exportCmd() {
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

    public function import() {

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

    /**
     * @param Status $status
     * @param \Closure|null $statusCallback
     */
    private function doStatusCallback(Status $status, $statusCallback) {
        if(!$statusCallback) return;
        else $statusCallback($status);
    }
}