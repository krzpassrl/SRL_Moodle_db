<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the class setaskment
 *
 * This class provides all the functionality for the new setask module.
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/accesslib.php');
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');
require_once($CFG->dirroot . '/mod/setask/mod_form.php');
require_once($CFG->dirroot . '/mod/setask/locallib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/setask/feedbackplugin.php');
require_once($CFG->dirroot . '/mod/setask/spheresubmission.php');
require_once($CFG->dirroot . '/mod/setask/submissionplugin.php');
require_once($CFG->dirroot . '/mod/setask/renderable.php');
require_once($CFG->dirroot . '/mod/setask/gradingtable.php');
require_once($CFG->libdir . '/eventslib.php');
require_once($CFG->libdir . '/portfolio/caller.php');
require_once($CFG->dirroot . '/mod/setask/sphereengine/autoload.php');
require_once($CFG->dirroot . '/lib/dml/moodle_database.php');

/**
 * Standard base class for mod_setask (setaskment types).
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setask_sphere {
public $TOKEN="dadeea82cdd4b840cbde18858d9f17d6fb11a957";
//public function 
//to be used on add sphere assignment page
//returns the list of problems available
#poodle_database.phpublic 

function sphereProblemsToForm($problems)
{
	$problemsRetranslated;
	foreach($problems as $problem)
	{
		$newItem = $problem['code'] . ' ' . $problem['name'];
	$problemsRetranslated[] = $newItem;
		
	}
return $problemsRetranslated;
}

function sphereSaveProblems($serverOutput)
{
global $DB;
	$table = 'setask_problems';
	#var_dump($serverOutput);
	foreach($serverOutput['items'] as $item)
	{
	    $record = new stdClass();
		$record->code = $item['code'];
		$record->name = substr($item['name'],0,20);
		$record->url = $item['uri'];
	#	var_dump($record);
		//var_dump($item);
		if (!$DB->record_exists($table, ['code' => $record->code, 'name' => $record->name, 'url' =>$record->url]))
			{
			try {
				$transaction = $DB->start_delegated_transaction();
				// Insert a record
				$DB->insert_record($table, $record);
				// Assuming the both inserts work, we get to the following line.
				$transaction->allow_commit();
			} 
			catch(Exception $e)
			{
				$transaction->rollback($e);
			}
		}
		
	}
}

public function sphereGetProblems()
{
$seProblemsApi = new SphereEngine\Api($this->TOKEN, "v3", "problems");
$seProblemsClient = $seProblemsApi->getProblemsClient();
$serverOutput = $seProblemsClient->problems->all();//get("121");#['items'];
	//	var_dump($res);
//to be changed to sphere lib
/*$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, "http://problems.sphere-engine.com/api/v3/problems?access_token=dadeea82cdd4b840cbde18858d9f17d6fb11a957");//will use $TOKEN instead
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$serverOutput = curl_exec ($curl);
*/
#$outputDecoded = json_decode($serverOutput,true);
#var_dump($serverOutput);
#var_dump($serverOutput['items']);
$this->sphereSaveProblems($serverOutput);
return $serverOutput['items'];
}



/*
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://problems.sphere-engine.com/api/v3/problems?access_token=dadeea82cdd4b840cbde18858d9f17d6fb11a957");
curl_setopt($ch, CURLOPT_POST, 1);
#curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output2 = curl_exec ($ch);
curl_close ($ch);
$result2 = json_decode($server_output2, true);
var_dump($result2);*/

public function getProblemsToForm(){
	#echo("test");
	$problems = $this->sphereGetProblems();
	return $this->sphereProblemsToForm($problems);
}
public function sendSphereSubmission(){
	

}

public function sphereSaveCompilers($compilersArray)
{ 
global $DB;
#var_dump($DB);
$user = $DB->get_record_sql('SELECT * FROM {user} WHERE id = 3', array(1));
#	var_dump($user);
/*
 <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
        <FIELD NAME="short_name" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="ver" TYPE="char" LENGTH="20" NOTNULL="true" DEFAULT="0" SEQUENCE="false"/>
        <FIELD NAME="name" TYPE="char" LENGTH="60" NOTNULL="true" DEFAULT="0" SEQUENCE="false" />
        <FIELD NAME="ace" TYPE="char" LENGTH="20" NOTNULL="false" DEFAULT="0" SEQUENCE="false" COMMENT="The last time this setaskment submission was modified by a student."/>
        <FIELD NAME="geshi" TYPE="char" LENGTH="20" NOTNULL="false" SEQUENCE="false"/>
        <FIELD NAME="compiler_id" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0" SEQUENCE="false" COMMENT="Sphere engine compiler ID"/>
      </FIELDS>
*/
var_dump($compilersArray);
$table = 'compilers';
$compilersDecoded = $compilersArray;//json_decode($compilersArray);
foreach($compilersDecoded as $compiler)
{
	/*var_dump($compiler);		
	var_dump($compiler->id);
	echo ("\n");
	echo($compiler->short);
	echo ("\n");
	echo($compiler->ver);
	echo ("\n");
	echo($compiler->name);
	echo ("\n");
	echo($compiler->ace);
	echo ("\n");
	echo($compiler->geshi);
	echo ("\n");
	echo($compiler->id);
*/$record = new stdClass();
	$record->short_name = $compiler->short;
	$record->ver = $compiler->ver;
	$record->name = $compiler->name;
	$record->ace = $compiler->ace;
	$record->geshi = $compiler->geshi;
	$record->compiler_id = $compiler->id;
	var_dump($record);
	/*
$record = new stdClass();
#$record->id    = 0
/*$record->name         = 'sampledemo';
$record->ver = '10000';
$record->short_name         = 'sampledemo';
$record->ace         = 'sampledemo';
$record->geshi        = 'sampledemo';
$record->compiler_id = 0;

var_dump($compilersArray);
var_dump($table);
var_dump($record);
try {
     $transaction = $DB->start_delegated_transaction();
     // Insert a record
     $DB->insert_record('setask_compilers', $record);
     // Assuming the both inserts work, we get to the following line.
     $transaction->allow_commit();
} catch(Exception $e) {
     $transaction->rollback($e);
}
}
#$DB->insert_record($table, $record, $returnid=true, $bulk=false) 
*/
}
#var_dump($setasks);
}


public function sphereGetCompilers()
{ 
$seCompilersApi = new SphereEngine\Api("02994435062fb95b5f3e6938e5aa433e", "v3", "languages");//new SphereEngine\Api($TOKEN, "v3", "languages");
$seCompilersClient = $seCompilersApi->getCompilersClient();
$serverOutput = $seCompilersClient->compilers();
$this->sphereSaveCompilers($serverOutput);
return $serverOutput;	
//to be changed to sphere lib
/*$curl = curl_init(); // old version
curl_setopt($curl, CURLOPT_URL, "http://problems.sphere-engine.com/api/v3/compilers?access_token=dadeea82cdd4b840cbde18858d9f17d6fb11a957");//will use $TOKEN instead
curl_setopt($curl, CURLOPT_POST, 1);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
$serverOutput = curl_exec ($curl);
$outputDecoded = json_decode($serverOutput,true);
return $outputDecoded;*/
}
public function sphereAddInstance($returnId, $problemId) {
	return;//changes to be made
	global $DB;
	$table = 'setask_prob_setask';
	$record = new stdClass();
	$record->prob_id = $problemId;
	$record->setask_id= $returnId;
	
	/*
#	$adminconfig = $this->get_admin_config();

	#$err = '';

	// Add the database record.
	$update = new stdClass();
	$update->name = $formdata->name;
	$update->timemodified = time();
	$update->timecreated = time();
	$update->course = $formdata->course;
	$update->courseid = $formdata->course;
	$update->intro = $formdata->intro;
	$update->introformat = $formdata->introformat;
	$update->alwaysshowdescription = !empty($formdata->alwaysshowdescription);
	$update->submissiondrafts = $formdata->submissiondrafts;
	$update->requiresubmissionstatement = $formdata->requiresubmissionstatement;
	$update->sendnotifications = $formdata->sendnotifications;
	$update->sendlatenotifications = $formdata->sendlatenotifications;
	$update->sendstudentnotifications = $adminconfig->sendstudentnotifications;
	if (isset($formdata->sendstudentnotifications)) {
		$update->sendstudentnotifications = $formdata->sendstudentnotifications;
	}
	$update->duedate = $formdata->duedate;
	$update->cutoffdate = $formdata->cutoffdate;
	$update->allowsubmissionsfromdate = $formdata->allowsubmissionsfromdate;
	$update->grade = $formdata->grade;
	$update->completionsubmit = !empty($formdata->completionsubmit);
	$update->teamsubmission = $formdata->teamsubmission;
	$update->requireallteammemberssubmit = $formdata->requireallteammemberssubmit;
	if (isset($formdata->teamsubmissiongroupingid)) {
		$update->teamsubmissiongroupingid = $formdata->teamsubmissiongroupingid;
	}
	$update->blindmarking = $formdata->blindmarking;
	$update->attemptreopenmethod = ASSIGN_ATTEMPT_REOPEN_METHOD_NONE;
	if (!empty($formdata->attemptreopenmethod)) {
		$update->attemptreopenmethod = $formdata->attemptreopenmethod;
	}
	if (!empty($formdata->maxattempts)) {
		$update->maxattempts = $formdata->maxattempts;
	}
	if (isset($formdata->preventsubmissionnotingroup)) {
		$update->preventsubmissionnotingroup = $formdata->preventsubmissionnotingroup;
	}
	$update->markingworkflow = $formdata->markingworkflow;
	$update->markingallocation = $formdata->markingallocation;
	if (empty($update->markingworkflow)) { // If marking workflow is disabled, make sure allocation is disabled.
		$update->markingallocation = 0;
	}

	$returnid = $DB->insert_record('setask', $update);
	$this->instance = $DB->get_record('setask', array('id'=>$returnid), '*', MUST_EXIST);
	// Cache the course record.
	$this->course = $DB->get_record('course', array('id'=>$formdata->course), '*', MUST_EXIST);

	$this->save_intro_draft_files($formdata);

	if ($callplugins) {
		// Call save_settings hook for submission plugins.
		foreach ($this->submissionplugins as $plugin) {
			if (!$this->update_plugin_instance($plugin, $formdata)) {
				print_error($plugin->get_error());
				return false;
			}
		}
		foreach ($this->feedbackplugins as $plugin) {
			if (!$this->update_plugin_instance($plugin, $formdata)) {
				print_error($plugin->get_error());
				return false;
			}
		}

		// In the case of upgrades the coursemodule has not been set,
		// so we need to wait before calling these two.
		$this->update_calendar($formdata->coursemodule);
		$this->update_gradebook(false, $formdata->coursemodule);

	}

	$update = new stdClass();
	$update->id = $this->get_instance()->id;
	$update->nosubmissions = (!$this->is_any_submission_plugin_enabled()) ? 1: 0;
	$DB->update_record('setask', $update);

	return $returnid;*/
}
}
