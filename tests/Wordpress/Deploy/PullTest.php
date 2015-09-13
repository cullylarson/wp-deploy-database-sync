<?php

namespace Test\Cully\Ssh;

use Wordpress\Deploy\DatabaseSync;
use Wordpress\Deploy\DatabaseSync\CommandUtil;
use Cully\Ssh;

// TODO -- test with optional options
// TODO -- I'm assuming the local db is localhost

class PullTest extends \PHPUnit_Framework_TestCase {
    private $session;
    /**
     * @var \PDO
     */
    private $dbh;

    public function setUp() {
        /*
         * Create an ssh connection
         */

        $session = @ssh2_connect(getenv("SSH_HOST"), getenv("SSH_PORT"), array('hostkey'=>'ssh-rsa'));

        if(!is_resource($session) ) {
            $this->markTestSkipped("Couldn't connect.");
        }

        if( !@ssh2_auth_agent($session, getenv("SSH_USER")) ) {
            $this->markTestSkipped("Couldn't authenticate. You might need to: eval `ssh-agent -s` && ssh-add");
        }

        $this->session = $session;

        /*
         * Connect to local database
         */

        try {
            $this->dbh = $dbh = new \PDO(sprintf("mysql:host=%s;dbname=%s", getenv("LOCAL_MYSQL_HOST"), getenv("LOCAL_MYSQL_NAME")), getenv("LOCAL_MYSQL_USER"), getenv("LOCAL_MYSQL_PASS"));
        }
        catch(Exception $e) {
            $this->markTestSkipped("Couldn't connect to local database: {$e}");
        }

        /*
         * Create remote table
         */

        $scmd = new Ssh\Command($this->session);
        $remoteQueryCommandBase = CommandUtil::buildMysqlCommand($this->getDbSyncParams()['remote']['db']);

        $remoteQueryCommandCreateTable = "{$remoteQueryCommandBase} -e 'CREATE TABLE wp_deploy_pushtest (
                id  INT UNSIGNED  NOT NULL  AUTO_INCREMENT,
                name VARCHAR(255) NULL,
                content TEXT NULL,
                PRIMARY KEY (id)
            )'";

        $scmd->exec($remoteQueryCommandCreateTable);

        if($scmd->failure()) {
            $this->markTestSkipped("Couldn't create remote test table.");
        }

        /*
         * Insert some database values
         */

        $remoteQueryCommandInsertData = "{$remoteQueryCommandBase} -e 'INSERT INTO wp_deploy_pushtest (name, content) VALUES (\"test_value_one\", \"test_content_one\")'";

        $scmd->exec($remoteQueryCommandInsertData);

        if($scmd->failure()) {
            $this->markTestSkipped("Couldn't insert data into remote test table.");
        }
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
                'srdb' => getenv("LOCAL_SRDB"),
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

        $dbSync->pull();

        $result = $this->dbh->query("select name, content from wp_deploy_pushtest");

        $this->assertNotFalse($result);

        $row = $result->fetch();

        $this->assertNotFalse($row);

        $this->assertEquals("test_value_one", $row['name']);
        $this->assertEquals("test_content_one", $row['content']);
    }

    public function testSearchReplace() {
        $options = $this->getDbSyncParams();

        $options['search_replace'] = [
            'value_one' => 'replaced_value_one',
            'content_one' => 'replaced_content_one',
        ];

        $dbSync = new DatabaseSync($options);

        $dbSync->pull();

        $result = $this->dbh->query("select name, content from wp_deploy_pushtest");

        $this->assertNotFalse($result);

        $row = $result->fetch();

        $this->assertNotFalse($row);

        $this->assertEquals("test_replaced_value_one", $row['name']);
        $this->assertEquals("test_replaced_content_one", $row['content']);
    }
}
