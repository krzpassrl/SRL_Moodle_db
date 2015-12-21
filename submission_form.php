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
 * This file contains the submission form used by the setask module.
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/setask/locallib.php');
require_once($CFG->dirroot . '/mod/setask/sphereengine/autoload.php');

/**
 * Assign submission form
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_setask_submission_form extends moodleform {

    /**
     * Define this form - called by the parent constructor
     */
    public function definition() {

    	$sphereLib = new setask_sphere();
    	$compilers = $sphereLib->sphereGetCompilers(); //to be improved (maybe... - probably no need to rewrite here)
   # 	var_dump($compilers);
        $mform = $this->_form;

        list($setask, $data) = $this->_customdata;
        $mform->addElement('select', 'compiler', 'Compilers: ',$compilers	);
		
        $setask->add_submission_form_elements($mform, $data);

        $this->add_action_buttons(true, get_string('savechanges', 'setask'));
        if ($data) {
			//ugly (but working) way of extracting data from text editor
        	foreach($data as $tes){
        		$ii =0;
        	
        		foreach($tes as $tes2)
        		{
        			if($ii == 0)
        				$textToSend = $tes2;//text from online editor - ugly way but it works...
        			#	var_dump($textToSend);
        				$ii++;
        		}
        	
        	}
        	
        	//extracting selected compiler 
        	$selectedItem  =& $mform->getElement('compiler')->getSelected();
        #	var_dump($selectedItem);
            $this->set_data($data);
        }
        
    }
}

