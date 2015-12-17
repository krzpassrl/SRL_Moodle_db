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
 * Define all the restore steps that will be used by the restore_setask_activity_task
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete setaskment structure for restore, with file and id annotations
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_setask_activity_structure_step extends restore_activity_structure_step {

    /**
     * Store whether submission details should be included. Details may not be included if the
     * this is a team submission, but groups/grouping information was not included in the backup.
     */
    protected $includesubmission = true;

    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element $structure
     */
    protected function define_structure() {

        $paths = array();
        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $paths[] = new restore_path_element('setask', '/activity/setask');
        if ($userinfo) {
            $submission = new restore_path_element('setask_submission',
                                                   '/activity/setask/submissions/submission');
            $paths[] = $submission;
            $this->add_subplugin_structure('setasksubmission', $submission);
            $grade = new restore_path_element('setask_grade', '/activity/setask/grades/grade');
            $paths[] = $grade;
            $this->add_subplugin_structure('setaskfeedback', $grade);
            $userflag = new restore_path_element('setask_userflag',
                                                   '/activity/setask/userflags/userflag');
            $paths[] = $userflag;
        }
        $paths[] = new restore_path_element('setask_plugin_config',
                                            '/activity/setask/plugin_configs/plugin_config');

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process an setask restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_setask($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->allowsubmissionsfromdate = $this->apply_date_offset($data->allowsubmissionsfromdate);
        $data->duedate = $this->apply_date_offset($data->duedate);

        // If this is a team submission, but there is no group info we need to flag that the submission
        // information should not be included. It should not be restored.
        $groupinfo = $this->task->get_setting_value('groups');
        if ($data->teamsubmission && !$groupinfo) {
            $this->includesubmission = false;
        }

        if (!empty($data->teamsubmissiongroupingid)) {
            $data->teamsubmissiongroupingid = $this->get_mappingid('grouping',
                                                                   $data->teamsubmissiongroupingid);
        } else {
            $data->teamsubmissiongroupingid = 0;
        }

        if (!isset($data->cutoffdate)) {
            $data->cutoffdate = 0;
        }
        if (!isset($data->markingworkflow)) {
            $data->markingworkflow = 0;
        }
        if (!isset($data->markingallocation)) {
            $data->markingallocation = 0;
        }
        if (!isset($data->preventsubmissionnotingroup)) {
            $data->preventsubmissionnotingroup = 0;
        }

        if (!empty($data->preventlatesubmissions)) {
            $data->cutoffdate = $data->duedate;
        } else {
            $data->cutoffdate = $this->apply_date_offset($data->cutoffdate);
        }

        if ($data->grade < 0) { // Scale found, get mapping.
            $data->grade = -($this->get_mappingid('scale', abs($data->grade)));
        }

        $newitemid = $DB->insert_record('setask', $data);

        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process a submission restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_setask_submission($data) {
        global $DB;

        if (!$this->includesubmission) {
            return;
        }

        $data = (object)$data;
        $oldid = $data->id;

        $data->setaskment = $this->get_new_parentid('setask');

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        if ($data->userid > 0) {
            $data->userid = $this->get_mappingid('user', $data->userid);
        }
        if (!empty($data->groupid)) {
            $data->groupid = $this->get_mappingid('group', $data->groupid);
        } else {
            $data->groupid = 0;
        }

        // We will correct this in set_latest_submission_field() once all submissions are restored.
        $data->latest = 0;

        $newitemid = $DB->insert_record('setask_submission', $data);

        // Note - the old contextid is required in order to be able to restore files stored in
        // sub plugin file areas attached to the submissionid.
        $this->set_mapping('submission', $oldid, $newitemid, false, null, $this->task->get_old_contextid());
    }

    /**
     * Process a user_flags restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_setask_userflag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->setaskment = $this->get_new_parentid('setask');

        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!empty($data->allocatedmarker)) {
            $data->allocatedmarker = $this->get_mappingid('user', $data->allocatedmarker);
        }
        if (!empty($data->extensionduedate)) {
            $data->extensionduedate = $this->apply_date_offset($data->extensionduedate);
        } else {
            $data->extensionduedate = 0;
        }
        // Flags mailed and locked need no translation on restore.

        $newitemid = $DB->insert_record('setask_user_flags', $data);
    }

    /**
     * Process a grade restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_setask_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->setaskment = $this->get_new_parentid('setask');

        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->grader = $this->get_mappingid('user', $data->grader);

        // Handle flags restore to a different table (for upgrade from old backups).
        if (!empty($data->extensionduedate) ||
                !empty($data->mailed) ||
                !empty($data->locked)) {
            $flags = new stdClass();
            $flags->setaskment = $this->get_new_parentid('setask');
            if (!empty($data->extensionduedate)) {
                $flags->extensionduedate = $this->apply_date_offset($data->extensionduedate);
            }
            if (!empty($data->mailed)) {
                $flags->mailed = $data->mailed;
            }
            if (!empty($data->locked)) {
                $flags->locked = $data->locked;
            }
            $flags->userid = $this->get_mappingid('user', $data->userid);
            $DB->insert_record('setask_user_flags', $flags);
        }

        $newitemid = $DB->insert_record('setask_grades', $data);

        // Note - the old contextid is required in order to be able to restore files stored in
        // sub plugin file areas attached to the gradeid.
        $this->set_mapping('grade', $oldid, $newitemid, false, null, $this->task->get_old_contextid());
        $this->set_mapping(restore_gradingform_plugin::itemid_mapping('submissions'), $oldid, $newitemid);
    }

    /**
     * Process a plugin-config restore
     * @param object $data The data in object form
     * @return void
     */
    protected function process_setask_plugin_config($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->setaskment = $this->get_new_parentid('setask');

        $newitemid = $DB->insert_record('setask_plugin_config', $data);
    }

    /**
     * For all submissions in this setaskment, either set the
     * submission->latest field to 1 for the latest attempts
     * or create a new submission record for grades with no submission.
     *
     * @return void
     */
    protected function set_latest_submission_field() {
        global $DB, $CFG;

        // Required for constants.
        require_once($CFG->dirroot . '/mod/setask/locallib.php');

        $setaskmentid = $this->get_new_parentid('setask');
        // This code could be rewritten as a monster SQL - but the point of adding this "latest" field
        // to the submissions table in the first place was to get away from those hard to maintain SQL queries.

        // First user submissions.
        $sql = 'SELECT DISTINCT userid FROM {setask_submission} WHERE setaskment = ? AND groupid = ?';
        $params = array($setaskmentid, 0);
        $users = $DB->get_records_sql($sql, $params);

        foreach ($users as $userid => $unused) {
            $params = array('setaskment'=>$setaskmentid, 'groupid'=>0, 'userid'=>$userid);

            // Only return the row with the highest attemptnumber.
            $submission = null;
            $submissions = $DB->get_records('setask_submission', $params, 'attemptnumber DESC', '*', 0, 1);
            if ($submissions) {
                $submission = reset($submissions);
                $submission->latest = 1;
                $DB->update_record('setask_submission', $submission);
            }
        }
        // Then group submissions (if any).
        $sql = 'SELECT DISTINCT groupid FROM {setask_submission} WHERE setaskment = ? AND userid = ?';
        $params = array($setaskmentid, 0);
        $groups = $DB->get_records_sql($sql, $params);

        foreach ($groups as $groupid => $unused) {
            $params = array('setaskment'=>$setaskmentid, 'userid'=>0, 'groupid'=>$groupid);

            // Only return the row with the highest attemptnumber.
            $submission = null;
            $submissions = $DB->get_records('setask_submission', $params, 'attemptnumber DESC', '*', 0, 1);
            if ($submissions) {
                $submission = reset($submissions);
                $submission->latest = 1;
                $DB->update_record('setask_submission', $submission);
            }
        }

        // Now check for records with a grade, but no submission record.
        // This happens when a teacher marks a student before they have submitted anything.
        $records = $DB->get_recordset_sql('SELECT g.id, g.userid
                                           FROM {setask_grades} g
                                      LEFT JOIN {setask_submission} s
                                             ON s.setaskment = g.setaskment
                                            AND s.userid = g.userid
                                          WHERE s.id IS NULL AND g.setaskment = ?', array($setaskmentid));

        $submissions = array();
        foreach ($records as $record) {
            $submission = new stdClass();
            $submission->setaskment = $setaskmentid;
            $submission->userid = $record->userid;
            $submission->status = ASSIGN_SUBMISSION_STATUS_NEW;
            $submission->groupid = 0;
            $submission->latest = 1;
            $submission->timecreated = time();
            $submission->timemodified = time();
            array_push($submissions, $submission);
        }

        $records->close();

        $DB->insert_records('setask_submission', $submissions);
    }

    /**
     * Once the database tables have been fully restored, restore the files
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files('mod_setask', 'intro', null);
        $this->add_related_files('mod_setask', 'introattachment', null);

        $this->set_latest_submission_field();
    }
}