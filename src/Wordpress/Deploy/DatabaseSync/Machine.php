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
     * Make sure that necessary commands exist on this
     * machine.
     *
     * @throws \RuntimeException
     */
    public function ensureSourceCommands() {
        // mysqldump
        $this->command->exec("which mysqldump");
        if($this->command->failure()) throw new \RuntimeException("The 'mysqldump' command does not exist on the source machine.");

        // gzip
        $this->command->exec("which gzip");
        if($this->command->failure()) throw new \RuntimeException("The 'gzip' command does not exist on the source machine.");
    }

    /**
     * Make sure that necessary commands exist on this
     * machine.
     *
     * @throws \RuntimeException
     */
    public function ensureDestCommands() {
        // mysql
        $this->command->exec("which mysql");
        if($this->command->failure()) throw new \RuntimeException("The 'mysql' command does not exist on the destination machine.");

        // gunzip
        $this->command->exec("which gunzip");
        if($this->command->failure()) throw new \RuntimeException("The 'gunzip' command does not exist on the destination machine.");

        // php
        $this->command->exec("which php");
        if($this->command->failure()) throw new \RuntimeException("The 'php' command does not exist on the destination machine.");

        // srdb
        if(!empty($this->getOptions()->getSrdb())) {
            $this->command->exec("ls " . escapeshellarg($this->getOptions()->getSrdb()));
            if($this->command->failure()) throw new \RuntimeException("The srdb command provided does not exist or is not accessible.");
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
     * Will copy the file from the source to the destination.
     *
     * If this is a local to local copy, and the destination is the same as the source,
     * it won't be copied (since it's already at it's destination).
     *
     * @param string    $sourceDumpFile     Path to source file.
     * @param Machine   $dest
     * @param string|null $localTmp         If this is a remote to remote copy, pass the localTmp
     *
     * @throws \InvalidArgumentException    If the copy failed because of an invalid parameter.
     *
     * @return string|bool  False if failed, otherwise the path to the dump file on the destination.
     */
    public function copyDumpFile($sourceDumpFile, Machine $dest, $localTmp=null) {
        $destDumpFile = $dest->generateDumpFilePath();

        // don't copy if this is a local to local sync, and the source and dest are
        // the same (it's already where we want it to be)
        if( !$this->getOptions()->isLocal() || !$dest->getOptions()->isLocal() || $sourceDumpFile != $destDumpFile ) {

            $copier = new Copier($this->options->getSsh(), $dest->getOptions()->getSsh(), $localTmp);

            try {
                if (!$copier->copy($sourceDumpFile, $destDumpFile)) {
                    return false;
                }
            } catch (\UnexpectedValueException $e) {
                throw new \InvalidArgumentException($e->getMessage());
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException($e->getMessage());
            }
        }

        return $destDumpFile;
    }

    /**
     * @param string $dumpFile
     * @return null|bool    True if delete succeeds. Null if we didn't delete
     *                      because we're keeping the dump file. False if we tried
     *                      to delete, but failed.
     */
    public function deleteDumpFile($dumpFile) {
        // if we want to keep the dump file, don't do anything
        if($this->options->shouldKeepDump()) return null;

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
