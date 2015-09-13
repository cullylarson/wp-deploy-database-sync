<?php

namespace Wordpress\Deploy;

use Wordpress\Deploy\DatabaseSync\Options;
use Wordpress\Deploy\DatabaseSync\ExportFile;
use Wordpress\Deploy\DatabaseSync\Pusher;
use Wordpress\Deploy\DatabaseSync\Puller;
use Wordpress\Deploy\DatabaseSync\Machine;
use Wordpress\Deploy\DatabaseSync\Status;
use Wordpress\Deploy\DatabaseSync\TDoStatusCallback;

use Cully\Local;
use Cully\Ssh;

// TODO -- support dry runs?

class DatabaseSync {
    use TDoStatusCallback;

    /**
     * @var Options
     */
    private $options;

    /**
     * @var Machine
     */
    private $source;

    /**
     * @var Machine
     */
    private $dest;

    /**
     * @param array $options
     */
    public function __construct(array $options) {
        $this->options = new Options($options, new ExportFile($this->generateExportFilenameBase()));
        $this->source = new Machine($this->options->getSource());
        $this->dest = new Machine($this->options->getDest());
    }

    /**
     * @param Status|null $statusCallback
     * @return bool
     */
    public function sync($statusCallback=null) {
        /*
         * Dump the source database
         */

        if( !($sourceDumpFile = $this->dumpSourceDatabaseToFile($statusCallback)) ) return false;

        /*
         * Copy the source dump file to the destination
         */

        if( !($destDumpFile = $this->copySourceDumpFileToDestination($statusCallback, $sourceDumpFile)) ) return false;

        /*
         * Delete the source dump file
         */

        // (we don't care if it worked or not, press on)
        $this->deleteSourceDumpFile($statusCallback, $sourceDumpFile);


        /*
         * Import dest dump file
         */

        if( !$this->importDestDumpFile($statusCallback, $destDumpFile) ) {
            // try to delete the dump file
            $this->dest->deleteDumpFile($destDumpFile);
            return false;
        }

        /*
         * Delete dest dump file
         */

        // (we don't care if it worked or not, press on)
        $this->deleteDestDumpFile($statusCallback, $destDumpFile);

        /*
         * Database Search & Replace
         */

        if( !$this->doDestDatabaseSearchReplace($statusCallback) ) return false;

        /*
         * Success!
         */

        return true;
    }

    private function doDestDatabaseSearchReplace($statusCallback) {
        if($this->options->shouldDoSearchReplace()) {
            $this->doStatusCallback(new Status("Performing database search & replace at destination.", Status::MT_NOTICE), $statusCallback);

            foreach($this->options->getSearchReplace() as $search => $replace) {
                $this->doStatusCallback(new Status("Database search & replace: {$search} -> {$replace}", Status::MT_NOTICE), $statusCallback);

                $success = $this->dest->doDatabaseSearchReplace($search, $replace);

                if(!$success) {
                    $this->doStatusCallback(new Status("Failed to perform search and replace.", Status::MT_ERROR), $statusCallback);
                    return false;
                }
            }
        }
        else {
            $this->doStatusCallback(new Status("Skipping database search & replace at destination because none have been provided.", Status::MT_NOTICE), $statusCallback);
        }

        // real success
        return true;
    }

    /**
     * @param $statusCallback
     * @param string $destDumpFile
     * @return bool
     */
    private function importDestDumpFile($statusCallback, $destDumpFile) {
        $this->doStatusCallback(new Status("Importing dump file at destination ({$destDumpFile}).", Status::MT_NOTICE), $statusCallback);

        $success = $this->dest->importDumpFile($destDumpFile);

        if(!$success) {
            $this->doStatusCallback(new Status("Failed to import dump file at destination ({$destDumpFile}).", Status::MT_ERROR), $statusCallback);
            return false;
        }

        return true;
    }

    /**
     * @param $statusCallback
     * @param $sourceDumpFile
     * @return bool True if succeeded or kept the file.  False if failed.
     */
    private function deleteSourceDumpFile($statusCallback, $sourceDumpFile) {
        $success = $this->source->deleteDumpFile($sourceDumpFile);

        // if null, we kept the file
        if( $success === null ) {
            $this->doStatusCallback(new Status("Keeping source dump file ({$sourceDumpFile}).", Status::MT_NOTICE), $statusCallback);
            return true;
        }
        // succeeded
        else if($success) {
            $this->doStatusCallback(new Status("Deleted source dump file ({$sourceDumpFile}).", Status::MT_NOTICE), $statusCallback);
            return true;
        }
        // failed
        else {
            $this->doStatusCallback(new Status("Failed to delete source dump file ({$sourceDumpFile}).", Status::MT_ERROR), $statusCallback);
            return false;
        }
    }

    /**
     * @param $statusCallback
     * @param $destDumpFile
     * @return bool True if succeeded or kept the file.  False if failed.
     */
    private function deleteDestDumpFile($statusCallback, $destDumpFile) {
        $success = $this->source->deleteDumpFile($destDumpFile);

        // if null, we kept the file
        if( $success === null ) {
            $this->doStatusCallback(new Status("Keeping dest dump file ({$destDumpFile}).", Status::MT_NOTICE), $statusCallback);
            return true;
        }
        // succeeded
        else if($success) {
            $this->doStatusCallback(new Status("Deleted dest dump file ({$destDumpFile}).", Status::MT_NOTICE), $statusCallback);
            return true;
        }
        // failed
        else {
            $this->doStatusCallback(new Status("Failed to dest source dump file ({$destDumpFile}).", Status::MT_ERROR), $statusCallback);
            return false;
        }
    }

    private function copySourceDumpFileToDestination($statusCallback, $sourceDumpFile) {
        $this->doStatusCallback(new Status("Copying source dump file ({$sourceDumpFile}) to destination.", Status::MT_NOTICE), $statusCallback);

        $destDumpFile = $this->source->copyDumpFile($sourceDumpFile, $this->dest, $this->options->getLocalTmp());

        if(!$destDumpFile) {
            $this->doStatusCallback(new Status("Failed to copy source dump file ({$sourceDumpFile}) to destination.", Status::MT_ERROR), $statusCallback);
            return false;
        }

        return $destDumpFile;
    }

    private function dumpSourceDatabaseToFile($statusCallback) {
        $dumpFile = $this->source->dumpDatabaseToFile();
        $this->doStatusCallback(new Status("Dumping source database to file ({$dumpFile}).", Status::MT_NOTICE), $statusCallback);

        if(!$dumpFile) {
            $this->doStatusCallback(new Status("Failed to dump source database to file ({$dumpFile}).", Status::MT_ERROR), $statusCallback);
            return false;
        }

        return $dumpFile;
    }

    private function generateExportFilenameBase() {
        $dbName = $this->options->getSource()->getDbName();
        $filename = preg_replace("/[^a-zA-Z0-9]/", "", $dbName);
        if(empty($filename)) $filename = "database";

        return $filename . "-" . date("Ymd-His");
    }
}