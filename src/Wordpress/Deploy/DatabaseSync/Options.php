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

    public function getLocalOptions() {
        return $this->options['local'];
    }

    public function getRemoteOptions() {
        return $this->options['remote'];
    }

    public function shouldKeepLocalDumpCopy() {
        return $this->getBoolOption('keep_local_dump_copy', false);
    }

    public function shouldKeepRemoteDumpCopy() {
        return $this->getBoolOption('keep_remote_dump_copy', false);
    }

    public function shouldDoDbSearchReplace() {
        return (isset($this->options['db_search_replace']) && !empty($this->options['db_search_replace']));
    }

    public function getDbSearchReplace() {
        return $this->getArrayOption('db_search_replace');
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