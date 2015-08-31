<?php

namespace Wordpress\Deploy\DatabaseSync;

class Options {
    /**
     * @var array
     */
    private $options;

    public function __construct(array $options) {
        $this->options = $options;

        $this->ensureRequired();
    }

    private function ensureRequired() {
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
        if(!isset($this->options['remote']['ssh'])) throw new \InvalidArgumentException("You must provide an ssh connection resource to the remote machine.");
    }

    public function getLocalOptions() {
        return $this->options['local'];
    }

    public function getRemoteOptions() {
        return $this->options['remote'];
    }
}