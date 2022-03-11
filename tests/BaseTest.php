<?php namespace STPH\pdfInjector;

use \ExternalModules\ExternalModules;
use \Exception;
use \Project;

require_once __DIR__ . '/../../../redcap_connect.php';

abstract class BaseTest extends \ExternalModules\ModuleBaseTest {

    protected function callPrivateMethod($name, array $args) {
        $obj =  $this->module;
        $class = new \ReflectionClass($obj);
        $method = $class->getMethod($name);
        $method->setAccessible(true);
        return $method->invokeArgs($obj, $args);
    }

    static function setUpBeforeClass():void{

        if(!self::hasTestProjects()) {

            self::setupTestProjects();

        }

    }

    protected static function echo($message)
    {
        // if output buffer has not started yet
        if (ob_get_level() == 0) {
            // current buffer existence
            $hasBuffer = false;
            // start the buffer
            ob_start();
        } else {
            // current buffer existence
            $hasBuffer = true;
        }

        // echo to output
        echo $message;

        // flush current buffer to output stream
        ob_flush();
        flush();
        ob_end_flush();

        // if there were a buffer before this method was called
        //      in my version of PHPUNIT it has its own buffer running
        if ($hasBuffer) {
            // start the output buffer again
            ob_start();
        }
    }

    
    //  Check if system has test projects
    private static function hasTestProjects(){

        $sql = "SELECT *  FROM redcap_config WHERE field_name = 'external_modules_test_pids'";
        $result  = ExternalModules::query($sql, []);

        if($result->num_rows > 0) {
            return true;
        } else {
            return false;
        }

    }

    function getTestPID(){
        return ExternalModules::getTestPIDs()[0];
    }


    //  Setup test projects
    static function setupTestProjects() {

        $pid1 = self::createTestProject('External Module Unit Test Project 1');
        $pid2 = self::createTestProject('External Module Unit Test Project 2');
        
        $value = $pid1.",".$pid2;

        $sql = "insert into redcap_config values ('external_modules_test_pids', ?)";
        
        ExternalModules::query($sql, [$value]);
    }

    
    //  Create test project
    static function createTestProject($title){

        $title = Project::cleanTitle($title);
        $new_app_name = Project::getValidProjectName($title);

        $purpose = 0;
        $project_note = 'Unit Testing';

        $ui_id = NULL;
        //$ui_id = 1;
        $ui_name = NULL;
        //$ui_name = 'site_admin';
        $auto_inc_set = 1;
        $GLOBALS['__SALT__'] = substr(sha1(rand()), 0, 10);

        ExternalModules::query("insert into redcap_projects (project_name, purpose, app_title, creation_time, created_by, auto_inc_set, project_note,auth_meth,__SALT__) values(?,?,?,?,?,?,?,?,?)",
            [$new_app_name,$purpose,$title,NOW,$ui_id,$auto_inc_set,trim($project_note),'none',$GLOBALS['__SALT__']]);            

        // Get this new project's project_id
        $new_project_id = db_insert_id();

        // Insert project defaults into redcap_projects
        Project::setDefaults($new_project_id);

		Project::insertDefaultArmAndEvent($new_project_id);

        // Now add the new project's metadata
		createMetadata($new_project_id);

        return $new_project_id;

    }
    
    //  Cleanup after last test
    static function tearDownAfterClass():void{
        
        //self::cleanupTestProjects();

        //  Delete temporary data
        $dirname = __DIR__ . "/tmp";
        array_map('unlink', glob("$dirname/*.*"));
        rmdir($dirname);
    }


    //  Cleanup test projects
    static function cleanupTestProjects() {
        ExternalModules::query(
            "DELETE FROM `redcap_projects` WHERE `project_id`= ? OR `project_id`=?", 
            [
                ExternalModules::getTestPIDs()[0],
                ExternalModules::getTestPIDs()[1]
            ]
        );

        ExternalModules::query(
            "DELETE FROM `redcap_config` WHERE  `field_name`='external_modules_test_pids'", []
        );
    }    


}