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
 * Unit tests for (some of) mod/setask/locallib.php.
 *
 * @package    mod_setask
 * @category   phpunit
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/setask/locallib.php');
require_once($CFG->dirroot . '/mod/setask/upgradelib.php');
require_once($CFG->dirroot . '/mod/setask/tests/base_test.php');

/**
 * Unit tests for (some of) mod/setask/locallib.php.
 *
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_setask_locallib_testcase extends mod_setask_base_testcase {

    public function test_return_links() {
        global $PAGE;
        $this->setUser($this->editingteachers[0]);
        $returnaction = 'RETURNACTION';
        $returnparams = array('param'=>'1');
        $setask = $this->create_instance();
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));
        $setask->register_return_link($returnaction, $returnparams);
        $this->assertEquals($returnaction, $setask->get_return_action());
        $this->assertEquals($returnparams, $setask->get_return_params());
    }

    public function test_get_feedback_plugins() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();
        $installedplugins = array_keys(core_component::get_plugin_list('setaskfeedback'));

        foreach ($setask->get_feedback_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Feedback plugin not in list of installed plugins');
        }
    }

    public function test_get_submission_plugins() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();
        $installedplugins = array_keys(core_component::get_plugin_list('setasksubmission'));

        foreach ($setask->get_submission_plugins() as $plugin) {
            $this->assertContains($plugin->get_type(), $installedplugins, 'Submission plugin not in list of installed plugins');
        }
    }

    public function test_is_blind_marking() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('blindmarking'=>1));
        $this->assertEquals(true, $setask->is_blind_marking());

        // Test cannot see student names.
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'setask')));

        // Test students cannot reveal identities.
        $nopermission = false;
        $this->students[0]->ignoresesskey = true;
        $this->setUser($this->students[0]);
        $this->setExpectedException('required_capability_exception');
        $setask->reveal_identities();
        $this->students[0]->ignoresesskey = false;

        // Test teachers cannot reveal identities.
        $nopermission = false;
        $this->teachers[0]->ignoresesskey = true;
        $this->setUser($this->teachers[0]);
        $this->setExpectedException('required_capability_exception');
        $setask->reveal_identities();
        $this->teachers[0]->ignoresesskey = false;

        // Test sesskey is required.
        $this->setUser($this->editingteachers[0]);
        $this->setExpectedException('moodle_exception');
        $setask->reveal_identities();

        // Test editingteacher can reveal identities if sesskey is ignored.
        $this->editingteachers[0]->ignoresesskey = true;
        $this->setUser($this->editingteachers[0]);
        $setask->reveal_identities();
        $this->assertEquals(false, $setask->is_blind_marking());
        $this->editingteachers[0]->ignoresesskey = false;

        // Test student names are visible.
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(false, strpos($output, get_string('hiddenuser', 'setask')));

        // Set this back to default.
        $this->editingteachers[0]->ignoresesskey = false;
    }

    /**
     * Test submissions with extension date.
     */
    public function test_gradingtable_extension_due_date() {
        global $PAGE;

        // Setup the setaskment.
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array(
            'setasksubmission_onlinetext_enabled'=>1,
            'duedate' => time() - 4 * 24 * 60 * 60,
         ));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array(
            'id' => $setask->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the setaskment is late.
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'setask'), $output);
        $this->assertContains(get_string('overdue', 'setask', format_time(4*24*60*60)), $output);

        // Grant an extension.
        $extendedtime = time() + 2 * 24 * 60 * 60;
        $setask->testable_save_user_extension($this->students[0]->id, $extendedtime);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'setask'), $output);
        $this->assertContains(get_string('userextensiondate', 'setask', userdate($extendedtime)), $output);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Verify output.
        $this->setUser($this->editingteachers[0]);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'setask'), $output);
        $this->assertContains(get_string('userextensiondate', 'setask', userdate($extendedtime)), $output);
    }

    /**
     * Test that late submissions with extension date calculate correctly.
     */
    public function test_gradingtable_extension_date_calculation_for_lateness() {
        global $PAGE;

        // Setup the setaskment.
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $time = time();
        $setask = $this->create_instance(array(
            'setasksubmission_onlinetext_enabled'=>1,
            'duedate' => $time - 4 * 24 * 60 * 60,
         ));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array(
            'id' => $setask->get_course_module()->id,
            'action' => 'grading',
        )));

        // Check that the setaskment is late.
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'setask'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'setask', format_time(4*24*60*60 + $difftime)), $output);

        // Grant an extension that is in the past.
        $setask->testable_save_user_extension($this->students[0]->id, $time - 2 * 24 * 60 * 60);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_', 'setask'), $output);
        $this->assertContains(get_string('userextensiondate', 'setask', userdate($time - 2*24*60*60)), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'setask', format_time(2*24*60*60 + $difftime)), $output);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        $submittedtime = time();

        // Verify output.
        $this->setUser($this->editingteachers[0]);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertContains(get_string('submissionstatus_submitted', 'setask'), $output);
        $this->assertContains(get_string('userextensiondate', 'setask', userdate($time - 2*24*60*60)), $output);

        $difftime = $submittedtime - $time;
        $this->assertContains(get_string('submittedlateshort', 'setask', format_time(2*24*60*60 + $difftime)), $output);
    }

    /**
     * Check that group submission information is rendered correctly in the
     * grading table.
     */
    public function test_gradingtable_group_submissions_rendering() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group setaskments.
        $this->setUser($this->teachers[0]);
        $setask = $this->create_instance(array(
            'teamsubmission' => 1,
            'setasksubmission_onlinetext_enabled' => 1,
            'submissiondrafts' => 1,
            'requireallteammemberssubmit' => 0,
        ));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array(
            'id' => $setask->get_course_module()->id,
            'action' => 'grading',
        )));

        // Add a submission.
        $this->setUser($this->extrastudents[0]);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();
        $setask->save_submission($data, $notices);

        $submission = $setask->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check output.
        $this->setUser($this->teachers[0]);
        $gradingtable = new setask_grading_table($setask, 4, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $document = new DOMDocument();
        $document->loadHTML($output);
        $xpath = new DOMXPath($document);

        // Check status.
        $this->assertSame(get_string('submissionstatus_submitted', 'setask'), $xpath->evaluate('string(//td[@id="mod_setask_grading_r0_c4"]/div[@class="submissionstatussubmitted"])'));
        $this->assertSame(get_string('submissionstatus_submitted', 'setask'), $xpath->evaluate('string(//td[@id="mod_setask_grading_r3_c4"]/div[@class="submissionstatussubmitted"])'));

        // Check submission last modified date
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_setask_grading_r0_c8"])')));
        $this->assertGreaterThan(0, strtotime($xpath->evaluate('string(//td[@id="mod_setask_grading_r3_c8"])')));

        // Check group.
        $this->assertSame($this->groups[0]->name, $xpath->evaluate('string(//td[@id="mod_setask_grading_r0_c5"])'));
        $this->assertSame($this->groups[0]->name, $xpath->evaluate('string(//td[@id="mod_setask_grading_r3_c5"])'));

        // Check submission text.
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_setask_grading_r0_c9"]/div/div)'));
        $this->assertSame('Submission text', $xpath->evaluate('string(//td[@id="mod_setask_grading_r3_c9"]/div/div)'));

        // Check comments can be made.
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_setask_grading_r0_c10"]//textarea)'));
        $this->assertSame(1, (int)$xpath->evaluate('count(//td[@id="mod_setask_grading_r3_c10"]//textarea)'));
    }

    public function test_show_intro() {
        // Test whether we are showing the intro at the correct times.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('alwaysshowdescription'=>1));

        $this->assertEquals(true, $setask->testable_show_intro());

        $tomorrow = time() + (24*60*60);

        $setask = $this->create_instance(array('alwaysshowdescription'=>0,
                                               'allowsubmissionsfromdate'=>$tomorrow));
        $this->assertEquals(false, $setask->testable_show_intro());
        $yesterday = time() - (24*60*60);
        $setask = $this->create_instance(array('alwaysshowdescription'=>0,
                                               'allowsubmissionsfromdate'=>$yesterday));
        $this->assertEquals(true, $setask->testable_show_intro());
    }

    public function test_has_submissions_or_grades() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1));

        $instance = $setask->get_instance();

        // Should start empty.
        $this->assertEquals(false, $setask->has_submissions_or_grades());

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $setask->get_user_submission($this->students[0]->id, true);

        // The submission is still new.
        $this->assertEquals(false, $setask->has_submissions_or_grades());

        // Submit the submission.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Now test again.
        $this->assertEquals(true, $setask->has_submissions_or_grades());
        // Set this back to default.
        $this->students[0]->ignoresesskey = false;
    }

    public function test_delete_grades() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Now see if the data is in the gradebook.
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id);

        $this->assertNotEquals(0, count($gradinginfo->items));

        $setask->testable_delete_grades();
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id);

        $this->assertEquals(0, count($gradinginfo->items));
    }

    public function test_delete_instance() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Now try and delete.
        $this->assertEquals(true, $setask->delete_instance());
    }

    public function test_reset_userdata() {
        global $DB;

        $now = time();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1,
                                               'duedate'=>$now));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->students[0]);
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->assertEquals(true, $setask->has_submissions_or_grades());
        // Now try and reset.
        $data = new stdClass();
        $data->reset_setask_submissions = 1;
        $data->reset_gradebook_grades = 1;
        $data->courseid = $this->course->id;
        $data->timeshift = 24*60*60;
        $this->setUser($this->editingteachers[0]);
        $setask->reset_userdata($data);
        $this->assertEquals(false, $setask->has_submissions_or_grades());

        // Reload the instance data.
        $instance = $DB->get_record('setask', array('id'=>$setask->get_instance()->id));
        $this->assertEquals($now + 24*60*60, $instance->duedate);

        // Test reset using setask_reset_userdata().
        $setaskduedate = $instance->duedate; // Keep old updated value for comparison.
        $data->timeshift = 2*24*60*60;
        setask_reset_userdata($data);
        $instance = $DB->get_record('setask', array('id' => $setask->get_instance()->id));
        $this->assertEquals($setaskduedate + 2*24*60*60, $instance->duedate);

        // Create one more setaskment and reset, make sure time shifted for previous setaskment is not changed.
        $setask2 = $this->create_instance(array('setasksubmission_onlinetext_enabled' => 1,
                                               'duedate' => $now));
        $setaskduedate = $instance->duedate;
        $data->timeshift = 3*24*60*60;
        $setask2->reset_userdata($data);
        $instance = $DB->get_record('setask', array('id' => $setask->get_instance()->id));
        $this->assertEquals($setaskduedate, $instance->duedate);
        $instance2 = $DB->get_record('setask', array('id' => $setask2->get_instance()->id));
        $this->assertEquals($now + 3*24*60*60, $instance2->duedate);

        // Reset both setaskments using setask_reset_userdata() and make sure both setaskments have same date.
        $setaskduedate = $instance->duedate;
        $setask2duedate = $instance2->duedate;
        $data->timeshift = 4*24*60*60;
        setask_reset_userdata($data);
        $instance = $DB->get_record('setask', array('id' => $setask->get_instance()->id));
        $this->assertEquals($setaskduedate + 4*24*60*60, $instance->duedate);
        $instance2 = $DB->get_record('setask', array('id' => $setask2->get_instance()->id));
        $this->assertEquals($setask2duedate + 4*24*60*60, $instance2->duedate);
    }

    public function test_plugin_settings() {
        global $DB;

        $now = time();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_file_enabled'=>1,
                                               'setasksubmission_file_maxfiles'=>12,
                                               'setasksubmission_file_maxsizebytes'=>10));

        $plugin = $setask->get_submission_plugin_by_type('file');
        $this->assertEquals('12', $plugin->get_config('maxfilesubmissions'));
    }

    public function test_update_calendar() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $userctx = context_user::instance($this->editingteachers[0]->id)->id;

        // Hack to pretend that there was an editor involved. We need both $_POST and $_REQUEST, and a sesskey.
        $draftid = file_get_unused_draft_itemid();
        $_REQUEST['introeditor'] = $draftid;
        $_POST['introeditor'] = $draftid;
        $_POST['sesskey'] = sesskey();

        // Write links to a draft area.
        $fakearealink1 = file_rewrite_pluginfile_urls('<a href="@@PLUGINFILE@@/pic.gif">link</a>', 'draftfile.php', $userctx,
            'user', 'draft', $draftid);
        $fakearealink2 = file_rewrite_pluginfile_urls('<a href="@@PLUGINFILE@@/pic.gif">new</a>', 'draftfile.php', $userctx,
            'user', 'draft', $draftid);

        // Create a new setaskment with links to a draft area.
        $now = time();
        $setask = $this->create_instance(array(
            'duedate' => $now,
            'intro' => $fakearealink1,
            'introformat' => FORMAT_HTML
        ));

        // See if there is an event in the calendar.
        $params = array('modulename'=>'setask', 'instance'=>$setask->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('link', $event->description);     // The pluginfile links are removed.

        // Make sure the same works when updating the setaskment.
        $instance = $setask->get_instance();
        $instance->instance = $instance->id;
        $instance->intro = $fakearealink2;
        $instance->introformat = FORMAT_HTML;
        $setask->update_instance($instance);
        $params = array('modulename' => 'setask', 'instance' => $setask->get_instance()->id);
        $event = $DB->get_record('event', $params);
        $this->assertNotEmpty($event);
        $this->assertSame('new', $event->description);     // The pluginfile links are removed.

        // Create an setaskment with a description that should be hidden.
        $setask = $this->create_instance(array('duedate'=>$now + 160,
                                               'alwaysshowdescription'=>false,
                                               'allowsubmissionsfromdate'=>$now + 60,
                                               'intro'=>'Some text'));

        // Get the event from the calendar.
        $params = array('modulename'=>'setask', 'instance'=>$setask->get_instance()->id);
        $event = $DB->get_record('event', $params);

        $this->assertEmpty($event->description);

        // Change the allowsubmissionfromdate to the past - do this directly in the DB
        // because if we call the setaskment update method - it will update the calendar
        // and we want to test that this works from cron.
        $DB->set_field('setask', 'allowsubmissionsfromdate', $now - 60, array('id'=>$setask->get_instance()->id));
        // Run cron to update the event in the calendar.
        setask::cron();
        $event = $DB->get_record('event', $params);

        $this->assertContains('Some text', $event->description);

    }

    public function test_update_instance() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1));

        $now = time();
        $instance = $setask->get_instance();
        $instance->duedate = $now;
        $instance->instance = $instance->id;
        $instance->setasksubmission_onlinetext_enabled = 1;

        $setask->update_instance($instance);

        $instance = $DB->get_record('setask', array('id'=>$setask->get_instance()->id));
        $this->assertEquals($now, $instance->duedate);
    }

    public function test_cannot_submit_empty() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('submissiondrafts'=>1));

        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Test you cannot see the submit button for an offline setaskment regardless.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output, 'Can submit empty offline setaskment');

        // Test you cannot see the submit button for an online text setaskment with no submission.
        $this->setUser($this->editingteachers[0]);
        $instance = $setask->get_instance();
        $instance->instance = $instance->id;
        $instance->setasksubmission_onlinetext_enabled = 1;

        $setask->update_instance($instance);
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output, 'Cannot submit empty onlinetext setaskment');

        // Simulate a submission.
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);
        // Test you can see the submit button for an online text setaskment with a submission.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertContains(get_string('submitsetaskment', 'setask'), $output, 'Can submit non empty onlinetext setaskment');
    }

    public function test_list_participants() {
        global $CFG, $DB;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('grade'=>100));

        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($setask->list_participants(null, true)));

        // Teacher with user preference set should see suspended users as well.
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $setask = $this->create_instance(array('grade'=>100));
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT + self::EXTRA_SUSPENDED_COUNT,
                count($setask->list_participants(null, true)));

        // Non-editing teacher should not see suspended users, even if user preference is set.
        $this->setUser($this->teachers[0]);
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $setask = $this->create_instance(array('grade'=>100));
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($setask->list_participants(null, true)));

        // Turn on availability and a group restriction, and check that it doesn't
        // show users who aren't in the group.
        $CFG->enableavailability = true;
        $specialgroup = $this->getDataGenerator()->create_group(
                array('courseid' => $this->course->id));
        $setask = $this->create_instance(array('grade' => 100,
                'availability' => json_encode(\core_availability\tree::get_root_json(
                    array(\availability_group\condition::get_json($specialgroup->id))))));
        groups_add_member($specialgroup, $this->students[0]);
        groups_add_member($specialgroup, $this->students[1]);
        $this->assertEquals(2, count($setask->list_participants(null, true)));
    }

    public function test_count_teams() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask1 = $this->create_instance(array('teamsubmission' => 1));
        $this->assertEquals(self::GROUP_COUNT + 1, $setask1->count_teams());

        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $this->course->id));
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $this->groups[0]->id, 'groupingid' => $grouping->id));
        $this->getDataGenerator()->create_grouping_group(array('groupid' => $this->groups[1]->id, 'groupingid' => $grouping->id));

        // No active group and non group submissions allowed => 2 groups + the default one.
        $params = array(
            'teamsubmission' => 1,
            'teamsubmissiongroupingid' => $grouping->id,
            'preventsubmissionnotingroup' => false
        );
        $setask2 = $this->create_instance($params);
        $this->assertEquals(3, $setask2->count_teams());

        // An active group => Just the selected one.
        $this->assertEquals(1, $setask2->count_teams($this->groups[0]->id));

        // No active group and non group submissions allowed => 2 groups + no default one.
        $params = array('teamsubmission' => 1, 'teamsubmissiongroupingid' => $grouping->id, 'preventsubmissionnotingroup' => true);
        $setask3 = $this->create_instance($params);
        $this->assertEquals(2, $setask3->count_teams());

        $setask4 = $this->create_instance(array('teamsubmission' => 1, 'preventsubmissionnotingroup' => true));
        $this->assertEquals(self::GROUP_COUNT, $setask4->count_teams());
    }

    public function test_submit_to_default_group() {
        global $DB, $SESSION;

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $this->setUser($this->editingteachers[0]);
        $params = array('teamsubmission' => 1,
                        'setasksubmission_onlinetext_enabled' => 1,
                        'submissiondrafts' => 0,
                        'groupmode' => VISIBLEGROUPS);
        $setask = $this->create_instance($params);

        $newstudent = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname'=>'student'));
        $this->getDataGenerator()->enrol_user($newstudent->id,
                                              $this->course->id,
                                              $studentrole->id);
        $this->setUser($newstudent);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $notices = array();

        $group = $setask->get_submission_group($newstudent->id);
        $this->assertFalse($group, 'New student is in default group');
        $setask->save_submission($data, $notices);
        $this->assertEmpty($notices, 'No errors on save submission');

        // Set active groups to all groups.
        $this->setUser($this->teachers[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = 0;
        $this->assertEquals(1, $setask->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        // Set an active group.
        $anothergroup = $this->groups[0];
        $SESSION->activegroup[$this->course->id]['aag'][0] = (int)$anothergroup->id;
        $this->assertEquals(0, $setask->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        $sink->close();
    }

    public function test_count_submissions() {
        global $SESSION;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask1 = $this->create_instance(array('setasksubmission_onlinetext_enabled' => 1));

        // Simulate a submission.
        $this->setUser($this->extrastudents[0]);
        $submission = $setask1->get_user_submission($this->extrastudents[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $setask1->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);
        // Leave this one as DRAFT.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask1->testable_apply_grade_to_user($data, $this->extrastudents[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->extrastudents[1]);
        $submission = $setask1->get_user_submission($this->extrastudents[1]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask1->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[2]);
        $submission = $setask1->get_user_submission($this->extrastudents[2]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask1->testable_update_submission($submission, $this->extrastudents[2]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[3]);
        $submission = $setask1->get_user_submission($this->extrastudents[3]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask1->testable_update_submission($submission, $this->extrastudents[3]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission for suspended user, this will never be counted.
        $this->setUser($this->extrastudents[3]);
        $submission = $setask1->get_user_submission($this->extrasuspendedstudents[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask1->testable_update_submission($submission, $this->extrasuspendedstudents[0]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask1->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Wait 1 second so the submission and grade do not have the same timemodified.
        sleep(1);
        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask1->testable_apply_grade_to_user($data, $this->extrastudents[3]->id, 0);
        $setask1->testable_apply_grade_to_user($data, $this->extrasuspendedstudents[0]->id, 0);

        // Create a new submission with status NEW.
        $this->setUser($this->extrastudents[4]);
        $submission = $setask1->get_user_submission($this->extrastudents[4]->id, true);

        $this->assertEquals(2, $setask1->count_grades());
        $this->assertEquals(4, $setask1->count_submissions());
        $this->assertEquals(5, $setask1->count_submissions(true));
        $this->assertEquals(2, $setask1->count_submissions_need_grading());
        $this->assertEquals(3, $setask1->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(1, $setask1->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_DRAFT));

        // Groups.
        $setask2 = $this->create_instance(array(
            'setasksubmission_onlinetext_enabled' => 1,
            'groupmode' => VISIBLEGROUPS
        ));

        $this->setUser($this->extrastudents[1]);
        $submission = $setask2->get_user_submission($this->extrastudents[1]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask2->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $setask2->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->assertEquals(1, $setask2->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        // Set active groups to all groups.
        $this->setUser($this->teachers[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = 0;
        $this->assertEquals(1, $setask2->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        // Set the user group.
        $studentgroups = groups_get_user_groups($this->course->id, $this->extrastudents[1]->id);
        $this->assertEquals(1, count($studentgroups));
        $studentgroup = array_pop($studentgroups);
        $SESSION->activegroup[$this->course->id]['aag'][0] = $studentgroup[0];
        $this->assertEquals(1, $setask2->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        // Set another group.
        $anothergroup = $this->groups[0];
        $this->assertNotEquals($anothergroup->id, $studentgroup[0]);
        $SESSION->activegroup[$this->course->id]['aag'][0] = (int)$anothergroup->id;
        $this->assertEquals(0, $setask2->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));
    }

    public function test_count_submissions_for_groups() {
        $this->create_extra_users();
        $groupid = null;
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled' => 1, 'teamsubmission' => 1));

        // Simulate a submission.
        $this->setUser($this->extrastudents[0]);
        $submission = $setask->get_group_submission($this->extrastudents[0]->id, $groupid, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);
        // Leave this one as DRAFT.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->extrastudents[0]->id, 0);

        // Simulate a submission.
        $this->setUser($this->extrastudents[1]);
        $submission = $setask->get_group_submission($this->extrastudents[1]->id, $groupid, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[1]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[2]);
        $submission = $setask->get_group_submission($this->extrastudents[2]->id, $groupid, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[2]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate a submission.
        $this->setUser($this->extrastudents[3]);
        $submission = $setask->get_group_submission($this->extrastudents[3]->id, $groupid, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[3]->id, true, false);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                                         'text' => 'Submission text',
                                         'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->extrastudents[3]->id, 0);
        $setask->testable_apply_grade_to_user($data, $this->extrasuspendedstudents[0]->id, 0);

        // Create a new submission with status NEW.
        $this->setUser($this->extrastudents[4]);
        $submission = $setask->get_group_submission($this->extrastudents[4]->id, $groupid, true);

        $this->assertEquals(2, $setask->count_grades());
        $this->assertEquals(4, $setask->count_submissions());
        $this->assertEquals(5, $setask->count_submissions(true));
        $this->assertEquals(3, $setask->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));
        $this->assertEquals(1, $setask->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_DRAFT));
    }

    public function test_get_grading_userid_list() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        $users = $setask->testable_get_grading_userid_list();
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT, count($users));

        $this->setUser($this->editingteachers[0]);
        set_user_preference('grade_report_showonlyactiveenrol', false);
        $setask = $this->create_instance();

        $users = $setask->testable_get_grading_userid_list();
        $this->assertEquals(self::DEFAULT_STUDENT_COUNT + self::EXTRA_STUDENT_COUNT + self::EXTRA_SUSPENDED_COUNT, count($users));
    }

    public function test_cron() {
        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        setask::cron();

        // Now create an setaskment and add some feedback.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('sendstudentnotifications'=>1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        $setask->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        $data->sendstudentnotifications = false;
        $setask->testable_apply_grade_to_user($data, $this->students[2]->id, 0);

        // Now run cron and see that one message was sent.
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        cron_setup_user();
        $this->expectOutputRegex('/Done processing 2 setaskment submissions/');
        setask::cron();

        $messages = $sink->get_messages();
        // The sent count should be 2, because the 3rd one was marked as do not send notifications.
        $this->assertEquals(2, count($messages));
        $this->assertEquals(1, $messages[0]->notification);
        $this->assertEquals($setask->get_instance()->name, $messages[0]->contexturlname);

        // Regrading a grade causes a notification to the user.
        $data->sendstudentnotifications = true;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);
        setask::cron();
        $messages = $sink->get_messages();
        $this->assertEquals(3, count($messages));
    }

    /**
     * Test delivery of grade notifications as controlled by marking workflow.
     */
    public function test_markingworkflow_cron() {
        // First run cron so there are no messages waiting to be sent (from other tests).
        cron_setup_user();
        setask::cron();

        // Now create an setaskment with marking workflow enabled.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('sendstudentnotifications' => 1, 'markingworkflow' => 1));

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';

        // This student will not receive notification.
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // This student will receive notification.
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
        $setask->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        // Now run cron and see that one message was sent.
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();
        cron_setup_user();
        $this->expectOutputRegex('/Done processing 1 setaskment submissions/');
        setask::cron();

        $messages = $sink->get_messages();
        $this->assertEquals(1, count($messages));
        $this->assertEquals($messages[0]->useridto, $this->students[1]->id);
        $this->assertEquals($setask->get_instance()->name, $messages[0]->contexturlname);
    }

    public function test_is_graded() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        $this->assertEquals(true, $setask->testable_is_graded($this->students[0]->id));
        $this->assertEquals(false, $setask->testable_is_graded($this->students[1]->id));
    }

    public function test_can_grade() {
        global $DB;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        $this->setUser($this->students[0]);
        $this->assertEquals(false, $setask->can_grade());
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(true, $setask->can_grade());
        $this->setUser($this->teachers[0]);
        $this->assertEquals(true, $setask->can_grade());

        // Test the viewgrades capability - without mod/setask:grade.
        $this->setUser($this->students[0]);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        setask_capability('mod/setask:viewgrades', CAP_ALLOW, $studentrole->id, $setask->get_context()->id);
        $this->assertEquals(false, $setask->can_grade());
    }

    public function test_can_view_submission() {
        global $DB;

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        $this->setUser($this->students[0]);
        $this->assertEquals(true, $setask->can_view_submission($this->students[0]->id));
        $this->assertEquals(false, $setask->can_view_submission($this->students[1]->id));
        $this->assertEquals(false, $setask->can_view_submission($this->teachers[0]->id));
        $this->setUser($this->teachers[0]);
        $this->assertEquals(true, $setask->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(false, $setask->can_view_submission($this->extrasuspendedstudents[0]->id));
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(true, $setask->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->extrasuspendedstudents[0]->id));

        // Test the viewgrades capability - without mod/setask:grade.
        $this->setUser($this->students[0]);
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        setask_capability('mod/setask:viewgrades', CAP_ALLOW, $studentrole->id, $setask->get_context()->id);
        $this->assertEquals(true, $setask->can_view_submission($this->students[0]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->students[1]->id));
        $this->assertEquals(true, $setask->can_view_submission($this->teachers[0]->id));
        $this->assertEquals(false, $setask->can_view_submission($this->extrasuspendedstudents[0]->id));
    }


    public function test_update_submission() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $setask->get_user_submission($this->extrastudents[0]->id, true);
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);

        $this->setUser($this->teachers[0]);
        // Verify the gradebook update.
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals($this->extrastudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->usermodified);

        // Now verify group setaskments.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('teamsubmission'=>1));

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $setask->get_group_submission($this->extrastudents[0]->id, 0, true);
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that at least 2 active members and 1 suspended member of the submission group had their submission updated.

        $this->setUser($this->editingteachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals($this->extrastudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[self::GROUP_COUNT]->id);

        $this->assertEquals($this->extrastudents[self::GROUP_COUNT]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[self::GROUP_COUNT]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrasuspendedstudents[0]->id);
        $this->assertEquals($this->extrasuspendedstudents[0]->id,
                            $gradinginfo->items[0]->grades[$this->extrasuspendedstudents[0]->id]->usermodified);

        // Check the same with non-editing teacher and make sure submission is not updated for suspended user.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('teamsubmission'=>1));

        $this->setUser($this->extrastudents[1]);
        $now = time();
        $submission = $setask->get_group_submission($this->extrastudents[1]->id, 0, true);
        $setask->testable_update_submission($submission, $this->extrastudents[1]->id, true, true);

        $this->setUser($this->teachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[1]->id);

        $this->assertEquals($this->extrastudents[1]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[1]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[self::GROUP_COUNT+1]->id);

        $this->assertEquals($this->extrastudents[self::GROUP_COUNT+1]->id,
                            $gradinginfo->items[0]->grades[$this->extrastudents[self::GROUP_COUNT+1]->id]->usermodified);

        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrasuspendedstudents[1]->id);
        $this->assertEquals($this->extrasuspendedstudents[1]->id,
                            $gradinginfo->items[0]->grades[$this->extrasuspendedstudents[1]->id]->usermodified);

        // Now verify blind marking.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('blindmarking'=>1));

        $this->setUser($this->extrastudents[0]);
        $now = time();
        $submission = $setask->get_user_submission($this->extrastudents[0]->id, true);
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, false);

        $this->setUser($this->editingteachers[0]);
        $gradinginfo = grade_get_grades($this->course->id,
                                        'mod',
                                        'setask',
                                        $setask->get_instance()->id,
                                        $this->extrastudents[0]->id);

        $this->assertEquals(null, $gradinginfo->items[0]->grades[$this->extrastudents[0]->id]->datesubmitted);
    }

    public function test_group_submissions_submit_for_marking_requireallteammemberssubmit() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group setaskments.
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('teamsubmission'=>1,
                                               'setasksubmission_onlinetext_enabled'=>1,
                                               'submissiondrafts'=>1,
                                               'requireallteammemberssubmit'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Add a submission.
        $this->setUser($this->extrastudents[0]);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $setask->save_submission($data, $notices);

        // Check we can see the submit button.
        $output = $setask->view_student_summary($this->extrastudents[0], true);
        $this->assertContains(get_string('submitsetaskment', 'setask'), $output);

        $submission = $setask->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $setask->view_student_summary($this->extrastudents[0], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output);

        // Change to another user in the same group.
        $this->setUser($this->extrastudents[self::GROUP_COUNT]);
        $output = $setask->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertContains(get_string('submitsetaskment', 'setask'), $output);

        $submission = $setask->get_group_submission($this->extrastudents[self::GROUP_COUNT]->id, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[self::GROUP_COUNT]->id, true, true);
        $output = $setask->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output);
    }

    public function test_group_submissions_submit_for_marking() {
        global $PAGE;

        $this->create_extra_users();
        // Now verify group setaskments.
        $this->setUser($this->editingteachers[0]);
        $time = time();
        $setask = $this->create_instance(array('teamsubmission'=>1,
                                               'setasksubmission_onlinetext_enabled'=>1,
                                               'submissiondrafts'=>1,
                                               'requireallteammemberssubmit'=>0,
                                               'duedate' => $time - 2*24*60*60));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        $this->setUser($this->extrastudents[0]);
        // Add a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $setask->save_submission($data, $notices);

        // Check we can see the submit button.
        $output = $setask->view_student_summary($this->extrastudents[0], true);
        $this->assertContains(get_string('submitsetaskment', 'setask'), $output);
        $this->assertContains(get_string('timeremaining', 'setask'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('overdue', 'setask', format_time(2*24*60*60 + $difftime)), $output);

        $submission = $setask->get_group_submission($this->extrastudents[0]->id, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[0]->id, true, true);

        // Check that the student does not see "Submit" button.
        $output = $setask->view_student_summary($this->extrastudents[0], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output);

        // Change to another user in the same group.
        $this->setUser($this->extrastudents[self::GROUP_COUNT]);
        $output = $setask->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output);

        // Check that time remaining is not overdue.
        $this->assertContains(get_string('timeremaining', 'setask'), $output);
        $difftime = time() - $time;
        $this->assertContains(get_string('submittedlate', 'setask', format_time(2*24*60*60 + $difftime)), $output);

        $submission = $setask->get_group_submission($this->extrastudents[self::GROUP_COUNT]->id, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->extrastudents[self::GROUP_COUNT]->id, true, true);
        $output = $setask->view_student_summary($this->extrastudents[self::GROUP_COUNT], true);
        $this->assertNotContains(get_string('submitsetaskment', 'setask'), $output);
    }

    public function test_submissions_open() {
        $this->setUser($this->editingteachers[0]);

        $now = time();
        $tomorrow = $now + 24*60*60;
        $oneweek = $now + 7*24*60*60;
        $yesterday = $now - 24*60*60;

        $setask = $this->create_instance();
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $setask = $this->create_instance(array('duedate'=>$tomorrow));
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $setask = $this->create_instance(array('duedate'=>$yesterday));
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $setask = $this->create_instance(array('duedate'=>$yesterday, 'cutoffdate'=>$tomorrow));
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $setask = $this->create_instance(array('duedate'=>$yesterday, 'cutoffdate'=>$yesterday));
        $this->assertEquals(false, $setask->testable_submissions_open($this->students[0]->id));

        $setask->testable_save_user_extension($this->students[0]->id, $tomorrow);
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $setask = $this->create_instance(array('submissiondrafts'=>1));
        $this->assertEquals(true, $setask->testable_submissions_open($this->students[0]->id));

        $this->setUser($this->students[0]);
        $now = time();
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);
        $this->setUser($this->editingteachers[0]);
        $this->assertEquals(false, $setask->testable_submissions_open($this->students[0]->id));
    }

    public function test_get_graders() {
        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);

        // Create an setaskment with no groups.
        $setask = $this->create_instance();
        $this->assertCount(self::DEFAULT_TEACHER_COUNT +
                           self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $setask->testable_get_graders($this->students[0]->id));

        // Force create an setaskment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_setask_grouping($groupingid, $this->groups[0]->id);
        $setask = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        $this->setUser($this->students[1]);
        $this->assertCount(4, $setask->testable_get_graders($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(10, $setask->testable_get_graders($this->students[1]->id));
    }

    public function test_get_notified_users() {
        global $CFG, $DB;

        $capability = 'mod/setask:receivegradernotifications';
        $coursecontext = context_course::instance($this->course->id);
        $role = $DB->get_record('role', array('shortname' => 'teacher'));

        $this->create_extra_users();
        $this->setUser($this->editingteachers[0]);

        // Create an setaskment with no groups.
        $setask = $this->create_instance();

        $this->assertCount(self::DEFAULT_TEACHER_COUNT +
                           self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $setask->testable_get_notifiable_users($this->students[0]->id));

        // Change nonediting teachers role to not receive grader notifications.
        setask_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        $this->assertCount(self::DEFAULT_EDITING_TEACHER_COUNT +
                           self::EXTRA_EDITING_TEACHER_COUNT,
                           $setask->testable_get_notifiable_users($this->students[0]->id));

        // Reset nonediting teachers role to default.
        unsetask_capability($capability, $role->id, $coursecontext);

        // Force create an setaskment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_setask_grouping($groupingid, $this->groups[0]->id);
        $setask = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        $this->setUser($this->students[1]);
        $this->assertCount(4, $setask->testable_get_notifiable_users($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(10, $setask->testable_get_notifiable_users($this->students[1]->id));

        // Change nonediting teachers role to not receive grader notifications.
        setask_capability($capability, CAP_PROHIBIT, $role->id, $coursecontext);

        $this->assertCount(2, $setask->testable_get_notifiable_users($this->students[0]->id));
        // Note the second student is in a group that is not in the grouping.
        // This means that we get all graders that are not in a group in the grouping.
        $this->assertCount(5, $setask->testable_get_notifiable_users($this->students[1]->id));
    }

    public function test_group_members_only() {
        global $CFG;

        $this->setAdminUser();
        $this->create_extra_users();
        $CFG->enableavailability = true;
        $grouping = $this->getDataGenerator()->create_grouping(array('courseid' => $this->course->id));
        groups_setask_grouping($grouping->id, $this->groups[0]->id);

        // Force create an setaskment with SEPARATEGROUPS.
        $instance = $this->getDataGenerator()->create_module('setask', array('course'=>$this->course->id),
                array('availability' => json_encode(\core_availability\tree::get_root_json(array(
                    \availability_grouping\condition::get_json()))),
                'groupingid' => $grouping->id));

        $cm = get_coursemodule_from_instance('setask', $instance->id);
        $context = context_module::instance($cm->id);
        $setask = new testable_setask($context, $cm, $this->course);

        $this->setUser($this->teachers[0]);
        get_fast_modinfo($this->course, 0, true);
        $this->assertCount(5, $setask->list_participants(0, true));

    }

    public function test_get_uniqueid_for_user() {
        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();

        foreach ($this->students as $student) {
            $uniqueid = $setask->get_uniqueid_for_user($student->id);
            $this->assertEquals($student->id, $setask->get_user_id_for_uniqueid($uniqueid));
        }
    }

    public function test_show_student_summary() {
        global $CFG, $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // No feedback should be available because this student has not been graded.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if there is no grade');
        // Simulate adding a grade.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Now we should see the feedback.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, 'Feedback'), 'Show feedback if there is a grade');

        // Now hide the grade in gradebook.
        $this->setUser($this->teachers[0]);
        require_once($CFG->libdir.'/gradelib.php');
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'setask',
            'iteminstance'  => $setask->get_instance()->id,
            'courseid'      => $this->course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if the grade is hidden in the gradebook');

        // Do the same but add feedback.
        $setask = $this->create_instance(array('setaskfeedback_comments_enabled' => 1));

        $this->setUser($this->teachers[0]);
        $grade = $setask->get_user_grade($this->students[0]->id, true);
        $data = new stdClass();
        $data->setaskfeedbackcomments_editor = array('text'=>'Tomato sauce',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_feedback_plugin_by_type('comments');
        $plugin->save($grade, $data);

        // Should have feedback but no grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, 'Feedback'), 'Show feedback even if there is no grade');
        $this->assertEquals(false, strpos($output, 'Grade'), 'Do not show grade when there is no grade.');
        $this->assertEquals(false, strpos($output, 'Graded on'), 'Do not show graded date when there is no grade.');

        // Now hide the grade in gradebook.
        $this->setUser($this->teachers[0]);
        $gradeitem = new grade_item(array(
            'itemtype'      => 'mod',
            'itemmodule'    => 'setask',
            'iteminstance'  => $setask->get_instance()->id,
            'courseid'      => $this->course->id));

        $gradeitem->set_hidden(1, false);

        // No feedback should be available because the grade is hidden.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, 'Feedback'), 'Do not show feedback if the grade is hidden in the gradebook');
    }

    public function test_attempt_reopen_method_manual() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('attemptreopenmethod'=>ASSIGN_ATTEMPT_REOPEN_METHOD_MANUAL,
                                               'maxattempts'=>3,
                                               'submissiondrafts'=>1,
                                               'setasksubmission_onlinetext_enabled'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'setask')));

        // Add a submission.
        $now = time();
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Verify the student cannot make changes to the submission.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addsubmission', 'setask')));

        // Mark the submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Allow the student another attempt.
        $this->teachers[0]->ignoresesskey = true;
        $this->setUser($this->teachers[0]);
        $result = $setask->testable_process_add_attempt($this->students[0]->id);
        $this->assertEquals(true, $result);

        // Check that the previous attempt is now in the submission history table.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        // Need a better check.
        $this->assertNotEquals(false, strpos($output, 'Submission text'), 'Contains: Submission text');

        // Check that the student now has a button for Add a new attempt".
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'setask')));
        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitsetaskment', 'setask')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'setask')));

        $this->setUser($this->teachers[0]);
        // Check that the grading table loads correctly and contains this user.
        // This is also testing that we do not get duplicate rows in the grading table.
        $gradingtable = new setask_grading_table($setask, 100, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, $this->students[0]->lastname));

        // Should be 1 not 2.
        $this->assertEquals(1, $setask->count_submissions());
        $this->assertEquals(1, $setask->count_submissions_with_status('reopened'));
        $this->assertEquals(0, $setask->count_submissions_need_grading());
        $this->assertEquals(1, $setask->count_grades());

        // Change max attempts to unlimited.
        $formdata = clone($setask->get_instance());
        $formdata->maxattempts = ASSIGN_UNLIMITED_ATTEMPTS;
        $formdata->instance = $formdata->id;
        $setask->update_instance($formdata);

        // Mark the submission again.
        $data = new stdClass();
        $data->grade = '60.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check the grade exists.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEquals(60, (int)$grades[$this->students[0]->id]->rawgrade);

        // Check we can reopen still.
        $result = $setask->testable_process_add_attempt($this->students[0]->id);
        $this->assertEquals(true, $result);

        // Should no longer have a grade because there is no grade for the latest attempt.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

    }

    /**
     * Test reopen behavior when in "Reopen until pass" mode.
     */
    public function test_attempt_reopen_method_untilpass() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('attemptreopenmethod' => ASSIGN_ATTEMPT_REOPEN_METHOD_UNTILPASS,
                'maxattempts' => 3,
                'submissiondrafts' => 1,
                'setasksubmission_onlinetext_enabled' => 1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Set grade to pass to 80.
        $gradeitem = $setask->get_grade_item();
        $gradeitem->gradepass = '80.0';
        $gradeitem->update();

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'setask')));

        // Add a submission.
        $now = time();
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Verify the student cannot make a new attempt.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'setask')));

        // Mark the submission as non-passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Check that the student now has a button for Add a new attempt.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'setask')));

        // Check that the student now does not have a button for Submit.
        $this->assertEquals(false, strpos($output, get_string('submitsetaskment', 'setask')));

        // Check that the student now has a submission history.
        $this->assertNotEquals(false, strpos($output, get_string('attempthistory', 'setask')));

        // Add a second submission.
        $now = time();
        $submission = $setask->get_user_submission($this->students[0]->id, true, 1);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[0]->id, true, false);

        // Mark the submission as passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '80.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check that the student does not have a button for Add a new attempt.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'setask')));

        // Re-mark the submission as not passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 1);

        // Check that the student now has a button for Add a new attempt.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addnewattempt', 'setask')));

        // Add a submission as a second student.
        $this->setUser($this->students[1]);
        $now = time();
        $submission = $setask->get_user_submission($this->students[1]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[1]->id, true, false);

        // Mark the submission as passing.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '100.0';
        $setask->testable_apply_grade_to_user($data, $this->students[1]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[1]);
        $output = $setask->view_student_summary($this->students[1], true);
        $this->assertNotEquals(false, strpos($output, '100.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $setask->view_student_summary($this->students[1], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'setask')));

        // Set grade to pass to 0, so that no attempts should reopen.
        $gradeitem = $setask->get_grade_item();
        $gradeitem->gradepass = '0';
        $gradeitem->update();

        // Add another submission.
        $this->setUser($this->students[2]);
        $now = time();
        $submission = $setask->get_user_submission($this->students[2]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid' => file_get_unused_draft_itemid(),
                'text' => 'Submission text',
                'format' => FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // And now submit it for marking.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $setask->testable_update_submission($submission, $this->students[2]->id, true, false);

        // Mark the submission as graded.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '0.0';
        $setask->testable_apply_grade_to_user($data, $this->students[2]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[2]);
        $output = $setask->view_student_summary($this->students[2], true);
        $this->assertNotEquals(false, strpos($output, '0.0'));

        // Check that the student does not have a button for Add a new attempt.
        $output = $setask->view_student_summary($this->students[2], true);
        $this->assertEquals(false, strpos($output, get_string('addnewattempt', 'setask')));
    }


    public function test_markingworkflow() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('markingworkflow'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Mark the submission and set to notmarked.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inmarking.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INMARKING;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforreview.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to inreview.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to readyforrelease.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can't see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertEquals(false, strpos($output, '50.0'));

        // Make sure the grade isn't pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEmpty($grades);

        // Mark the submission and set to released.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->grade = '50.0';
        $data->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_RELEASED;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the student can see the grade.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, '50.0'));

        // Make sure the grade is pushed to the gradebook.
        $grades = $setask->get_user_grades_for_gradebook($this->students[0]->id);
        $this->assertEquals(50, (int)$grades[$this->students[0]->id]->rawgrade);
    }

    public function test_markerallocation() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance(array('markingworkflow'=>1, 'markingallocation'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Allocate marker to submission.
        $data = new stdClass();
        $data->allocatedmarker = $this->teachers[0]->id;
        $setask->testable_apply_grade_to_user($data, $this->students[0]->id, 0);

        // Check the allocated marker can view the submission.
        $this->setUser($this->teachers[0]);
        $gradingtable = new setask_grading_table($setask, 100, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, $this->students[0]->lastname));

        // Check that other teachers can't view this submission.
        $this->setUser($this->teachers[1]);
        $gradingtable = new setask_grading_table($setask, 100, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertNotEquals(true, strpos($output, $this->students[0]->lastname));
    }



    public function test_teacher_submit_for_student() {
        global $PAGE;

        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $this->setUser($this->editingteachers[0]);

        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1, 'submissiondrafts'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        $this->setUser($this->students[0]);
        // Simulate a submission.
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Student submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $setask->save_submission($data, $notices);

        // Check that the submission text was saved.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertContains('Student submission text', $output, 'Contains student submission text');

        // Check that a teacher teacher with the extra capability can edit a students submission.
        $this->setUser($this->teachers[0]);
        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Teacher edited submission text',
                                         'format'=>FORMAT_MOODLE);

        // Add the required capability.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        setask_capability('mod/setask:editothersubmission', CAP_ALLOW, $roleid, $setask->get_context()->id);
        role_setask($roleid, $this->teachers[0]->id, $setask->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Try to save the submission.
        $notices = array();
        $setask->save_submission($data, $notices);

        // Check that the teacher can submit the students work.
        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $notices = array();
        $setask->submit_for_grading($data, $notices);

        // Revert to draft so the student can edit it.
        $setask->revert_to_draft($this->students[0]->id);

        $this->setUser($this->students[0]);

        // Check that the submission text was saved.
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertContains('Teacher edited submission text', $output, 'Contains student submission text');

        // Check that the student can submit their work.
        $data = new stdClass();
        $setask->submit_for_grading($data, $notices);

        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotContains(get_string('addsubmission', 'setask'), $output);

        // Set to a default editing teacher who should not be able to edit this submission.
        $this->setUser($this->editingteachers[1]);

        // Revert to draft so the submission is editable.
        $setask->revert_to_draft($this->students[0]->id);

        $data = new stdClass();
        $data->userid = $this->students[0]->id;
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Teacher 2 edited submission text',
                                         'format'=>FORMAT_MOODLE);

        $notices = array();
        $this->setExpectedException('moodle_exception');
        $setask->save_submission($data, $notices);

        $sink->close();
    }

    public function test_disable_submit_after_cutoff_date() {
        global $PAGE;

        $this->setUser($this->editingteachers[0]);
        $now = time();
        $tomorrow = $now + 24*60*60;
        $lastweek = $now - 7*24*60*60;
        $yesterday = $now - 24*60*60;

        $setask = $this->create_instance(array('duedate'=>$yesterday,
                                               'cutoffdate'=>$tomorrow,
                                               'setasksubmission_onlinetext_enabled'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Student should be able to see an add submission button.
        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotEquals(false, strpos($output, get_string('addsubmission', 'setask')));

        // Add a submission but don't submit now.
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>'Submission text',
                                         'format'=>FORMAT_MOODLE);
        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        // Create another instance with cut-off and due-date already passed.
        $this->setUser($this->editingteachers[0]);
        $now = time();
        $setask = $this->create_instance(array('duedate'=>$lastweek,
                                               'cutoffdate'=>$yesterday,
                                               'setasksubmission_onlinetext_enabled'=>1));

        $this->setUser($this->students[0]);
        $output = $setask->view_student_summary($this->students[0], true);
        $this->assertNotContains($output, get_string('editsubmission', 'setask'),
                                 'Should not be able to edit after cutoff date.');
        $this->assertNotContains($output, get_string('submitsetaskment', 'setask'),
                                 'Should not be able to submit after cutoff date.');
    }
    /**
     * Testing for submission comment plugin settings
     */
    public function test_submission_comment_plugin_settings() {
        global $CFG;

        $commentconfig = false;
        if (!empty($CFG->usecomments)) {
            $commentconfig = $CFG->usecomments;
        }

        $CFG->usecomments = true;
        $setask = $this->create_instance();
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setasksubmission_comments_enabled' => 0));
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setasksubmission_comments_enabled' => 1));
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));

        $CFG->usecomments = false;
        $setask = $this->create_instance();
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setasksubmission_comments_enabled' => 0));
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setasksubmission_comments_enabled' => 1));
        $plugin = $setask->get_submission_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $CFG->usecomments = $commentconfig;
    }

    /**
     * Testing for comment inline settings
     */
    public function test_feedback_comment_commentinline() {
        global $CFG;

        $sourcetext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src='https://moodle.org/logo/logo-240x60.gif' alt='Moodle'/>
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\"/>
Internal link 1:<img src='@@PLUGINFILE@@/logo-240x60.gif' alt='Moodle'/>
Internal link 2:<img alt=\"Moodle\" src=\"@@PLUGINFILE@@logo-240x60.gif\"/>
Anchor link 1:<a href=\"@@PLUGINFILE@@logo-240x60.gif\" alt=\"bananas\">Link text</a>
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        // Note the internal images have been stripped and the html is purified (quotes fixed in this case).
        $filteredtext = "Hello!

I'm writing to you from the Moodle Majlis in Muscat, Oman, where we just had several days of Moodle community goodness.

URL outside a tag: https://moodle.org/logo/logo-240x60.gif
Plugin url outside a tag: @@PLUGINFILE@@/logo-240x60.gif

External link 1:<img src=\"https://moodle.org/logo/logo-240x60.gif\" alt=\"Moodle\" />
External link 2:<img alt=\"Moodle\" src=\"https://moodle.org/logo/logo-240x60.gif\" />
Internal link 1:
Internal link 2:
Anchor link 1:Link text
Anchor link 2:<a title=\"bananas\" href=\"../logo-240x60.gif\">Link text</a>
";

        $this->setUser($this->editingteachers[0]);
        $params = array('setasksubmission_onlinetext_enabled' => 1,
                        'setaskfeedback_comments_enabled' => 1,
                        'setaskfeedback_comments_commentinline' => 1);
        $setask = $this->create_instance($params);

        $this->setUser($this->students[0]);
        // Add a submission but don't submit now.
        $submission = $setask->get_user_submission($this->students[0]->id, true);
        $data = new stdClass();

        // Test the internal link is stripped, but the external one is not.
        $data->onlinetext_editor = array('itemid'=>file_get_unused_draft_itemid(),
                                         'text'=>$sourcetext,
                                         'format'=>FORMAT_MOODLE);

        $plugin = $setask->get_submission_plugin_by_type('onlinetext');
        $plugin->save($submission, $data);

        $this->setUser($this->editingteachers[0]);

        $data = new stdClass();
        require_once($CFG->dirroot . '/mod/setask/gradeform.php');
        $pagination = array('userid'=>$this->students[0]->id,
                            'rownum'=>0,
                            'last'=>true,
                            'useridlistid'=>time(),
                            'attemptnumber'=>0);
        $formparams = array($setask, $data, $pagination);
        $mform = new mod_setask_grade_form(null, $formparams);

        $this->assertEquals($filteredtext, $data->setaskfeedbackcomments_editor['text']);
    }

    /**
     * Testing for feedback comment plugin settings
     */
    public function test_feedback_plugin_settings() {

        $setask = $this->create_instance();
        $plugin = $setask->get_feedback_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setaskfeedback_comments_enabled' => 0));
        $plugin = $setask->get_feedback_plugin_by_type('comments');
        $this->assertEquals(0, $plugin->is_enabled('enabled'));

        $setask = $this->create_instance(array('setaskfeedback_comments_enabled' => 1));
        $plugin = $setask->get_feedback_plugin_by_type('comments');
        $this->assertEquals(1, $plugin->is_enabled('enabled'));
    }

    /**
     * Testing if gradebook feedback plugin is enabled.
     */
    public function test_is_gradebook_feedback_enabled() {
        $adminconfig = get_config('setask');
        $gradebookplugin = $adminconfig->feedback_plugin_for_gradebook;

        // Create setaskment with gradebook feedback enabled and grade = 0.
        $setask = $this->create_instance(array($gradebookplugin . '_enabled' => 1, 'grades' => 0));

        // Get gradebook feedback plugin.
        $gradebookplugintype = str_replace('setaskfeedback_', '', $gradebookplugin);
        $plugin = $setask->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(1, $plugin->is_enabled('enabled'));
        $this->assertEquals(1, $setask->is_gradebook_feedback_enabled());

        // Create setaskment with gradebook feedback disabled and grade = 0.
        $setask = $this->create_instance(array($gradebookplugin . '_enabled' => 0, 'grades' => 0));
        $plugin = $setask->get_feedback_plugin_by_type($gradebookplugintype);
        $this->assertEquals(0, $plugin->is_enabled('enabled'));
    }

    /**
     * Testing can_edit_submission
     */
    public function test_can_edit_submission() {
        global $PAGE, $DB;
        $this->create_extra_users();

        $this->setAdminUser();
        // Create setaskment (onlinetext).
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled'=>1, 'submissiondrafts'=>1));
        $PAGE->set_url(new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id)));

        // Check student can edit their own submission.
        $this->assertTrue($setask->can_edit_submission($this->students[0]->id, $this->students[0]->id));
        // Check student cannot edit others submission.
        $this->assertFalse($setask->can_edit_submission($this->students[0]->id, $this->students[1]->id));

        // Check teacher cannot (by default) edit a students submission.
        $this->assertFalse($setask->can_edit_submission($this->students[0]->id, $this->teachers[0]->id));

        // Add the required capability to edit a student submission.
        $roleid = create_role('Dummy role', 'dummyrole', 'dummy role description');
        setask_capability('mod/setask:editothersubmission', CAP_ALLOW, $roleid, $setask->get_context()->id);
        role_setask($roleid, $this->teachers[0]->id, $setask->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();
        // Retest - should now have access.
        $this->assertTrue($setask->can_edit_submission($this->students[0]->id, $this->teachers[0]->id));

        // Force create an setaskment with SEPARATEGROUPS.
        $data = new stdClass();
        $data->courseid = $this->course->id;
        $data->name = 'Grouping';
        $groupingid = groups_create_grouping($data);
        groups_setask_grouping($groupingid, $this->groups[0]->id);
        groups_setask_grouping($groupingid, $this->groups[1]->id);
        $setask = $this->create_instance(array('groupingid' => $groupingid, 'groupmode' => SEPARATEGROUPS));

        // Add the capability to the new setaskment for extra students 0 and 1.
        setask_capability('mod/setask:editothersubmission', CAP_ALLOW, $roleid, $setask->get_context()->id);
        role_setask($roleid, $this->extrastudents[0]->id, $setask->get_context()->id);
        role_setask($roleid, $this->extrastudents[1]->id, $setask->get_context()->id);
        accesslib_clear_all_caches_for_unit_testing();

        // Verify the extra student does not have the capability to edit a submission not in their group.
        $this->assertFalse($setask->can_edit_submission($this->students[0]->id, $this->extrastudents[1]->id));
        // Verify the extra student does have the capability to edit a submission in their group.
        $this->assertTrue($setask->can_edit_submission($this->students[0]->id, $this->extrastudents[0]->id));

    }

    /**
     * Test if the view blind details capability works
     */
    public function test_can_view_blind_details() {
        global $PAGE, $DB;
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        $managerrole = $DB->get_record('role', array('shortname' => 'manager'));

        $student = $this->students[0];// Get a student user.
        // Create a teacher. Shouldn't be able to view blind marking ID.
        $teacher = $this->getDataGenerator()->create_user();

        $this->getDataGenerator()->enrol_user($teacher->id,
                                              $this->course->id,
                                              $teacherrole->id);

        // Create a manager.. Should be able to view blind marking ID.
        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($manager->id,
                $this->course->id,
                $managerrole->id);

        // Generate blind marking setaskment.
        $setask = $this->create_instance(array('blindmarking' => 1));
        $this->assertEquals(true, $setask->is_blind_marking());

        // Test student names are hidden to teacher.
        $this->setUser($teacher);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(true, strpos($output, get_string('hiddenuser', 'setask')));    // "Participant" is somewhere on the page.
        $this->assertEquals(false, strpos($output, fullname($student)));    // Students full name doesn't appear.

        // Test student names are visible to manager.
        $this->setUser($manager);
        $gradingtable = new setask_grading_table($setask, 1, '', 0, true);
        $output = $setask->get_renderer()->render($gradingtable);
        $this->assertEquals(false, strpos($output, get_string('hiddenuser', 'setask')));
        $this->assertEquals(true, strpos($output, fullname($student)));    //students full name doesn't appear.
    }
}

