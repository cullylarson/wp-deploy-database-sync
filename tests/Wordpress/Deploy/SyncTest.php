<?php

namespace Test\Cully\Ssh;

use Wordpress\Deploy\DatabaseSync;
use Wordpress\Deploy\DatabaseSync\CommandUtil;
use Cully\Ssh;

// TODO -- test with optional options

class SyncTest extends \PHPUnit_Framework_TestCase
{
    private $destSsh;
    private $sourceSsh;
    private $sourceDbh;
    private $destDbh;

    public function setUp()
    {
        /*
         * Create dest ssh connection
         */

        $destSsh = @ssh2_connect(getenv("SSH_HOST"), getenv("SSH_PORT"), array('hostkey' => 'ssh-rsa'));

        if (!is_resource($destSsh)) {
            $this->markTestSkipped("Couldn't connect to dest.");
        }

        if (!@ssh2_auth_agent($destSsh, getenv("SSH_USER"))) {
            $this->markTestSkipped("Couldn't authenticate dest. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->destSsh = $destSsh;

        /*
         * Create source ssh connection
         */

        $sourceSsh = @ssh2_connect(getenv("SSH_HOST"), getenv("SSH_PORT"), array('hostkey' => 'ssh-rsa'));

        if (!is_resource($sourceSsh)) {
            $this->markTestSkipped("Couldn't connect to source.");
        }

        if (!@ssh2_auth_agent($sourceSsh, getenv("SSH_USER"))) {
            $this->markTestSkipped("Couldn't authenticate source. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->sourceSsh = $sourceSsh;

        /*
         * Set up the local dest database connection
         */

        try {
            $this->sourceDbh = $sourceDbh = new \PDO(
                sprintf("mysql:host=%s;dbname=%s",
                    $this->getMachineParams()['local']['dest']['db']['host'],
                    $this->getMachineParams()['local']['dest']['db']['name']),
                $this->getMachineParams()['local']['dest']['db']['username'],
                $this->getMachineParams()['local']['dest']['db']['password']);
        } catch (\Exception $e) {
            $this->markTestSkipped("Couldn't connect to local source database: {$e}");
            return;
        }

        /*
         * Set up the local source database
         */

        try {
            $this->sourceDbh = $sourceDbh = new \PDO(
                sprintf("mysql:host=%s;dbname=%s",
                    $this->getMachineParams()['local']['source']['db']['host'],
                    $this->getMachineParams()['local']['source']['db']['name']),
                $this->getMachineParams()['local']['source']['db']['username'],
                $this->getMachineParams()['local']['source']['db']['password']);
        } catch (\Exception $e) {
            $this->markTestSkipped("Couldn't connect to local source database: {$e}");
            return;
        }

        $sourceDbh->query("
            CREATE TABLE wp_deploy_synctest (
                id  INT UNSIGNED  NOT NULL  AUTO_INCREMENT,
                name VARCHAR(255) NULL,
                content TEXT NULL,
                PRIMARY KEY (id)
            )
        ") or $this->markTestSkipped("Couldn't create local source test table.");

        // Insert some database values

        $sourceDbh->query("INSERT INTO wp_deploy_synctest (name, content) VALUES ('test_value_one', 'test_content_one')")
            or $this->markTestSkipped("Couldn't add data to local source test table.");

        /*
         * Set up remote source database
         */

        $remoteSourceCmd = new Ssh\Command($this->destSsh);

        $remoteMysql = CommandUtil::buildMysqlCommand($this->getMachineParams()['remote']['source']['db']);
        $remoteCreateCommand = "{$remoteMysql} -e 'CREATE TABLE wp_deploy_synctest (
                id  INT UNSIGNED  NOT NULL  AUTO_INCREMENT,
                name VARCHAR(255) NULL,
                content TEXT NULL,
                PRIMARY KEY (id)
            )'";
        $remoteSourceCmd->exec($remoteCreateCommand);

        if($remoteSourceCmd->failure()) $this->markTestSkipped("Couldn't create remote source test table.");

        // Insert some database values

        $destInsertCommand = "{$remoteMysql} -e 'INSERT INTO wp_deploy_synctest (name, content) VALUES (\"test_value_one\", \"test_content_one\")'";
        $remoteSourceCmd->exec($destInsertCommand);
        if($remoteSourceCmd->failure()) $this->markTestSkipped("Couldn't insert data into remote source test table.");

    }

    public function tearDown() {
        /*
         * Get rid of the local dest database stuff
         */

        if( $this->destDbh ) {
            $this->destDbh->query("DROP TABLE wp_deploy_synctest");
        }

        /*
         * Get rid of the local source database stuff
         */

        if( $this->sourceDbh ) {
            $this->sourceDbh->query("DROP TABLE wp_deploy_synctest");
        }

        /*
         * Get rid of remote dest database stuff
         */

        if(is_resource($this->destSsh)) {
            $scmd = new Ssh\Command($this->destSsh);

            $remoteQueryCommand = CommandUtil::buildMysqlCommand($this->getMachineParams()['remote']['dest']['db']);
            $remoteQueryCommand .= " -e 'drop table wp_deploy_synctest'";
            $scmd->exec($remoteQueryCommand);
        }

        /*
         * Get rid of remote source database stuff
         */

        if(is_resource($this->sourceSsh)) {
            $scmd = new Ssh\Command($this->sourceSsh);

            $remoteQueryCommand = CommandUtil::buildMysqlCommand($this->getMachineParams()['remote']['source']['db']);
            $remoteQueryCommand .= " -e 'drop table wp_deploy_synctest'";
            $scmd->exec($remoteQueryCommand);
        }
    }

    private function getMachineParams() {
        return [
            'local' => [
                'source' => [
                    'tmp' => getenv("LOCAL_SOURCE_TMP"),
                    'srdb' => getenv("LOCAL_SOURCE_SRDB"),
                    'local' => true,
                    'db' => [
                        'host' => getenv("LOCAL_SOURCE_MYSQL_HOST"),
                        'username' => getenv("LOCAL_SOURCE_MYSQL_USER"),
                        'password' => getenv("LOCAL_SOURCE_MYSQL_PASS"),
                        'name' => getenv("LOCAL_SOURCE_MYSQL_NAME"),
                        'port' => getenv("LOCAL_SOURCE_MYSQL_PORT"),
                    ],
                ],
                'dest' => [
                    'tmp' => getenv("LOCAL_DEST_TMP"),
                    'srdb' => getenv("LOCAL_DEST_SRDB"),
                    'local' => true,
                    'db' => [
                        'host' => getenv("LOCAL_DEST_MYSQL_HOST"),
                        'username' => getenv("LOCAL_DEST_MYSQL_USER"),
                        'password' => getenv("LOCAL_DEST_MYSQL_PASS"),
                        'name' => getenv("LOCAL_DEST_MYSQL_NAME"),
                        'port' => getenv("LOCAL_DEST_MYSQL_PORT"),
                    ],
                ],
            ],
            'remote' => [
                'source' => [
                    'ssh' => $this->sourceSsh,
                    'tmp' => getenv("REMOTE_DEST_TMP"),
                    'srdb' => getenv("REMOTE_DEST_SRDB"),
                    'db' => [
                        'host' => getenv("REMOTE_DEST_MYSQL_HOST"),
                        'username' => getenv("REMOTE_DEST_MYSQL_USER"),
                        'password' => getenv("REMOTE_DEST_MYSQL_PASS"),
                        'name' => getenv("REMOTE_DEST_MYSQL_NAME"),
                        'port' => getenv("REMOTE_DEST_MYSQL_PORT"),
                    ],
                ],
                'dest' => [
                    'ssh' => $this->destSsh,
                    'tmp' => getenv("REMOTE_SOURCE_TMP"),
                    'srdb' => getenv("REMOTE_SOURCE_SRDB"),
                    'db' => [
                        'host' => getenv("REMOTE_SOURCE_MYSQL_HOST"),
                        'username' => getenv("REMOTE_SOURCE_MYSQL_USER"),
                        'password' => getenv("REMOTE_SOURCE_MYSQL_PASS"),
                        'name' => getenv("REMOTE_SOURCE_MYSQL_NAME"),
                        'port' => getenv("REMOTE_SOURCE_MYSQL_PORT"),
                    ],
                ],
            ]
        ];
    }

    public function getLocalToRemoteOptions() {
        return [
            // local source
            'source' => $this->getMachineParams()['local']['source'],
            // remote dest
            'dest' => $this->getMachineParams()['remote']['dest'],
        ];
    }

    public function testBasicSync() {
        $options = $this->getLocalToRemoteOptions();
        $dbSync = new DatabaseSync($options);

        $dbSync->sync();

        $scmd = new Ssh\Command($this->destSsh);
        $remoteQueryCommand = CommandUtil::buildMysqlCommand($options['dest']['db']);
        $remoteQueryCommand .= " -e 'select name, content from wp_deploy_synctest'";
        $scmd->exec($remoteQueryCommand);

        $this->assertTrue($scmd->success());

        $this->assertRegExp("/test_value_one\ttest_content_one/", $scmd->getOutput());
    }
}