<?php

namespace Wordpress\Deploy\DatabaseSync;

use Wordpress\Deploy\DatabaseSync\Options;
use Wordpress\Deploy\DatabaseSync\ExportFile;

use Cully\Local;
use Cully\Ssh;

// TODO -- refactor so that pusher and puller can share some functions

class Pusher {
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
     * @param $localDumpFilePath
     * @return bool
     */
    private function dumpLocalDatabase($statusCallback, $localDumpFilePath) {
        $this->doStatusCallback(new Status("Dumping local database to file.", Status::MT_NOTICE), $statusCallback);
        $dumpCommand = CommandUtil::buildDumpCommand($this->localDb, $localDumpFilePath);
        $lcmd = new Local\Command();
        $lcmd->exec($dumpCommand);

        // failed
        if($lcmd->failure()) {
            $this->doStatusCallback(new Status("Error encountered while running the dump command (Exit Status: {$lcmd->getExitStatus()})", Status::MT_ERROR), $statusCallback);
            $this->doStatusCallback(new Status($lcmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            return false;
        }

        return true;
    }

    /**
     * @param $statusCallback
     * @param $localDumpFilePath
     * @param $remoteDumpFilePath
     * @return bool
     */
    private function copyDumpFileToServer($statusCallback, $localDumpFilePath, $remoteDumpFilePath) {
        $this->doStatusCallback(new Status("Copying dump file to remote server.", Status::MT_NOTICE), $statusCallback);

        $copySuccessful = ssh2_scp_send($this->remote['ssh'], $localDumpFilePath, $remoteDumpFilePath);

        // failed
        if( !$copySuccessful ) {
            $this->doStatusCallback(new Status("Failed to send dump file to remote server (local:{$localDumpFilePath} -> remote:{$remoteDumpFilePath})", Status::MT_ERROR), $statusCallback);
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
     * @param $remoteDumpFilePath
     * @return bool
     */
    private function importRemoteDumpFile($statusCallback, $remoteDumpFilePath) {
        $this->doStatusCallback(new Status("Importing remote dump file ({$remoteDumpFilePath}).", Status::MT_NOTICE), $statusCallback);

        $scmd = new Ssh\Command($this->remote['ssh']);

        $importCommand = CommandUtil::buildImportCommandFromGunzipFile($this->remoteDb, $remoteDumpFilePath);
        $scmd->exec($importCommand);

        if($scmd->failure()) {
            $this->doStatusCallback(new Status("Error encountered while importing dump file into mysql (Exit Status: {$scmd->getExitStatus()})", Status::MT_ERROR), $statusCallback);
            $this->doStatusCallback(new Status($scmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
            return false;
        }
        else {
            $this->doStatusCallback(new Status($scmd->getOutput(), Status::MT_RAW_OUTPUT), $statusCallback);
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

    private function remoteDatabaseSearchReplace($statusCallback) {
        if($this->options->shouldDoSearchReplace()) {
            $this->doStatusCallback(new Status("Performing database search & replace.", Status::MT_NOTICE), $statusCallback);

            $scmd = new Ssh\Command($this->remote['ssh']);

            foreach($this->options->getSearchReplace() as $search => $replace) {
                $this->doStatusCallback(new Status("Database search & replace: {$search} -> {$replace}", Status::MT_NOTICE), $statusCallback);

                $srCommand = CommandUtil::buildSrdbCommand($this->remote['srdb'], $this->remoteDb, $search, $replace);
                $scmd->exec($srCommand);

                if($scmd->failure()) {
                    $this->doStatusCallback(new Status("Error while performing search and replace.", Status::MT_ERROR), $statusCallback);
                    $this->doStatusCallback(new Status($scmd->getError(), Status::MT_RAW_ERROR_OUTPUT), $statusCallback);
                    return false;
                }
            }
        }
        else {
            $this->doStatusCallback(new Status("Skipping database search & replace because none have been provided.", Status::MT_NOTICE), $statusCallback);
        }

        return true;
    }

    public function push($statusCallback=null) {
        $localDumpFilePath = $this->local['tmp'] . "/" . $this->exportFile->getGzipFilename();
        $remoteDumpFilePath = $this->remote['tmp'] . "/" . $this->exportFile->getGzipFilename();

        /*
         * Dump the local database
         */

        $dumpLocalSuccess = $this->dumpLocalDatabase($statusCallback, $localDumpFilePath);
        if( !$dumpLocalSuccess ) return false;

        /*
         * Copy the file to the server
         */

        $copyDumpSuccess = $this->copyDumpFileToServer($statusCallback, $localDumpFilePath, $remoteDumpFilePath);
        if(!$copyDumpSuccess) return false;

        /*
         * Delete local dump file
         */

        $this->deleteLocalDumpFile($statusCallback, $localDumpFilePath);

        /*
         * Import the sql file
         */

        $importRemoteSqlSuccess = $this->importRemoteDumpFile($statusCallback, $remoteDumpFilePath);
        if(!$importRemoteSqlSuccess) return false;

        /*
         * Delete the remote dump file
         */

        $this->deleteRemoteDumpFile($statusCallback, $remoteDumpFilePath);

        /*
         * Database Search & Replace
         */

        $dbSrSuccess = $this->remoteDatabaseSearchReplace($statusCallback);
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
