<?php

namespace Wordpress\Deploy\DatabaseSync\Machine;

use Wordpress\Deploy\DatabaseSync\ExportFile;

class Options {
    /**
     * @var array
     */
    private $options;

    /**
     * @var ExportFile
     */
    private $exportFile;

    /**
     * @var string
     */
    private $_tmpFiltered;

    /**
     * @param array $options
     * @param ExportFile $exportFile
     */
    public function __construct(array $options, ExportFile $exportFile) {
        $this->options = $options;
        $this->exportFile = $exportFile;
        $this->fillDefaults();
    }

    private function fillDefaults() {
        $defaults = [
            'local' => false,
            'srdb' => null,
            'ssh' => null,
            'db' => [
                'port' => 3306,
            ],
            'keep_backup' => false,
        ];

        $this->options = array_merge($defaults, $this->options);
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateAsSource() {
        $this->validateCommon("source");
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function validateAsDest() {
        $this->validateCommon("destination");

        // we only need the srdb command on the destination
        if(!isset($this->options['srdb'])) throw new \InvalidArgumentException("You must provide the path to srdb.cli.php on the destination machine.");
    }

    /**
     * @param string    $name   Something like "source" or "local".  Only used in exception messages.
     *
     * @throws \InvalidArgumentException
     */
    private function validateCommon($name) {
        if(!isset($this->options['db']['host'])) throw new \InvalidArgumentException("You must provide the {$name} database host.");
        if(!isset($this->options['db']['username'])) throw new \InvalidArgumentException("You must provide the {$name} database username.");
        if(!isset($this->options['db']['password'])) throw new \InvalidArgumentException("You must provide the {$name} database password.");
        if(!isset($this->options['db']['name'])) throw new \InvalidArgumentException("You must provide the {$name} database name.");
        if(!isset($this->options['tmp'])) throw new \InvalidArgumentException("You must provide the path to a folder for temporary files on the {$name} machine.");

        if(isset($this->options['ssh']) && !is_resource($this->options['ssh'])) {
            throw new \InvalidArgumentException("The {$name} ssh connection resource you provided is not a resource.");
        }

        // ssh isn't set, but local isn't true
        if(!isset($this->options['ssh']) && empty($this->options['local'])) {
            throw new \InvalidArgumentException("The {$name} machine must either have an ssh connection resource, or 'local' explicitly set to true.");
        }

        // ssh is set, and local isn't false
        if(isset($this->options['ssh']) && isset($this->options['local']) && $this->options['local'] !== false) {
            throw new \InvalidArgumentException("The {$name} machine has an ssh connection resource and 'local' set to true.");
        }
    }

    /**
     * @return ExportFile
     */
    public function getExportFile() {
        return $this->exportFile;
    }

    /**
     * @return string
     */
    public function getDbHost() {
        return $this->getDbOptions()['host'];
    }

    /**
     * @return string
     */
    public function getDbUser() {
        return $this->getDbOptions()['user'];
    }

    /**
     * @return string
     */
    public function getDbPass() {
        return $this->getDbOptions()['pass'];
    }

    /**
     * @return string
     */
    public function getDbName() {
        return $this->getDbOptions()['name'];
    }

    /**
     * @return int
     */
    public function getDbPort() {
        return (int) $this->getDbOptions()['port'];
    }

    /**
     * @return bool
     */
    public function isLocal() {
        return (bool) $this->options['local'];
    }

    /**
     * @return bool
     */
    public function isRemote() {
        return isset($this->options['ssh']);
    }

    /**
     * @return string|null
     */
    public function getSrdb() {
        return $this->options['srdb'];
    }

    /**
     * Will not have an trailing slash.
     *
     * @return string
     */
    public function getTmp() {
        // need to filter
        if(!isset($this->_tmpFiltered)) {
            // make sure it doesn't end with a slash
            $this->_tmpFiltered = preg_replace(';[/\\\]+$;', "", $this->options['tmp']);
        }

        return $this->_tmpFiltered;
    }

    /**
     * @return array
     */
    private function getDbOptions() {
        return $this->options['db'];
    }

    /**
     * @return bool
     */
    public function shouldKeepBackup() {
        return $this->getBoolOption('keep_backup', false);
    }

    /**
     * @return resource|null
     */
    public function getSsh() {
        return $this->getOption('ssh', null);
    }

    /**
     * @param string $option
     * @param bool $defaultVal
     * @return bool
     */
    private function getBoolOption($option, $defaultVal) {
        if(!isset($this->options[$option])) return $defaultVal;
        else return ($this->options[$option] == true);
    }

    /**
     * @param string    $option
     * @param mixed     $default
     * @return mixed
     */
    private function getOption($option, $default) {
        if(isset($this->options[$option])) return $this->options[$option];
        else return $default;
    }
}
