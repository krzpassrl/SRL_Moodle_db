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
 * Definition of log events
 *
 * @package   mod_setask
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$logs = array(
    array('module'=>'setask', 'action'=>'add', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'delete mod', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'download all submissions', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'grade submission', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'lock submission', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'reveal identities', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'revert submission to draft', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'set marking workflow state', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'submission statement accepted', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'submit', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'submit for grading', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'unlock submission', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'update', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'upload', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view all', 'mtable'=>'course', 'field'=>'fullname'),
    array('module'=>'setask', 'action'=>'view confirm submit setaskment form', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view grading form', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view submission', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view submission grading table', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view submit setaskment form', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view feedback', 'mtable'=>'setask', 'field'=>'name'),
    array('module'=>'setask', 'action'=>'view batch set marking workflow state', 'mtable'=>'setask', 'field'=>'name'),
);
