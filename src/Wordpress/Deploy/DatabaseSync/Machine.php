<?php

namespace Wordpress\Deploy\DatabaseSync;

use Cully\ICommand;
use Cully\Ssh;
use Cully\Local;
use Cully\Ssh\Copier;

class Machine {
    /**
     * @var Machine\Options
     */
    private $options;

    /**
     * @var ICommand
     */
    private $command;

    /**
     * @param Machine\Options $options
     */
    public function __construct(Machine\Options $options) {
        $this->options = $options;

        if($this->options->isRemote()) {
            $this->command = new Ssh\Command($this->options->getSsh());
        }
        else {
            $this->command = new Local\Command();
        }
    }

    /**
     * @return Machine\Options
     */
    public function getOptions() {
        return $this->options;
    }

    /**
     * @param string $search
     * @param string $replace
     * @return bool
     */
    public function doDatabaseSearchReplace($search, $replace) {
        $srCommand = CommandUtil::buildSrdbCommand($this->options->getSrdb(), $this->buildDbParams(), $search, $replace);
        $this->command->exec($srCommand);

        return $this->command->success();
    }

    /**
     * @return bool|string  False if failure, otherwise the path to the output file.
     */
    public function dumpDatabaseToFile() {
        $outputFilePath = $this->generateDumpFilePath();
        $dumpCommand = CommandUtil::buildDumpCommand($this->buildDbParams(), $outputFilePath);
        $this->command->exec($dumpCommand);

        if($this->command->success()) {
            return $outputFilePath;
        }
        else {
            return false;
        }
    }

    /**
     * @param string    $sourceDumpFile     Path to source file.
     * @param Machine   $dest
     * @param string|null $localTmp         If this is a remote to remote copy, pass the localTmp
     *
     * @throws \InvalidArgumentException    If the copy failed because of an invalid parameter.
     *
     * @return string|bool  False if failed, otherwise the path to the dump file on the destination.
     */
    public function copyDumpFile($sourceDumpFile, Machine $dest, $localTmp=null) {
        $copier = new Copier($this->options->getSsh(), $dest->getOptions()->getSsh(), $localTmp);
        $destDumpFile = $dest->generateDumpFilePath();

        try {
            if(!$copier->copy($sourceDumpFile, $destDumpFile)) {
                return false;
            }
        }
        catch(\UnexpectedValueException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }
        catch(\InvalidArgumentException $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        return $destDumpFile;
    }

    /**
     * @param string $dumpFile
     * @return null|bool    True if delete succeeds. Null if we didn't delete
     *                      because we're keeping a backup. False if we tried
     *                      to delete, but failed.
     */
    public function deleteDumpFile($dumpFile) {
        // if we want to keep the dump file, don't do anything
        if($this->options->shouldKeepBackup()) return null;

        $this->command->exec("rm " . escapeshellarg($dumpFile));

        return $this->command->success();
    }

    /**
     * @param string $dumpFile
     * @return bool
     */
    public function importDumpFile($dumpFile) {
        $importCommand = CommandUtil::buildImportCommandFromGunzipFile($this->buildDbParams(), $dumpFile);
        $this->command->exec($importCommand);

        return $this->command->success();
    }

    /**
     * Returns a path for a dump file.  This file may or may not exist.
     *
     * @return string
     */
    public function generateDumpFilePath() {
        return $this->options->getTmp() . DIRECTORY_SEPARATOR . $this->options->getExportFile()->getGzipFilename();
    }

    private function buildDbParams() {
        return [
            'host' => $this->options->getDbHost(),
            'username' => $this->options->getDbUser(),
            'password' => $this->options->getDbPass(),
            'name' => $this->options->getDbName(),
            'port' => $this->options->getDbPort(),
        ];
    }
}