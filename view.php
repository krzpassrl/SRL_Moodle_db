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
 * This file is the entry point to the setask module. All pages are rendered from here
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/setask/locallib.php');

$id = required_param('id', PARAM_INT);

$urlparams = array('id' => $id,
                  'action' => optional_param('action', '', PARAM_TEXT),
                  'rownum' => optional_param('rownum', 0, PARAM_INT),
                  'useridlistid' => optional_param('action', 0, PARAM_INT));

$url = new moodle_url('/mod/setask/view.php', $urlparams);

list ($course, $cm) = get_course_and_cm_from_cmid($id, 'setask');

require_login($course, true, $cm);
$PAGE->set_url($url);

$context = context_module::instance($cm->id);

require_capability('mod/setask:view', $context);

$setask = new setask($context, $cm, $course);

$completion=new completion_info($course);
$completion->set_module_viewed($cm);

// Get the setask class to
// render the page.
echo $setask->view(optional_param('action', '', PARAM_TEXT));
