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
 * Unit tests for setaskfeedback_editpdf\comments_quick_list
 *
 * @package    setaskfeedback_editpdf
 * @category   phpunit
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use \setaskfeedback_editpdf\comments_quick_list;
use \setaskfeedback_editpdf\document_services;
use \setaskfeedback_editpdf\page_editor;
use \setaskfeedback_editpdf\pdf;
use \setaskfeedback_editpdf\comment;
use \setaskfeedback_editpdf\annotation;

global $CFG;
require_once($CFG->dirroot . '/mod/setask/tests/base_test.php');

/**
 * Unit tests for setaskfeedback_editpdf\comments_quick_list
 *
 * @copyright  2013 Damyon Wiese
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setaskfeedback_editpdf_testcase extends mod_setask_base_testcase {

    protected function setUp() {
        // Skip this test if ghostscript is not supported.
        if (!pdf::test_gs_path(false)) {
            $this->markTestSkipped('Ghostscript not setup');
            return;
        }
        parent::setUp();
    }

    protected function create_setask_and_submit_pdf() {
        global $CFG;
        $setask = $this->create_instance(array('setasksubmission_onlinetext_enabled' => 1,
                                               'setasksubmission_file_enabled' => 1,
                                               'setasksubmission_file_maxfiles' => 1,
                                               'setaskfeedback_editpdf_enabled' => 1,
                                               'setasksubmission_file_maxsizebytes' => 1000000));

        $user = $this->students[0];
        $this->setUser($user);

        // Create a file submission with the test pdf.
        $submission = $setask->get_user_submission($user->id, true);

        $fs = get_file_storage();
        $pdfsubmission = (object) array(
            'contextid' => $setask->get_context()->id,
            'component' => 'setasksubmission_file',
            'filearea' => ASSIGNSUBMISSION_FILE_FILEAREA,
            'itemid' => $submission->id,
            'filepath' => '/',
            'filename' => 'submission.pdf'
        );
        $sourcefile = $CFG->dirroot.'/mod/setask/feedback/editpdf/tests/fixtures/submission.pdf';
        $fi = $fs->create_file_from_pathname($pdfsubmission, $sourcefile);

        $data = new stdClass();
        $plugin = $setask->get_submission_plugin_by_type('file');
        $plugin->save($submission, $data);

        return $setask;
    }

    public function test_comments_quick_list() {

        $this->setUser($this->teachers[0]);

        $comments = comments_quick_list::get_comments();

        $this->assertEmpty($comments);

        $comment = comments_quick_list::add_comment('test', 45, 'red');

        $comments = comments_quick_list::get_comments();

        $this->assertEquals(count($comments), 1);
        $first = reset($comments);
        $this->assertEquals($comment, $first);

        $commentbyid = comments_quick_list::get_comment($comment->id);
        $this->assertEquals($comment, $commentbyid);

        $result = comments_quick_list::remove_comment($comment->id);

        $this->assertTrue($result);

        $comments = comments_quick_list::get_comments();
        $this->assertEmpty($comments);
    }

    public function test_page_editor() {

        $setask = $this->create_setask_and_submit_pdf();
        $this->setUser($this->teachers[0]);

        $grade = $setask->get_user_grade($this->students[0]->id, true);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);

        $comment = new comment();

        $comment->rawtext = 'Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';

        $comment2 = new comment();

        $comment2->rawtext = 'Comment text 2';
        $comment2->width = 100;
        $comment2->x = 200;
        $comment2->y = 100;
        $comment2->colour = 'clear';

        page_editor::set_comments($grade->id, 0, array($comment, $comment2));

        $annotation = new annotation();

        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';

        $annotation2 = new annotation();

        $annotation2->path = '';
        $annotation2->x = 100;
        $annotation2->y = 100;
        $annotation2->endx = 200;
        $annotation2->endy = 200;
        $annotation2->type = 'rectangle';
        $annotation2->colour = 'yellow';

        page_editor::set_annotations($grade->id, 0, array($annotation, $annotation2));

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        // Still empty because all edits are still drafts.
        $this->assertFalse($notempty);

        $comments = page_editor::get_comments($grade->id, 0, false);

        $this->assertEmpty($comments);

        $comments = page_editor::get_comments($grade->id, 0, true);

        $this->assertEquals(count($comments), 2);

        $annotations = page_editor::get_annotations($grade->id, 0, false);

        $this->assertEmpty($annotations);

        $annotations = page_editor::get_annotations($grade->id, 0, true);

        $this->assertEquals(count($annotations), 2);

        $comment = reset($comments);
        $annotation = reset($annotations);

        page_editor::remove_comment($comment->id);
        page_editor::remove_annotation($annotation->id);

        $comments = page_editor::get_comments($grade->id, 0, true);

        $this->assertEquals(count($comments), 1);

        $annotations = page_editor::get_annotations($grade->id, 0, true);

        $this->assertEquals(count($annotations), 1);

        page_editor::release_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);

        $this->assertTrue($notempty);

        page_editor::unrelease_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);

        $this->assertFalse($notempty);
    }

    public function test_document_services() {

        $setask = $this->create_setask_and_submit_pdf();
        $this->setUser($this->teachers[0]);

        $grade = $setask->get_user_grade($this->students[0]->id, true);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);
        $this->assertFalse($notempty);

        $comment = new comment();

        $comment->rawtext = 'Comment text';
        $comment->width = 100;
        $comment->x = 100;
        $comment->y = 100;
        $comment->colour = 'red';

        page_editor::set_comments($grade->id, 0, array($comment));

        $annotations = array();

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'line';
        $annotation->colour = 'red';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'rectangle';
        $annotation->colour = 'yellow';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 200;
        $annotation->type = 'oval';
        $annotation->colour = 'green';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 200;
        $annotation->endy = 116;
        $annotation->type = 'highlight';
        $annotation->colour = 'blue';
        array_push($annotations, $annotation);

        $annotation = new annotation();
        $annotation->path = '100,100:105,105:110,100';
        $annotation->x = 100;
        $annotation->y = 100;
        $annotation->endx = 110;
        $annotation->endy = 105;
        $annotation->type = 'pen';
        $annotation->colour = 'black';
        array_push($annotations, $annotation);
        page_editor::set_annotations($grade->id, 0, $annotations);

        page_editor::release_drafts($grade->id);

        $notempty = page_editor::has_annotations_or_comments($grade->id, false);

        $this->assertTrue($notempty);

        $file = document_services::generate_feedback_document($setask->get_instance()->id, $grade->userid, $grade->attemptnumber);
        $this->assertNotEmpty($file);

        $file2 = document_services::get_feedback_document($setask->get_instance()->id, $grade->userid, $grade->attemptnumber);

        $this->assertEquals($file, $file2);

        document_services::delete_feedback_document($setask->get_instance()->id, $grade->userid, $grade->attemptnumber);
        $file3 = document_services::get_feedback_document($setask->get_instance()->id, $grade->userid, $grade->attemptnumber);

        $this->assertEmpty($file3);
    }
}
