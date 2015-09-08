<?php

namespace Wordpress\Deploy\DatabaseSync;

use Cully\ICommand;
use Cully\Ssh;
use Cully\Local;

class Machine {
    /**
     * @param ICommand $command Use to execute commands (remote or local)
     */
    public function __construct(ICommand $command) {
        $this->command = $command;
    }

    public function dumpDatabaseToFile($dbParams, $outputFilePath) {
        $dumpCommand = CommandUtil::buildDumpCommand($dbParams, $outputFilePath);
        $this->command->exec($dumpCommand);
    }
}