<?php

namespace Wordpress\Deploy;

use Wordpress\Deploy\DatabaseSync\Status;
use Wordpress\Deploy\DatabaseSync\Options;
use Wordpress\Deploy\DatabaseSync\ExportFile;
use Wordpress\Deploy\DatabaseSync\Machine;

class DatabaseSync {
    /**
     * @var Options
     */
    private $options;
    /**
     * @var ExportFilename
     */
    private $exportFilename;

    /**
     * @param array $options
     */
    public function __construct(array $options) {
        $this->options = new Options($options);
        $this->exportFilename = new ExportFile($this->generateExportFilenameBase());

    }

    /**
     * @param \Closure|null $statusCallback
     */
    public function push($statusCallback=null) {

    }

    /**
     * @param \Closure|null $statusCallback
     */
    public function pull($statusCallback=null) {

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

    private function generateExportFilenameBase() {
        $dbName = $this->options->getSourceOptions()['name'];
        $filename = preg_replace("/[^a-zA-Z0-9]/", "", $dbName);
        if(empty($filename)) $filename = "database";

        return $filename . "-" . date("Ymd-His");
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