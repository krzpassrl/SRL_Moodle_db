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
 * Events tests.
 *
 * @package    setasksubmission_comments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/setask/lib.php');
require_once($CFG->dirroot . '/mod/setask/locallib.php');
require_once($CFG->dirroot . '/mod/setask/tests/base_test.php');

/**
 * Events tests class.
 *
 * @package    setasksubmission_comments
 * @category   test
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setasksubmission_comments_events_testcase extends mod_setask_base_testcase {

    /**
     * Test comment_created event.
     */
    public function test_comment_created() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();
        $submission = $setask->get_user_submission($this->students[0]->id, true);

        $context = $setask->get_context();
        $options = new stdClass();
        $options->area = 'submission_comments';
        $options->course = $setask->get_course();
        $options->context = $context;
        $options->itemid = $submission->id;
        $options->component = 'setasksubmission_comments';
        $options->showcount = true;
        $options->displaycancel = true;

        $comment = new comment($options);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->add('New comment');
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\setasksubmission_comments\event\comment_created', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }

    /**
     * Test comment_deleted event.
     */
    public function test_comment_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/comment/lib.php');

        $this->setUser($this->editingteachers[0]);
        $setask = $this->create_instance();
        $submission = $setask->get_user_submission($this->students[0]->id, true);

        $context = $setask->get_context();
        $options = new stdClass();
        $options->area    = 'submission_comments';
        $options->course    = $setask->get_course();
        $options->context = $context;
        $options->itemid  = $submission->id;
        $options->component = 'setasksubmission_comments';
        $options->showcount = true;
        $options->displaycancel = true;
        $comment = new comment($options);
        $newcomment = $comment->add('New comment 1');

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $comment->delete($newcomment->id);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\setasksubmission_comments\event\comment_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new moodle_url('/mod/setask/view.php', array('id' => $setask->get_course_module()->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
    }
}
