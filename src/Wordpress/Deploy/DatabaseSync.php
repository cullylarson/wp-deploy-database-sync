<?php

namespace Wordpress\Deploy;

use Wordpress\Deploy\DatabaseSync\Status;
use Wordpress\Deploy\DatabaseSync\Options;
use Wordpress\Deploy\DatabaseSync\ExportFile;
use Wordpress\Deploy\DatabaseSync\Pusher;
use Wordpress\Deploy\DatabaseSync\Puller;

use Cully\Local;
use Cully\Ssh;

// TODO -- support dry runs?

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
     *
     * @return boolean
     */
    public function push($statusCallback=null) {
        $pusher = new Pusher($this->options, $this->exportFilename);
        return $pusher->push($statusCallback);
    }

    /**
     * @param \Closure|null $statusCallback
     *
     * @return boolean
     */
    public function pull($statusCallback=null) {
        $puller = new Puller($this->options, $this->exportFilename);
        return $puller->pull($statusCallback);
    }

    private function generateExportFilenameBase() {
        $dbName = $this->options->getLocalDbOptions()['name'];
        $filename = preg_replace("/[^a-zA-Z0-9]/", "", $dbName);
        if(empty($filename)) $filename = "database";

        return $filename . "-" . date("Ymd-His");
    }
}