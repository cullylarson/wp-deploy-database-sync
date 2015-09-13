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

    // TODO -- not all of these are required for every kind of sync don't validate them here unless they're required for every sync
    private function validate() {
        if(!isset($this->options['source']['db']['host'])) throw new \InvalidArgumentException("You must provide the source database host.");
        if(!isset($this->options['source']['db']['username'])) throw new \InvalidArgumentException("You must provide the source database username.");
        if(!isset($this->options['source']['db']['password'])) throw new \InvalidArgumentException("You must provide the source database password.");
        if(!isset($this->options['source']['db']['name'])) throw new \InvalidArgumentException("You must provide the source database name.");
        if(!isset($this->options['source']['srdb'])) throw new \InvalidArgumentException("You must provide the path to srdb.cli.php on the source machine.");
        if(!isset($this->options['source']['tmp'])) throw new \InvalidArgumentException("You must provide the path to a folder for temporary files on the source machine.");
        if(!is_resource($this->options['source']['ssh'])) throw new \InvalidArgumentException("The source ssh connection resource you provided is not a resource.");

        if(!isset($this->options['dest']['db']['host'])) throw new \InvalidArgumentException("You must provide the destination database host.");
        if(!isset($this->options['dest']['db']['username'])) throw new \InvalidArgumentException("You must provide the destination database username.");
        if(!isset($this->options['dest']['db']['password'])) throw new \InvalidArgumentException("You must provide the destination database password.");
        if(!isset($this->options['dest']['db']['name'])) throw new \InvalidArgumentException("You must provide the destination database name.");
        if(!isset($this->options['dest']['tmp'])) throw new \InvalidArgumentException("You must provide the path to a folder for temporary files on the destination machine.");
        if(!isset($this->options['dest']['srdb'])) throw new \InvalidArgumentException("You must provide the path to srdb.cli.php on the destination machine.");
        if(!isset($this->options['dest']['ssh'])) throw new \InvalidArgumentException("You must provide an ssh connection resource to the destination machine.");
        if(!is_resource($this->options['remote']['ssh'])) throw new \InvalidArgumentException("The destination ssh connection resource you provided is not a resource.");

        if(!isset($this->options['local_tmp'])) throw new \InvalidArgumentException("You must provide the path to a folder for temporary files on the local machine, to perform remote to remote syncs.");

        if(isset($this->options['search_replace']) && !is_array($this->options['search_replace'])) throw new \InvalidArgumentException("The 'search_replace' param must be an array.");
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
        $this->options = array_merge($defaults, $this->options);
    }

    public function getLocalOptions() {
        return $this->options['local'];
    }

    public function getRemoteOptions() {
        return $this->options['remote'];
    }

    public function getLocalDbOptions() {
        return $this->options['local']['db'];
    }

    public function getRemoteDbOptions() {
        return $this->options['remote']['db'];
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