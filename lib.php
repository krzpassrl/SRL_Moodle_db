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
 * This file contains the moodle hooks for the setask module.
 *
 * It delegates most functions to the setaskment class.
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Adds an setaskment instance
 *
 * This is done by calling the add_instance() method of the setaskment type class
 * @param stdClass $data
 * @param mod_setask_mod_form $form
 * @return int The instance id of the new setaskment
 */
function setask_add_instance(stdClass $data, mod_setask_mod_form $form = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $setaskment = new setask(context_module::instance($data->coursemodule), null, null);
    return $setaskment->add_instance($data, true);
}

/**
 * delete an setaskment instance
 * @param int $id
 * @return bool
 */
function setask_delete_instance($id) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');
    $cm = get_coursemodule_from_instance('setask', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $setaskment = new setask($context, null, null);
    return $setaskment->delete_instance();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all setaskment submissions and feedbacks in the database
 * and clean up any related data.
 *
 * @param stdClass $data the data submitted from the reset course.
 * @return array
 */
function setask_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $status = array();
    $params = array('courseid'=>$data->courseid);
    $sql = "SELECT a.id FROM {setask} a WHERE a.course=:courseid";
    $course = $DB->get_record('course', array('id'=>$data->courseid), '*', MUST_EXIST);
    if ($setasks = $DB->get_records_sql($sql, $params)) {
        foreach ($setasks as $setask) {
            $cm = get_coursemodule_from_instance('setask',
                                                 $setask->id,
                                                 $data->courseid,
                                                 false,
                                                 MUST_EXIST);
            $context = context_module::instance($cm->id);
            $setaskment = new setask($context, $cm, $course);
            $status = array_merge($status, $setaskment->reset_userdata($data));
        }
    }
    return $status;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every setaskment event in the site is checked, else
 * only setaskment events belonging to the course specified are checked.
 *
 * @param int $courseid
 * @return bool
 */
function setask_refresh_events($courseid = 0) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    if ($courseid) {
        // Make sure that the course id is numeric.
        if (!is_numeric($courseid)) {
            return false;
        }
        if (!$setasks = $DB->get_records('setask', array('course' => $courseid))) {
            return false;
        }
        // Get course from courseid parameter.
        if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
            return false;
        }
    } else {
        if (!$setasks = $DB->get_records('setask')) {
            return false;
        }
    }
    foreach ($setasks as $setask) {
        // Use setaskment's course column if courseid parameter is not given.
        if (!$courseid) {
            $courseid = $setask->course;
            if (!$course = $DB->get_record('course', array('id' => $courseid), '*')) {
                continue;
            }
        }
        if (!$cm = get_coursemodule_from_instance('setask', $setask->id, $courseid, false)) {
            continue;
        }
        $context = context_module::instance($cm->id);
        $setaskment = new setask($context, $cm, $course);
        $setaskment->update_calendar($cm->id);
    }

    return true;
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of setaskment to limit the reset to a particular setaskment type
 */
function setask_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $params = array('moduletype'=>'setask', 'courseid'=>$courseid);
    $sql = 'SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
            FROM {setask} a, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=a.id AND a.course=:courseid';

    if ($setaskments = $DB->get_records_sql($sql, $params)) {
        foreach ($setaskments as $setaskment) {
            setask_grade_item_update($setaskment, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the setaskment.
 * @param moodleform $mform form passed by reference
 */
function setask_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'setaskheader', get_string('modulenameplural', 'setask'));
    $name = get_string('deleteallsubmissions', 'setask');
    $mform->addElement('advcheckbox', 'reset_setask_submissions', $name);
}

/**
 * Course reset form defaults.
 * @param  object $course
 * @return array
 */
function setask_reset_course_form_defaults($course) {
    return array('reset_setask_submissions'=>1);
}

/**
 * Update an setaskment instance
 *
 * This is done by calling the update_instance() method of the setaskment type class
 * @param stdClass $data
 * @param stdClass $form - unused
 * @return object
 */
function setask_update_instance(stdClass $data, $form) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');
    $context = context_module::instance($data->coursemodule);
    $setaskment = new setask($context, null, null);
    return $setaskment->update_instance($data);
}

/**
 * Return the list if Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function setask_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_ADVANCED_GRADING:
            return true;
        case FEATURE_PLAGIARISM:
            return true;

        default:
            return null;
    }
}

/**
 * Lists all gradable areas for the advanced grading methods gramework
 *
 * @return array('string'=>'string') An array with area names as keys and descriptions as values
 */
function setask_grading_areas_list() {
    return array('submissions'=>get_string('submissions', 'setask'));
}


/**
 * extend an assigment navigation settings
 *
 * @param settings_navigation $settings
 * @param navigation_node $navref
 * @return void
 */
function setask_extend_settings_navigation(settings_navigation $settings, navigation_node $navref) {
    global $PAGE, $DB;

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    $context = $cm->context;
    $course = $PAGE->course;

    if (!$course) {
        return;
    }

    // Link to gradebook.
    if (has_capability('gradereport/grader:view', $cm->context) &&
            has_capability('moodle/grade:viewall', $cm->context)) {
        $link = new moodle_url('/grade/report/grader/index.php', array('id' => $course->id));
        $linkname = get_string('viewgradebook', 'setask');
        $node = $navref->add($linkname, $link, navigation_node::TYPE_SETTING);
    }

    // Link to download all submissions.
    if (has_any_capability(array('mod/setask:grade', 'mod/setask:viewgrades'), $context)) {
        $link = new moodle_url('/mod/setask/view.php', array('id' => $cm->id, 'action'=>'grading'));
        $node = $navref->add(get_string('viewgrading', 'setask'), $link, navigation_node::TYPE_SETTING);

        $link = new moodle_url('/mod/setask/view.php', array('id' => $cm->id, 'action'=>'downloadall'));
        $node = $navref->add(get_string('downloadall', 'setask'), $link, navigation_node::TYPE_SETTING);
    }

    if (has_capability('mod/setask:revealidentities', $context)) {
        $dbparams = array('id'=>$cm->instance);
        $setaskment = $DB->get_record('setask', $dbparams, 'blindmarking, revealidentities');

        if ($setaskment && $setaskment->blindmarking && !$setaskment->revealidentities) {
            $urlparams = array('id' => $cm->id, 'action'=>'revealidentities');
            $url = new moodle_url('/mod/setask/view.php', $urlparams);
            $linkname = get_string('revealidentities', 'setask');
            $node = $navref->add($linkname, $url, navigation_node::TYPE_SETTING);
        }
    }
}

/**
 * Add a get_coursemodule_info function in case any setaskment type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function setask_get_coursemodule_info($coursemodule) {
    global $CFG, $DB;

    $dbparams = array('id'=>$coursemodule->instance);
    $fields = 'id, name, alwaysshowdescription, allowsubmissionsfromdate, intro, introformat';
    if (! $setaskment = $DB->get_record('setask', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $setaskment->name;
    if ($coursemodule->showdescription) {
        if ($setaskment->alwaysshowdescription || time() > $setaskment->allowsubmissionsfromdate) {
            // Convert intro to html. Do not filter cached version, filters run at display time.
            $result->content = format_module_intro('setask', $setaskment, $coursemodule->id, false);
        }
    }
    return $result;
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function setask_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $modulepagetype = array(
        'mod-setask-*' => get_string('page-mod-setask-x', 'setask'),
        'mod-setask-view' => get_string('page-mod-setask-view', 'setask'),
    );
    return $modulepagetype;
}

/**
 * Print an overview of all setaskments
 * for the courses.
 *
 * @param mixed $courses The list of courses to print the overview for
 * @param array $htmlarray The array of html to return
 *
 * @return true
 */
function setask_print_overview($courses, &$htmlarray) {
    global $CFG, $DB;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return true;
    }

    if (!$setaskments = get_all_instances_in_courses('setask', $courses)) {
        return true;
    }

    $setaskmentids = array();

    // Do setaskment_base::isopen() here without loading the whole thing for speed.
    foreach ($setaskments as $key => $setaskment) {
        $time = time();
        $isopen = false;
        if ($setaskment->duedate) {
            $duedate = false;
            if ($setaskment->cutoffdate) {
                $duedate = $setaskment->cutoffdate;
            }
            if ($duedate) {
                $isopen = ($setaskment->allowsubmissionsfromdate <= $time && $time <= $duedate);
            } else {
                $isopen = ($setaskment->allowsubmissionsfromdate <= $time);
            }
        }
        if ($isopen) {
            $setaskmentids[] = $setaskment->id;
        }
    }

    if (empty($setaskmentids)) {
        // No setaskments to look at - we're done.
        return true;
    }

    // Definitely something to print, now include the constants we need.
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $strduedate = get_string('duedate', 'setask');
    $strcutoffdate = get_string('nosubmissionsacceptedafter', 'setask');
    $strnolatesubmissions = get_string('nolatesubmissions', 'setask');
    $strduedateno = get_string('duedateno', 'setask');
    $strsetaskment = get_string('modulename', 'setask');

    // We do all possible database work here *outside* of the loop to ensure this scales.
    list($sqlsetaskmentids, $setaskmentidparams) = $DB->get_in_or_equal($setaskmentids);

    $mysubmissions = null;
    $unmarkedsubmissions = null;

    foreach ($setaskments as $setaskment) {

        // Do not show setaskments that are not open.
        if (!in_array($setaskment->id, $setaskmentids)) {
            continue;
        }

        $context = context_module::instance($setaskment->coursemodule);

        // Does the submission status of the setaskment require notification?
        if (has_capability('mod/setask:submit', $context)) {
            // Does the submission status of the setaskment require notification?
            $submitdetails = setask_get_mysubmission_details_for_print_overview($mysubmissions, $sqlsetaskmentids,
                    $setaskmentidparams, $setaskment);
        } else {
            $submitdetails = false;
        }

        if (has_capability('mod/setask:grade', $context)) {
            // Does the grading status of the setaskment require notification ?
            $gradedetails = setask_get_grade_details_for_print_overview($unmarkedsubmissions, $sqlsetaskmentids,
                    $setaskmentidparams, $setaskment, $context);
        } else {
            $gradedetails = false;
        }

        if (empty($submitdetails) && empty($gradedetails)) {
            // There is no need to display this setaskment as there is nothing to notify.
            continue;
        }

        $dimmedclass = '';
        if (!$setaskment->visible) {
            $dimmedclass = ' class="dimmed"';
        }
        $href = $CFG->wwwroot . '/mod/setask/view.php?id=' . $setaskment->coursemodule;
        $basestr = '<div class="setask overview">' .
               '<div class="name">' .
               $strsetaskment . ': '.
               '<a ' . $dimmedclass .
                   'title="' . $strsetaskment . '" ' .
                   'href="' . $href . '">' .
               format_string($setaskment->name) .
               '</a></div>';
        if ($setaskment->duedate) {
            $userdate = userdate($setaskment->duedate);
            $basestr .= '<div class="info">' . $strduedate . ': ' . $userdate . '</div>';
        } else {
            $basestr .= '<div class="info">' . $strduedateno . '</div>';
        }
        if ($setaskment->cutoffdate) {
            if ($setaskment->cutoffdate == $setaskment->duedate) {
                $basestr .= '<div class="info">' . $strnolatesubmissions . '</div>';
            } else {
                $userdate = userdate($setaskment->cutoffdate);
                $basestr .= '<div class="info">' . $strcutoffdate . ': ' . $userdate . '</div>';
            }
        }

        // Show only relevant information.
        if (!empty($submitdetails)) {
            $basestr .= $submitdetails;
        }

        if (!empty($gradedetails)) {
            $basestr .= $gradedetails;
        }
        $basestr .= '</div>';

        if (empty($htmlarray[$setaskment->course]['setask'])) {
            $htmlarray[$setaskment->course]['setask'] = $basestr;
        } else {
            $htmlarray[$setaskment->course]['setask'] .= $basestr;
        }
    }
    return true;
}

/**
 * This api generates html to be displayed to students in print overview section, related to their submission status of the given
 * setaskment.
 *
 * @param array $mysubmissions list of submissions of current user indexed by setaskment id.
 * @param string $sqlsetaskmentids sql clause used to filter open setaskments.
 * @param array $setaskmentidparams sql params used to filter open setaskments.
 * @param stdClass $setaskment current setaskment
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function setask_get_mysubmission_details_for_print_overview(&$mysubmissions, $sqlsetaskmentids, $setaskmentidparams,
                                                            $setaskment) {
    global $USER, $DB;

    if ($setaskment->nosubmissions) {
        // Offline setaskment. No need to display alerts for offline setaskments.
        return false;
    }

    $strnotsubmittedyet = get_string('notsubmittedyet', 'setask');

    if (!isset($mysubmissions)) {

        // Get all user submissions, indexed by setaskment id.
        $dbparams = array_merge(array($USER->id), $setaskmentidparams, array($USER->id));
        $mysubmissions = $DB->get_records_sql('SELECT a.id AS setaskment,
                                                      a.nosubmissions AS nosubmissions,
                                                      g.timemodified AS timemarked,
                                                      g.grader AS grader,
                                                      g.grade AS grade,
                                                      s.status AS status
                                                 FROM {setask} a, {setask_submission} s
                                            LEFT JOIN {setask_grades} g ON
                                                      g.setaskment = s.setaskment AND
                                                      g.userid = ? AND
                                                      g.attemptnumber = s.attemptnumber
                                                WHERE a.id ' . $sqlsetaskmentids . ' AND
                                                      s.latest = 1 AND
                                                      s.setaskment = a.id AND
                                                      s.userid = ?', $dbparams);
    }

    $submitdetails = '';
    $submitdetails .= '<div class="details">';
    $submitdetails .= get_string('mysubmission', 'setask');
    $submission = false;

    if (isset($mysubmissions[$setaskment->id])) {
        $submission = $mysubmissions[$setaskment->id];
    }

    if ($submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
        // A valid submission already exists, no need to notify students about this.
        return false;
    }

    // We need to show details only if a valid submission doesn't exist.
    if (!$submission ||
        !$submission->status ||
        $submission->status == ASSIGN_SUBMISSION_STATUS_DRAFT ||
        $submission->status == ASSIGN_SUBMISSION_STATUS_NEW
    ) {
        $submitdetails .= $strnotsubmittedyet;
    } else {
        $submitdetails .= get_string('submissionstatus_' . $submission->status, 'setask');
    }
    if ($setaskment->markingworkflow) {
        $workflowstate = $DB->get_field('setask_user_flags', 'workflowstate', array('setaskment' =>
                $setaskment->id, 'userid' => $USER->id));
        if ($workflowstate) {
            $gradingstatus = 'markingworkflowstate' . $workflowstate;
        } else {
            $gradingstatus = 'markingworkflowstate' . ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
        }
    } else if (!empty($submission->grade) && $submission->grade !== null && $submission->grade >= 0) {
        $gradingstatus = ASSIGN_GRADING_STATUS_GRADED;
    } else {
        $gradingstatus = ASSIGN_GRADING_STATUS_NOT_GRADED;
    }
    $submitdetails .= ', ' . get_string($gradingstatus, 'setask');
    $submitdetails .= '</div>';
    return $submitdetails;
}

/**
 * This api generates html to be displayed to teachers in print overview section, related to the grading status of the given
 * setaskment's submissions.
 *
 * @param array $unmarkedsubmissions list of submissions of that are currently unmarked indexed by setaskment id.
 * @param string $sqlsetaskmentids sql clause used to filter open setaskments.
 * @param array $setaskmentidparams sql params used to filter open setaskments.
 * @param stdClass $setaskment current setaskment
 * @param context $context context of the setaskment.
 *
 * @return bool|string html to display , false if nothing needs to be displayed.
 * @throws coding_exception
 */
function setask_get_grade_details_for_print_overview(&$unmarkedsubmissions, $sqlsetaskmentids, $setaskmentidparams,
                                                     $setaskment, $context) {
    global $DB;
    if (!isset($unmarkedsubmissions)) {
        // Build up and array of unmarked submissions indexed by setaskment id/ userid
        // for use where the user has grading rights on setaskment.
        $dbparams = array_merge(array(ASSIGN_SUBMISSION_STATUS_SUBMITTED), $setaskmentidparams);
        $rs = $DB->get_recordset_sql('SELECT s.setaskment as setaskment,
                                             s.userid as userid,
                                             s.id as id,
                                             s.status as status,
                                             g.timemodified as timegraded
                                        FROM {setask_submission} s
                                   LEFT JOIN {setask_grades} g ON
                                             s.userid = g.userid AND
                                             s.setaskment = g.setaskment AND
                                             g.attemptnumber = s.attemptnumber
                                       WHERE
                                             ( g.timemodified is NULL OR
                                             s.timemodified > g.timemodified OR
                                             g.grade IS NULL ) AND
                                             s.timemodified IS NOT NULL AND
                                             s.status = ? AND
                                             s.latest = 1 AND
                                             s.setaskment ' . $sqlsetaskmentids, $dbparams);

        $unmarkedsubmissions = array();
        foreach ($rs as $rd) {
            $unmarkedsubmissions[$rd->setaskment][$rd->userid] = $rd->id;
        }
        $rs->close();
    }

    // Count how many people can submit.
    $submissions = 0;
    if ($students = get_enrolled_users($context, 'mod/setask:view', 0, 'u.id')) {
        foreach ($students as $student) {
            if (isset($unmarkedsubmissions[$setaskment->id][$student->id])) {
                $submissions++;
            }
        }
    }

    if ($submissions) {
        $urlparams = array('id' => $setaskment->coursemodule, 'action' => 'grading');
        $url = new moodle_url('/mod/setask/view.php', $urlparams);
        $gradedetails = '<div class="details">' .
                '<a href="' . $url . '">' .
                get_string('submissionsnotgraded', 'setask', $submissions) .
                '</a></div>';
        return $gradedetails;
    } else {
        return false;
    }

}

/**
 * Print recent activity from all setaskments in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function setask_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    // Do not use log table if possible, it may be huge.

    $dbparams = array($timestart, $course->id, 'setask', ASSIGN_SUBMISSION_STATUS_SUBMITTED);
    $namefields = user_picture::fields('u', null, 'userid');
    if (!$submissions = $DB->get_records_sql("SELECT asb.id, asb.timemodified, cm.id AS cmid,
                                                     $namefields
                                                FROM {setask_submission} asb
                                                     JOIN {setask} a      ON a.id = asb.setaskment
                                                     JOIN {course_modules} cm ON cm.instance = a.id
                                                     JOIN {modules} md        ON md.id = cm.module
                                                     JOIN {user} u            ON u.id = asb.userid
                                               WHERE asb.timemodified > ? AND
                                                     asb.latest = 1 AND
                                                     a.course = ? AND
                                                     md.name = ? AND
                                                     asb.status = ?
                                            ORDER BY asb.timemodified ASC", $dbparams)) {
         return false;
    }

    $modinfo = get_fast_modinfo($course);
    $show    = array();
    $grader  = array();

    $showrecentsubmissions = get_config('setask', 'showrecentsubmissions');

    foreach ($submissions as $submission) {
        if (!array_key_exists($submission->cmid, $modinfo->get_cms())) {
            continue;
        }
        $cm = $modinfo->get_cm($submission->cmid);
        if (!$cm->uservisible) {
            continue;
        }
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }

        $context = context_module::instance($submission->cmid);
        // The act of submitting of setaskment may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!array_key_exists($cm->id, $grader)) {
                $grader[$cm->id] = has_capability('moodle/grade:viewall', $context);
            }
            if (!$grader[$cm->id]) {
                continue;
            }
        }

        $groupmode = groups_get_activity_groupmode($cm, $course);

        if ($groupmode == SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups',  $context)) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newsubmissions', 'setask').':', 3);

    foreach ($show as $submission) {
        $cm = $modinfo->get_cm($submission->cmid);
        $context = context_module::instance($submission->cmid);
        $setask = new setask($context, $cm, $cm->course);
        $link = $CFG->wwwroot.'/mod/setask/view.php?id='.$cm->id;
        // Obscure first and last name if blind marking enabled.
        if ($setask->is_blind_marking()) {
            $submission->firstname = get_string('participant', 'mod_setask');
            $submission->lastname = $setask->get_uniqueid_for_user($submission->userid);
        }
        print_recent_activity_note($submission->timemodified,
                                   $submission,
                                   $cm->name,
                                   $link,
                                   false,
                                   $viewfullnames);
    }

    return true;
}

/**
 * Returns all setaskments since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function setask_get_recent_mod_activity(&$activities,
                                        &$index,
                                        $timestart,
                                        $courseid,
                                        $cmid,
                                        $userid=0,
                                        $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id'=>$courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->get_cm($cmid);
    $params = array();
    if ($userid) {
        $userselect = 'AND u.id = :userid';
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['cminstance'] = $cm->instance;
    $params['timestart'] = $timestart;
    $params['submitted'] = ASSIGN_SUBMISSION_STATUS_SUBMITTED;

    $userfields = user_picture::fields('u', null, 'userid');

    if (!$submissions = $DB->get_records_sql('SELECT asb.id, asb.timemodified, ' .
                                                     $userfields .
                                             '  FROM {setask_submission} asb
                                                JOIN {setask} a ON a.id = asb.setaskment
                                                JOIN {user} u ON u.id = asb.userid ' .
                                          $groupjoin .
                                            '  WHERE asb.timemodified > :timestart AND
                                                     asb.status = :submitted AND
                                                     a.id = :cminstance
                                                     ' . $userselect . ' ' . $groupselect .
                                            ' ORDER BY asb.timemodified ASC', $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext      = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cmcontext);


    $showrecentsubmissions = get_config('setask', 'showrecentsubmissions');
    $show = array();
    foreach ($submissions as $submission) {
        if ($submission->userid == $USER->id) {
            $show[] = $submission;
            continue;
        }
        // The act of submitting of setaskment may be considered private -
        // only graders will see it if specified.
        if (empty($showrecentsubmissions)) {
            if (!$grader) {
                continue;
            }
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups =  groups_get_all_groups($course->id, $submission->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $submission;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir.'/gradelib.php');
        $userids = array();
        foreach ($show as $id => $submission) {
            $userids[] = $submission->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'setask', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($show as $submission) {
        $activity = new stdClass();

        $activity->type         = 'setask';
        $activity->cmid         = $cm->id;
        $activity->name         = $aname;
        $activity->sectionnum   = $cm->sectionnum;
        $activity->timestamp    = $submission->timemodified;
        $activity->user         = new stdClass();
        if ($grader) {
            $activity->grade = $grades->items[0]->grades[$submission->userid]->str_long_grade;
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                // Aliased in SQL above.
                $activity->user->{$userfield} = $submission->userid;
            } else {
                $activity->user->{$userfield} = $submission->{$userfield};
            }
        }
        $activity->user->fullname = fullname($submission, $viewfullnames);

        $activities[$index++] = $activity;
    }

    return;
}

/**
 * Print recent activity from all setaskments in a given course
 *
 * This is used by course/recent.php
 * @param stdClass $activity
 * @param int $courseid
 * @param bool $detail
 * @param array $modnames
 */
function setask_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="setaskment-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo '<img src="' . $OUTPUT->pix_url('icon', 'setask') . '" '.
             'class="icon" alt="' . $modname . '">';
        echo '<a href="' . $CFG->wwwroot . '/mod/setask/view.php?id=' . $activity->cmid . '">';
        echo $activity->name;
        echo '</a>';
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo get_string('grade').': ';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">";
    echo "{$activity->user->fullname}</a>  - " . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Checks if a scale is being used by an setaskment.
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param int $setaskmentid
 * @param int $scaleid
 * @return boolean True if the scale is used by the setaskment
 */
function setask_scale_used($setaskmentid, $scaleid) {
    global $DB;

    $return = false;
    $rec = $DB->get_record('setask', array('id'=>$setaskmentid, 'grade'=>-$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of setaskment
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any setaskment
 */
function setask_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('setask', array('grade'=>-$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function setask_get_view_actions() {
    return array('view submission', 'view feedback');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function setask_get_post_actions() {
    return array('upload', 'submit', 'submit for grading');
}

/**
 * Call cron on the setask module.
 */
function setask_cron() {
    global $CFG;

    require_once($CFG->dirroot . '/mod/setask/locallib.php');
    setask::cron();

    $plugins = core_component::get_plugin_list('setasksubmission');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('setasksubmission_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'setask_submission_' . $name;
            require_once($CFG->dirroot . '/mod/setask/submission/' . $name . '/locallib.php');
            $class::cron();
        }
    }
    $plugins = core_component::get_plugin_list('setaskfeedback');

    foreach ($plugins as $name => $plugin) {
        $disabled = get_config('setaskfeedback_' . $name, 'disabled');
        if (!$disabled) {
            $class = 'setask_feedback_' . $name;
            require_once($CFG->dirroot . '/mod/setask/feedback/' . $name . '/locallib.php');
            $class::cron();
        }
    }

    return true;
}

/**
 * Returns all other capabilities used by this module.
 * @return array Array of capability strings
 */
function setask_get_extra_capabilities() {
    return array('gradereport/grader:view',
                 'moodle/grade:viewall',
                 'moodle/site:viewfullnames',
                 'moodle/site:config');
}

/**
 * Create grade item for given setaskment.
 *
 * @param stdClass $setask record with extra cmidnumber
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok, error code otherwise
 */
function setask_grade_item_update($setask, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if (!isset($setask->courseid)) {
        $setask->courseid = $setask->course;
    }

    $params = array('itemname'=>$setask->name, 'idnumber'=>$setask->cmidnumber);

    // Check if feedback plugin for gradebook is enabled, if yes then
    // gradetype = GRADE_TYPE_TEXT else GRADE_TYPE_NONE.
    $gradefeedbackenabled = false;

    if (isset($setask->gradefeedbackenabled)) {
        $gradefeedbackenabled = $setask->gradefeedbackenabled;
    } else if ($setask->grade == 0) { // Grade feedback is needed only when grade == 0.
        require_once($CFG->dirroot . '/mod/setask/locallib.php');
        $mod = get_coursemodule_from_instance('setask', $setask->id, $setask->courseid);
        $cm = context_module::instance($mod->id);
        $setaskment = new setask($cm, null, null);
        $gradefeedbackenabled = $setaskment->is_gradebook_feedback_enabled();
    }

    if ($setask->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $setask->grade;
        $params['grademin']  = 0;

    } else if ($setask->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$setask->grade;

    } else if ($gradefeedbackenabled) {
        // $setask->grade == 0 and feedback enabled.
        $params['gradetype'] = GRADE_TYPE_TEXT;
    } else {
        // $setask->grade == 0 and no feedback enabled.
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update('mod/setask',
                        $setask->courseid,
                        'mod',
                        'setask',
                        $setask->id,
                        0,
                        $grades,
                        $params);
}

/**
 * Return grade for given user or all users.
 *
 * @param stdClass $setask record of setask with an additional cmidnumber
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function setask_get_user_grades($setask, $userid=0) {
    global $CFG;

    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $cm = get_coursemodule_from_instance('setask', $setask->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $setaskment = new setask($context, null, null);
    $setaskment->set_instance($setask);
    return $setaskment->get_user_grades_for_gradebook($userid);
}

/**
 * Update activity grades.
 *
 * @param stdClass $setask database record
 * @param int $userid specific user only, 0 means all
 * @param bool $nullifnone - not used
 */
function setask_update_grades($setask, $userid=0, $nullifnone=true) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    if ($setask->grade == 0) {
        setask_grade_item_update($setask);

    } else if ($grades = setask_get_user_grades($setask, $userid)) {
        foreach ($grades as $k => $v) {
            if ($v->rawgrade == -1) {
                $grades[$k]->rawgrade = null;
            }
        }
        setask_grade_item_update($setask, $grades);

    } else {
        setask_grade_item_update($setask);
    }
}

/**
 * List the file areas that can be browsed.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array
 */
function setask_get_file_areas($course, $cm, $context) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $areas = array(ASSIGN_INTROATTACHMENT_FILEAREA => get_string('introattachments', 'mod_setask'));

    $setaskment = new setask($context, $cm, $course);
    foreach ($setaskment->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }
    foreach ($setaskment->get_feedback_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if ($pluginareas) {
                $areas = array_merge($areas, $pluginareas);
            }
        }
    }

    return $areas;
}

/**
 * File browsing support for setask module.
 *
 * @param file_browser $browser
 * @param object $areas
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return object file_info instance or null if not found
 */
function setask_get_file_info($browser,
                              $areas,
                              $course,
                              $cm,
                              $context,
                              $filearea,
                              $itemid,
                              $filepath,
                              $filename) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;

    // Need to find where this belongs to.
    $setaskment = new setask($context, $cm, $course);
    if ($filearea === ASSIGN_INTROATTACHMENT_FILEAREA) {
        if (!has_capability('moodle/course:managefiles', $context)) {
            // Students can not peak here!
            return null;
        }
        if (!($storedfile = $fs->get_file($setaskment->get_context()->id,
                                          'mod_setask', $filearea, 0, $filepath, $filename))) {
            return null;
        }
        return new file_info_stored($browser,
                        $setaskment->get_context(),
                        $storedfile,
                        $urlbase,
                        $filearea,
                        $itemid,
                        true,
                        true,
                        false);
    }

    $pluginowner = null;
    foreach ($setaskment->get_submission_plugins() as $plugin) {
        if ($plugin->is_visible()) {
            $pluginareas = $plugin->get_file_areas();

            if (array_key_exists($filearea, $pluginareas)) {
                $pluginowner = $plugin;
                break;
            }
        }
    }
    if (!$pluginowner) {
        foreach ($setaskment->get_feedback_plugins() as $plugin) {
            if ($plugin->is_visible()) {
                $pluginareas = $plugin->get_file_areas();

                if (array_key_exists($filearea, $pluginareas)) {
                    $pluginowner = $plugin;
                    break;
                }
            }
        }
    }

    if (!$pluginowner) {
        return null;
    }

    $result = $pluginowner->get_file_info($browser, $filearea, $itemid, $filepath, $filename);
    return $result;
}

/**
 * Prints the complete info about a user's interaction with an setaskment.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $setask the database setask record
 *
 * This prints the submission summary and feedback summary for this student.
 */
function setask_user_complete($course, $user, $coursemodule, $setask) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $context = context_module::instance($coursemodule->id);

    $setaskment = new setask($context, $coursemodule, $course);

    echo $setaskment->view_student_summary($user, false);
}

/**
 * Print the grade information for the setaskment for this user.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $coursemodule
 * @param stdClass $setaskment
 */
function setask_user_outline($course, $user, $coursemodule, $setaskment) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');
    require_once($CFG->dirroot.'/grade/grading/lib.php');

    $gradinginfo = grade_get_grades($course->id,
                                        'mod',
                                        'setask',
                                        $setaskment->id,
                                        $user->id);

    $gradingitem = $gradinginfo->items[0];
    $gradebookgrade = $gradingitem->grades[$user->id];

    if (empty($gradebookgrade->str_long_grade)) {
        return null;
    }
    $result = new stdClass();
    $result->info = get_string('outlinegrade', 'setask', $gradebookgrade->str_long_grade);
    $result->time = $gradebookgrade->dategraded;

    return $result;
}

/**
 * Obtains the automatic completion state for this module based on any conditions
 * in setask settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function setask_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/setask/locallib.php');

    $setask = new setask(null, $cm, $course);

    // If completion option is enabled, evaluate it and return true/false.
    if ($setask->get_instance()->completionsubmit) {
        $submission = $setask->get_user_submission($userid, false);
        return $submission && $submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED;
    } else {
        // Completion option is not enabled so just return $type.
        return $type;
    }
}

/**
 * Serves intro attachment files.
 *
 * @param mixed $course course or id of the course
 * @param mixed $cm course module or id of the course module
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - just send the file
 */
function setask_pluginfile($course,
                $cm,
                context $context,
                $filearea,
                $args,
                $forcedownload,
                array $options=array()) {
    global $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);
    if (!has_capability('mod/setask:view', $context)) {
        return false;
    }

    require_once($CFG->dirroot . '/mod/setask/locallib.php');
    $setask = new setask($context, $cm, $course);

    if ($filearea !== ASSIGN_INTROATTACHMENT_FILEAREA) {
        return false;
    }
    if (!$setask->show_intro()) {
        return false;
    }

    $itemid = (int)array_shift($args);
    if ($itemid != 0) {
        return false;
    }

    $relativepath = implode('/', $args);

    $fullpath = "/{$context->id}/mod_setask/$filearea/$itemid/$relativepath";

    $fs = get_file_storage();
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}
