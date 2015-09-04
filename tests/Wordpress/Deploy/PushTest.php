<?php

namespace Test\Cully\Ssh;

use Wordpress\Deploy\DatabaseSync;
use Wordpress\Deploy\DatabaseSync\CommandUtil;
use Cully\Ssh;

// TODO -- test with optional options

class PushTest extends \PHPUnit_Framework_TestCase {
    private $session;
    private $dbh;
    private $failed = false;//stub

    public function setUp() {
        /*
         * Create an ssh connection
         */

        $session = @ssh2_connect(getenv("SSH_HOST"), getenv("SSH_PORT"), array('hostkey'=>'ssh-rsa'));

        if(!is_resource($session) ) {
            $this->markTestSkipped("Couldn't connect.");
        }

        if( !@ssh2_auth_agent($session, getenv("SSH_USER")) ) {
            $this->failed = true;
            $this->markTestSkipped("Couldn't authenticate. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->session = $session;

        /*
         * Set up the local database
         */

        try {
            $this->dbh = $dbh = new \PDO(sprintf("mysql:host=%s;dbname=%s", getenv("LOCAL_MYSQL_HOST"), getenv("LOCAL_MYSQL_NAME")), getenv("LOCAL_MYSQL_USER"), getenv("LOCAL_MYSQL_PASS"));
        }
        catch(Exception $e) {
            $this->markTestSkipped("Couldn't connect to local database: {$e}");
        }

        $dbh->query("
            CREATE TABLE wp_deploy_pushtest (
                id  INT UNSIGNED  NOT NULL  AUTO_INCREMENT,
                name VARCHAR(255) NULL,
                content TEXT NULL,
                PRIMARY KEY (id)
            )
        ") or $this->markTestSkipped("Couldn't create local test table.");

        /*
         * Insert some database values
         */

        $dbh->query("INSERT INTO wp_deploy_pushtest (name, content) VALUES ('test_value_one', 'test_content_one')")
            or $this->markTestSkipped("Couldn't add data to local test table.");

    }

    public function tearDown() {
        /*
         * Get rid of the local database stuff
         */

        if( $this->dbh ) {
            $dbh = $this->dbh;

            $dbh->query("DROP TABLE wp_deploy_pushtest") or $this->markTestSkipped("Couldn't drop local test table.");
        }

        /*
         * Get rid of remote database stuff
         */

        if(is_resource($this->session)) {
            $scmd = new Ssh\Command($this->session);

            $remoteQueryCommand = CommandUtil::buildMysqlCommand($this->getDbSyncParams()['remote']['db']);
            $remoteQueryCommand .= " -e 'drop table wp_deploy_pushtest'";
            $scmd->exec($remoteQueryCommand);
        }
    }

    private function getDbSyncParams() {
        return [
            'local' => [
                'tmp' => getenv("LOCAL_TMP"),
                'db' => [
                    'host' => getenv("LOCAL_MYSQL_HOST"),
                    'username' => getenv("LOCAL_MYSQL_USER"),
                    'password' => getenv("LOCAL_MYSQL_PASS"),
                    'name' => getenv("LOCAL_MYSQL_NAME"),
                    'port' => getenv("LOCAL_MYSQL_PORT"),
                ],
            ],
            'remote' => [
                'ssh' => $this->session,
                'tmp' => getenv("REMOTE_TMP"),
                'srdb' => getenv("REMOTE_SRDB"),
                'db' => [
                    'host' => getenv("REMOTE_MYSQL_HOST"),
                    'username' => getenv("REMOTE_MYSQL_USER"),
                    'password' => getenv("REMOTE_MYSQL_PASS"),
                    'name' => getenv("REMOTE_MYSQL_NAME"),
                    'port' => getenv("REMOTE_MYSQL_PORT"),
                ],
            ],
        ];
    }

    public function testBasicSync() {
        $options = $this->getDbSyncParams();
        $dbSync = new DatabaseSync($options);

        $dbSync->push();

        $scmd = new Ssh\Command($this->session);
        $remoteQueryCommand = CommandUtil::buildMysqlCommand($this->getDbSyncParams()['remote']['db']);
        $remoteQueryCommand .= " -e 'select name, content from wp_deploy_pushtest'";
        $scmd->exec($remoteQueryCommand);

        $this->assertTrue($scmd->success());

        $this->assertRegExp("/test_value_one\ttest_content_one/", $scmd->getOutput());
    }

    public function testSearchReplace() {
        $options = $this->getDbSyncParams();

        $options['search_replace'] = [
            'value_one' => 'replaced_value_one',
            'content_one' => 'replaced_content_one',
        ];

        $dbSync = new DatabaseSync($options);

        $dbSync->push();

        $scmd = new Ssh\Command($this->session);
        $remoteQueryCommand = CommandUtil::buildMysqlCommand($this->getDbSyncParams()['remote']['db']);
        $remoteQueryCommand .= " -e 'select name, content from wp_deploy_pushtest'";
        $scmd->exec($remoteQueryCommand);

        $this->assertTrue($scmd->success());

        $this->assertRegExp("/test_replaced_value_one\ttest_replaced_content_one/", $scmd->getOutput());
    }
}
