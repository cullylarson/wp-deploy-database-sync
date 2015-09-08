<?php

namespace Wordpress\Deploy\DatabaseSync;

use Wordpress\Deploy\DatabaseSync\Options;
use Wordpress\Deploy\DatabaseSync\ExportFile;

use Cully\Local;
use Cully\Ssh;

// TODO -- maybe use define doStatusCallback as a trait?

class Puller {
    /**
     * @var \Wordpress\Deploy\DatabaseSync\Options
     */
    private $options;
    /**
     * @var \Wordpress\Deploy\DatabaseSync\ExportFile
     */
    private $exportFile;
    /**
     * @var array
     */
    private $remote;
    /**
     * @var array
     */
    private $local;
    /**
     * @var array
     */
    private $remoteDb;
    /**
     * @var array
     */
    private $localDb;

    /**
     * @param \Wordpress\Deploy\DatabaseSync\Options $options
     * @param \Wordpress\Deploy\DatabaseSync\ExportFile $exportFile
     */
    public function __construct(Options $options, ExportFile $exportFile) {
        $this->options = $options;
        $this->exportFile = $exportFile;
        $this->remote = $options->getRemoteOptions();
        $this->local = $options->getLocalOptions();
        $this->remoteDb = $options->getRemoteDbOptions();
        $this->localDb = $options->getLocalDbOptions();
    }

    /**
     * @param $statusCallback
     * @param $remoteDumpFilePath
     * @return bool
     */
    private function dumpRemoteDatabase($statusCallback, $remoteDumpFilePath) {
        $this->doStatusCallback(new Status("Dumping remote database to file.", Status::MT_NOTICE), $statusCallback);
        $dumpCommand = CommandUtil::buildDumpCommand($this->remoteDb, $remoteDumpFilePath);
        $scmd = new Ssh\Command($this->remote['ssh']);
        $scmd->exec($dumpCommand);

        // failed
        if($scmd->failure()) {
            $this->doStatusCallback(new Status("Error encountered while running the dump command (Exit Status: {$scmd->getExitStatus()})", Status::MT_ERROR), $statusCallback);
            $this->doStatusCallback(new Status($scmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            return false;
        }

        return true;
    }

    /**
     * @param $statusCallback
     * @param $remoteDumpFilePath
     * @param $localDumpFilePath
     * @return bool
     */
    private function copyDumpFileToLocal($statusCallback, $remoteDumpFilePath, $localDumpFilePath) {
        $this->doStatusCallback(new Status("Copying dump file to local machine.", Status::MT_NOTICE), $statusCallback);

        $copySuccessful = ssh2_scp_recv($this->remote['ssh'], $remoteDumpFilePath, $localDumpFilePath);

        // failed
        if( !$copySuccessful ) {
            $this->doStatusCallback(new Status("Failed to copy dump file to local machine (remote:{$remoteDumpFilePath} -> local:{$localDumpFilePath})", Status::MT_ERROR), $statusCallback);
            return false;
        }

        return true;
    }

    /**
     * @param $statusCallback
     * @param $localDumpFilePath
     */
    private function deleteLocalDumpFile($statusCallback, $localDumpFilePath) {
        if( !$this->options->shouldKeepLocalBackup() ) {
            $this->doStatusCallback(new Status("Deleting local dump file ({$localDumpFilePath}).", Status::MT_NOTICE), $statusCallback);

            $scmd = new Ssh\Command($this->remote['ssh']);

            $localDumpRemoved = @unlink($localDumpFilePath);

            if(!$localDumpRemoved) {
                $this->doStatusCallback(new Status("Failed to delete local dump file ({$localDumpFilePath}).", Status::MT_WARNING), $statusCallback);
                $this->doStatusCallback(new Status($scmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            }
        }
        else {
            $this->doStatusCallback(new Status("Keeping local dump file ({$localDumpFilePath}).", Status::MT_NOTICE), $statusCallback);
        }
    }

    /**
     * @param $statusCallback
     * @param $localDumpFilePath
     * @return bool
     */
    private function importLocalDumpFile($statusCallback, $localDumpFilePath) {
        $this->doStatusCallback(new Status("Importing dump file on local machine ({$localDumpFilePath}).", Status::MT_NOTICE), $statusCallback);

        $lcmd = new Local\Command();

        $importCommand = CommandUtil::buildImportCommandFromGunzipFile($this->localDb, $localDumpFilePath);
        $lcmd->exec($importCommand);

        if($lcmd->failure()) {
            $this->doStatusCallback(new Status("Error encountered while importing dump file into mysql (Exit Status: {$lcmd->getExitStatus()})", Status::MT_ERROR), $statusCallback);
            $this->doStatusCallback(new Status($lcmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            return false;
        }
        else {
            $this->doStatusCallback(new Status($lcmd->getOutput(), Status::MT_RAW_OUTPUT), $statusCallback);
            return true;
        }
    }

    private function deleteRemoteDumpFile($statusCallback, $remoteDumpFilePath) {
        if(!$this->options->shouldKeepRemoteBackup()) {
            $this->doStatusCallback(new Status("Deleting remote dump file ({$remoteDumpFilePath}).", Status::MT_NOTICE), $statusCallback);

            $scmd = new Ssh\Command($this->remote['ssh']);

            $scmd->exec(sprintf("rm %s", escapeshellarg($remoteDumpFilePath)));

            if($scmd->failure()) {
                $this->doStatusCallback(new Status("Failed to delete remote dump file ({$remoteDumpFilePath}).", Status::MT_WARNING), $statusCallback);
                $this->doStatusCallback(new Status($scmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            }
        }
        else {
            $this->doStatusCallback(new Status("Keeping remote dump file ({$remoteDumpFilePath}).", Status::MT_NOTICE), $statusCallback);
        }
    }

    private function localDatabaseSearchReplace($statusCallback) {
        if($this->options->shouldDoSearchReplace()) {
            $this->doStatusCallback(new Status("Performing database search & replace.", Status::MT_NOTICE), $statusCallback);

            $lcmd = new Local\Command();

            foreach($this->options->getSearchReplace() as $search => $replace) {
                $this->doStatusCallback(new Status("Database search & replace: {$search} -> {$replace}", Status::MT_NOTICE), $statusCallback);

                $srCommand = CommandUtil::buildSrdbCommand($this->local['srdb'], $this->localDb, $search, $replace);
                $lcmd->exec($srCommand);

                if($lcmd->failure()) {
                    $this->doStatusCallback(new Status("Error while performing search and replace.", Status::MT_ERROR), $statusCallback);
                    $this->doStatusCallback(new Status($lcmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
                    return false;
                }
            }
        }
        else {
            $this->doStatusCallback(new Status("Skipping database search & replace because none have been provided.", Status::MT_NOTICE), $statusCallback);
        }

        return true;
    }

    public function pull($statusCallback=null) {
        $localDumpFilePath = $this->local['tmp'] . "/" . $this->exportFile->getGzipFilename();
        $remoteDumpFilePath = $this->remote['tmp'] . "/" . $this->exportFile->getGzipFilename();

        /*
         * Dump the local database
         */

        $dumpRemoteSuccess = $this->dumpRemoteDatabase($statusCallback, $remoteDumpFilePath);
        if( !$dumpRemoteSuccess ) return false;

        /*
         * Copy the file to local machine
         */

        $copyDumpSuccess = $this->copyDumpFileToLocal($statusCallback, $remoteDumpFilePath, $localDumpFilePath);
        if(!$copyDumpSuccess) return false;

        /*
         * Delete the remote dump file
         */

        $this->deleteRemoteDumpFile($statusCallback, $remoteDumpFilePath);

        /*
         * Import the sql file
         */

        $importLocalSqlSuccess = $this->importLocalDumpFile($statusCallback, $localDumpFilePath);
        if(!$importLocalSqlSuccess) return false;

        /*
         * Delete local dump file
         */

        $this->deleteLocalDumpFile($statusCallback, $localDumpFilePath);

        /*
         * Database Search & Replace
         */

        $dbSrSuccess = $this->localDatabaseSearchReplace($statusCallback);
        if(!$dbSrSuccess) return false;

        return true;
    }

    /**
     * @param Status $status
     * @param \Closure|null $statusCallback
     */
    private function doStatusCallback(Status $status, $statusCallback) {
        if(!$statusCallback) return;
        else $statusCallback($status);
    }
}
