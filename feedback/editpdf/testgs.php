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
 * Test that ghostscript is configured correctly
 *
 * @package   setaskfeedback_editpdf
 * @copyright 2013 Davo Smith
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');

global $PAGE, $OUTPUT;

$PAGE->set_url(new moodle_url('/mod/setask/feedback/editpdf/testgs.php'));
$PAGE->set_context(context_system::instance());

require_login();
require_capability('moodle/site:config', context_system::instance());

$strheading = get_string('testgs', 'setaskfeedback_editpdf');
$PAGE->navbar->add(get_string('administrationsite'));
$PAGE->navbar->add(get_string('plugins', 'admin'));
$PAGE->navbar->add(get_string('setaskmentplugins', 'mod_setask'));
$PAGE->navbar->add(get_string('feedbackplugins', 'mod_setask'));
$PAGE->navbar->add(get_string('pluginname', 'setaskfeedback_editpdf'), new moodle_url('/admin/settings.php?section=setaskfeedback_editpdf'));
$PAGE->navbar->add($strheading);
$PAGE->set_heading($strheading);
$PAGE->set_title($strheading);

if (optional_param('sendimage', false, PARAM_BOOL)) {
    // Serve the generated test image.
    setaskfeedback_editpdf\pdf::send_test_image();
    die();
}

$result = setaskfeedback_editpdf\pdf::test_gs_path();

switch ($result->status) {
    case setaskfeedback_editpdf\pdf::GSPATH_OK:
        $msg = get_string('test_ok', 'setaskfeedback_editpdf');
        $msg .= html_writer::empty_tag('br');
        $imgurl = new moodle_url($PAGE->url, array('sendimage' => 1));
        $msg .= html_writer::empty_tag('img', array('src' => $imgurl, 'alt' => get_string('gsimage', 'setaskfeedback_editpdf')));
        break;

    case setaskfeedback_editpdf\pdf::GSPATH_ERROR:
        $msg = $result->message;
        break;

    default:
        $msg = get_string("test_{$result->status}", 'setaskfeedback_editpdf');
        break;
}

$returl = new moodle_url('/admin/settings.php', array('section' => 'setaskfeedback_editpdf'));
$msg .= $OUTPUT->continue_button($returl);

echo $OUTPUT->header();
echo $OUTPUT->box($msg, 'generalbox ');
echo $OUTPUT->footer();
