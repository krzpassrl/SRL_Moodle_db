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


function sphereSaveTestCase()
{

}
function sphereSaveProblemDetails($code, $problemId)
{
global $DB;
$table = 'setask_prob_desc';
	$seProblemsApi = new SphereEngine\Api($this->TOKEN, "v3", "problems");
	$seProblemsClient = $seProblemsApi->getProblemsClient();#
	
	$serverOutput = $seProblemsClient->problems->get($code);//$codes);
#var_dump($serverOutput);
	$record = new stdClass();
	$record->prob_id = $problemId;
	$sphereBody = $serverOutput['body'];
	$x = $serverOutput['testcases'];
var_dump($x);
$mysqli = new mysqli();
#;;$sphereBodyFixed = $mysqli->escape_string(
$sphereBodyFixed = addslashes($sphereBody);
#var_dump($sphereBody);

	$record->testcases_id =  1;// $serverOutput['type'];
	$record->type = $serverOutput['type'];	
$record->desc = "test";#$sphereBody;#Fixed;
#var_dump($sphereBodyFixed);
#;substr($serverOutput['body'], 0, 1200); # database storage should be changed
						#- some descriptions are longer than db's varchar length limit
	if (!$DB->record_exists($table, ['prob_id' => $record->prob_id]))
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
			#	var_dump($e);
			}
		}
}
function sphereSaveProblems($serverOutput)
{
global $DB;
	$table = 'setask_problems';
	foreach($serverOutput['items'] as $item)
	{
	    $record = new stdClass();
		$record->code = $item['code'];
		$record->name = $item['name'];#,0,20;
		$record->url = $item['uri'];#,0,20);
		#$record->testacases_id = NULL; // to be changed
		$code = $item['code'];
		if (!$DB->record_exists($table, ['code' => $record->code, 'name' => $record->name, 'url' =>$record->url]))
			{
			try {
				$transaction = $DB->start_delegated_transaction();
				// Insert a record
				$problemId = $DB->insert_record($table, $record);
				// Assuming the both inserts work, we get to the following line.
				$transaction->allow_commit();
				#$this->sphereSaveProblemDetails($code,$problemId);
				
			} 
			catch(Exception $e)
			{
				$transaction->rollback($e);
			}
			try {
				$transaction = $DB->start_delegated_transaction();
				// Insert a record
				//$problemId = $DB->insert_record($table, $record);
				// Assuming the both inserts work, we get to the following line.
var_dump($record);
$this->sphereSaveProblemDetails($code,$problemId);

				$transaction->allow_commit();
#				$this->sphereSaveProblemDetails($code,$problemId);
				
			} 
			catch(Exception $e)
			{
				$transaction->rollback($e);
			}
		}
		else {

			try {
				$transaction = $DB->start_delegated_transaction();
				// Insert a record
			//	$problemId = $DB->update_record($table, $record);
			//	$this->sphereSaveProblemDetails($code,$problemId);
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
$serverOutput = $seProblemsClient->problems->all(30);
$this->sphereSaveProblems($serverOutput);
return $serverOutput['items'];
}



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
/*#var_dump($DB);
#$user = $DB->get_record_sql('SELECT * FROM {user} WHERE id = 3', array(1));
#var_dump($compilersArray);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://api.compilers.sphere-engine.com/api/v3/languages?access_token=02994435062fb95b5f3e6938e5aa433e");
curl_setopt($ch, CURLOPT_POST, 1);
#curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
// curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$server_output = curl_exec ($ch);
#var_dump($server_output);
#var_dump(json_decode($server_output))*/;
$table = 'setask_compilers';
$compilersDecoded = $compilersArray;//json_decode($compilersArray);
#var_dump($compilersDecoded);
foreach($compilersDecoded['items'] as $compiler)
{
	$record = new stdClass();
	$record->short_name = $compiler->short;
	$record->ver = $compiler->ver;
	$record->name = $compiler->name;
	$record->ace = $compiler->ace;
	$record->geshi = $compiler->geshi;
	$record->compiler_id = $compiler->id;
	/*var_dump($record);
	$test = '<p> nowy rekord <p>';
	var_dump($test);*/
	if (!$DB->record_exists($table, ['short_name' => $record->short_name, 
	'ver' => $record->ver, 'name' =>$record->name ,
	'ace' => $record->ace, 
	'geshi' => $record->geshi, 'compiler_id' =>$record->id]))
	{
		try {
			$transaction = $DB->start_delegated_transaction();
			// Insert a record
			$DB->insert_record($table, $record);
			$transaction->allow_commit();
	
		}
		catch(Exception $e)
		{
			$transaction->rollback($e);
		}
	}
	else{
	
	#	var_dump($record);
		#die();
	}
	
}

}


public function sphereGetCompilers()
{ 
$seCompilersApi = new SphereEngine\Api("02994435062fb95b5f3e6938e5aa433e", "v3", "languages");//new SphereEngine\Api($TOKEN, "v3", "languages");
$seCompilersClient = $seCompilersApi->getCompilersClient();
$serverOutput = $seCompilersClient->compilers();
$this->sphereSaveCompilers($serverOutput);
return $serverOutput;	
}
public function sphereAddInstance($returnId, $problemId) {
	return;//changes to be made
	global $DB;
	$table = 'setask_prob_setask';
	$record = new stdClass();
	var_dump($problemId);
	$record->prob_id = 1;//	$problemId;
	$record->setask_id = $returnId;
	var_dump($record);
	if (!$DB->record_exists($table, ['prob_id' => $record->prob_id, 'setask_id' => $record->setask_id, 'url' =>$record->url]))
	{
		try {
			$transaction = $DB->start_delegated_transaction();
			// Insert a record
			$DB->insert_record($table, $record);
			$transaction->allow_commit();
	
		}
		catch(Exception $e)
		{
			$transaction->rollback($e);
		}
	}
	else{
		
		var_dump($record);
		die();
	}
}
}
