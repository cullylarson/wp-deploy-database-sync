<?php

namespace Wordpress\Deploy\DatabaseSync;

class Options {
    /**
     * @var array
     */
    private $options;

    public function __construct(array $options) {
        $this->options = $options;

        $this->validate();
        $this->setDefaultValues();
    }

    private function validate() {
        if(!isset($this->options['local']['db']['host'])) throw new \InvalidArgumentException("You must provide the local host.");
        if(!isset($this->options['local']['db']['username'])) throw new \InvalidArgumentException("You must provide the local username.");
        if(!isset($this->options['local']['db']['password'])) throw new \InvalidArgumentException("You must provide the local password.");
        if(!isset($this->options['local']['db']['name'])) throw new \InvalidArgumentException("You must provide the local database name.");
        if(!isset($this->options['local']['tmp'])) throw new \InvalidArgumentException("You must provide the local location of a folder for temporary files.");

        if(!isset($this->options['remote']['db']['host'])) throw new \InvalidArgumentException("You must provide the remote host.");
        if(!isset($this->options['remote']['db']['username'])) throw new \InvalidArgumentException("You must provide the remote username.");
        if(!isset($this->options['remote']['db']['password'])) throw new \InvalidArgumentException("You must provide the remote password.");
        if(!isset($this->options['remote']['db']['name'])) throw new \InvalidArgumentException("You must provide the remote database name.");
        if(!isset($this->options['remote']['tmp'])) throw new \InvalidArgumentException("You must provide the remote location of a folder for temporary files.");
        if(!isset($this->options['remote']['srdb'])) throw new \InvalidArgumentException("You must provide the remote path to srdb.cli.php.");
        if(!isset($this->options['remote']['ssh'])) throw new \InvalidArgumentException("You must provide an ssh connection resource to the remote machine.");
        if(!is_resource($this->options['remote']['ssh'])) throw new \InvalidArgumentException("The ssh connection resource you provided is not a resource.");

        if(isset($this->options['db_search_replace']) && !is_array($this->options['db_search_replace'])) throw new \InvalidArgumentException("The 'db_search_replace' param must be an array.");
    }

    private function setDefaultValues() {
        $defaults = [
            'local' => [
                'db' => [
                    'port' => 3306,
                ]
            ],
            'remote' => [
                'db' => [
                    'port' => 3306,
                ]
            ],
            'keep_local_backup' => false,
            'keep_remote_backup' => false,
        ];

        // TODO -- make sure this works
        $this->options = array_merge_recursive($defaults, $this->options);
    }

    public function getLocalOptions() {
        return $this->options['local'];
    }

    public function getRemoteOptions() {
        return $this->options['remote'];
    }

    public function shouldKeepLocalBackup() {
        return $this->getBoolOption('keep_local_backup', false);
    }

    public function shouldKeepRemoteBackup() {
        return $this->getBoolOption('keep_remote_backup', false);
    }

    public function shouldDoSearchReplace() {
        return (isset($this->options['search_replace']) && !empty($this->options['search_replace']));
    }

    public function getSearchReplace() {
        return $this->getArrayOption('search_replace');
    }

    private function getBoolOption($option, $defaultVal) {
        if(!isset($this->options[$option])) return $defaultVal;
        else return ($this->options[$option] == true);
    }

    private function getArrayOption($option) {
        if( isset($this->options[$option]) && is_array($this->options[$option])) return $this->options[$option];
        else return [];
    }
}