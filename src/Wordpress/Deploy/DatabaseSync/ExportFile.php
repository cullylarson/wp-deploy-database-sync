<?php

namespace Wordpress\Deploy\DatabaseSync;

class ExportFile {
    /**
     * @var string
     */
    private $base;

    /**
     * @param string    $filenameBase
     */
    public function __construct($filenameBase) {
        $this->base = $filenameBase;
    }

    public function getSqlFilename() {
        return $this->base . ".sql";
    }

    public function getGzipFilename() {
        return $this->getSqlFilename() . ".gz";
    }
}