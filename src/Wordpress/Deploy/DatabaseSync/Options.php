<?php

namespace Wordpress\Deploy\DatabaseSync;

use Wordpress\Deploy\DatabaseSync\Machine;
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
     * @var Machine\Options
     */
    private $source;

    /**
     * @var Machine\Options
     */
    private $dest;

    /**
     * @param array $options
     * @param ExportFile $exportFile
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $options, ExportFile $exportFile) {
        if(!isset($options['source'])) $options['source'] = [];
        if(!isset($options['dest'])) $options['dest'] = [];

        $this->options = $options;
        $this->exportFile = $exportFile;

        $this->source = new Machine\Options($options['source'], $exportFile);
        $this->dest = new Machine\Options($options['dest'], $exportFile);

        $this->validate();
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function validate() {
        $this->source->validateAsSource();
        $this->dest->validateAsDest();

        // this is a remote to remote sync and local_tmp isn't set
        if($this->source->isRemote() && $this->dest->isRemote() && !isset($this->options['local_tmp'])) throw new \InvalidArgumentException("You must provide the path to a folder for temporary files on the local machine, to perform remote to remote syncs.");

        if(isset($this->options['search_replace']) && !is_array($this->options['search_replace'])) throw new \InvalidArgumentException("The 'search_replace' param must be an array.");
    }

    public function getLocalTmp() {
        return $this->getOption("local_tmp", null);
    }

    /**
     * @return Machine\Options
     */
    public function getSource() {
        return $this->source;
    }

    /**
     * @return Machine\Options
     */
    public function getDest() {
        return $this->dest;
    }

    /**
     * @return bool
     */
    public function shouldDoSearchReplace() {
        return (isset($this->options['search_replace']) && !empty($this->options['search_replace']));
    }

    /**
     * @return array
     */
    public function getSearchReplace() {
        return $this->getArrayOption('search_replace');
    }

    /**
     * @param string    $option     Key in options array
     * @return array
     */
    private function getArrayOption($option) {
        if( isset($this->options[$option]) && is_array($this->options[$option])) return $this->options[$option];
        else return [];
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