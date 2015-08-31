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
        if(!isset($this->options['source']['db']['host'])) throw new \InvalidArgumentException("You must provide the source host.");
        if(!isset($this->options['source']['db']['username'])) throw new \InvalidArgumentException("You must provide the source username.");
        if(!isset($this->options['source']['db']['password'])) throw new \InvalidArgumentException("You must provide the source password.");
        if(!isset($this->options['source']['db']['name'])) throw new \InvalidArgumentException("You must provide the source database name.");

        if(!isset($this->options['dest']['db']['host'])) throw new \InvalidArgumentException("You must provide the destination host.");
        if(!isset($this->options['dest']['db']['username'])) throw new \InvalidArgumentException("You must provide the destination username.");
        if(!isset($this->options['dest']['db']['password'])) throw new \InvalidArgumentException("You must provide the destination password.");
        if(!isset($this->options['dest']['db']['name'])) throw new \InvalidArgumentException("You must provide the destination database name.");

        if(!isset($this->options['source']['tmp'])) throw new \InvalidArgumentException("You must provide the location of a folder on the source for temporary files.");
        if(!isset($this->options['dest']['tmp'])) throw new \InvalidArgumentException("You must provide the location of a folder on the destination for temporary files.");
    }

    public function haveSourceSsh() {
        return !empty($this->options['source']['ssh']);
    }

    public function haveDestSsh() {
        return !empty($this->options['dest']['ssh']);
    }

    public function getSourceOptions() {
        return $this->options['source'];
    }

    public function getDestOptions() {
        return $this->options['dest'];
    }
}