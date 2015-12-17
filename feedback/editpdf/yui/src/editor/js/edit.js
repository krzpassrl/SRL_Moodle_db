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
 * Provides an in browser PDF editor.
 *
 * @module moodle-setaskfeedback_editpdf-editor
 */

/**
 * EDIT
 *
 * @namespace M.setaskfeedback_editpdf
 * @class edit
 */
var EDIT = function() {

    /**
     * Starting point for the edit.
     * @property start
     * @type M.setaskfeedback_editpdf.point|false
     * @public
     */
    this.start = false;

    /**
     * Finishing point for the edit.
     * @property end
     * @type M.setaskfeedback_editpdf.point|false
     * @public
     */
    this.end = false;

    /**
     * Starting time for the edit.
     * @property starttime
     * @type int
     * @public
     */
    this.starttime = 0;

    /**
     * Starting point for the currently selected annotation.
     * @property annotationstart
     * @type M.setaskfeedback_editpdf.point|false
     * @public
     */
    this.annotationstart = false;

    /**
     * The currently selected tool
     * @property tool
     * @type String
     * @public
     */
    this.tool = "comment";

    /**
     * The currently comment colour
     * @property commentcolour
     * @type String
     * @public
     */
    this.commentcolour = 'yellow';

    /**
     * The currently annotation colour
     * @property annotationcolour
     * @type String
     * @public
     */
    this.annotationcolour = 'red';

    /**
     * The current stamp image.
     * @property stamp
     * @type String
     * @public
     */
    this.stamp = '';

    /**
     * List of points the the current drawing path.
     * @property path
     * @type M.setaskfeedback_editpdf.point[]
     * @public
     */
    this.path = [];
};

M.setaskfeedback_editpdf = M.setaskfeedback_editpdf || {};
M.setaskfeedback_editpdf.edit = EDIT;
