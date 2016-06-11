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
 * A page to display a list of ratingpeers for a given item (forum post etc)
 *
 * @package    core_ratingpeer
 * @category   ratingpeer
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../config.php");
require_once("lib.php");

$contextid  = required_param('contextid', PARAM_INT);
$component  = required_param('component', PARAM_COMPONENT);
$ratingpeerarea = optional_param('ratingpeerarea', null, PARAM_AREA);
$itemid     = required_param('itemid', PARAM_INT);
$scaleid    = required_param('scaleid', PARAM_INT);
$sort       = optional_param('sort', '', PARAM_ALPHA);
$popup      = optional_param('popup', 0, PARAM_INT); //==1 if in a popup window?

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);

$url = new moodle_url('/ratingpeer/index.php', array('contextid'=>$contextid,'component'=>$component,'itemid'=>$itemid,'scaleid'=>$scaleid));
if (!empty($ratingpeerarea)) {
    $url->param('ratingpeerarea', $ratingpeerarea);
}
if (!empty($sort)) {
    $url->param('sort', $sort);
}
if (!empty($popup)) {
    $url->param('popup', $popup);
}
$PAGE->set_url($url);
$PAGE->set_context($context);

if ($popup) {
    $PAGE->set_pagelayout('popup');
}

if (!has_capability('mod/peerforum:viewratingpeer',$context)) {
    print_error('noviewratepeer', 'ratingpeer');
}
if (!has_capability('mod/peerforum:viewallratingpeer',$context) and $USER->id != $item->userid) {
    print_error('noviewanyratepeer', 'ratingpeer');
}

switch ($sort) {
    case 'firstname': $sqlsort = "u.firstname ASC"; break;
    case 'ratingpeer':    $sqlsort = "r.ratingpeer ASC"; break;
    default:          $sqlsort = "r.timemodified ASC";
}

$scalemenu = make_grades_menu($scaleid);

$strratingpeer  = get_string('ratingpeer', 'ratingpeer');
$strname    = get_string('name');
$strtime    = get_string('time');

$PAGE->set_title(get_string('allratingpeersforitem','ratingpeer'));
echo $OUTPUT->header();

$ratingpeeroptions = new stdClass;
$ratingpeeroptions->context = $context;
$ratingpeeroptions->component = $component;
$ratingpeeroptions->ratingpeerarea = $ratingpeerarea;
$ratingpeeroptions->itemid = $itemid;
$ratingpeeroptions->sort = $sqlsort;

$rm = new ratingpeer_manager();
$ratingpeers = $rm->get_all_ratingpeers_for_item($ratingpeeroptions);
if (!$ratingpeers) {
    $msg = get_string('noratingpeers','ratingpeer');
    echo html_writer::tag('div', $msg, array('class'=>'mdl-align'));
} else {
    // To get the sort URL, copy the current URL and remove any previous sort
    $sorturl = new moodle_url($url);
    $sorturl->remove_params('sort');

    $table = new html_table;
    $table->cellpadding = 3;
    $table->cellspacing = 3;
    $table->attributes['class'] = 'generalbox ratingpeertable';
    $table->head = array(
        '',
        html_writer::link(new moodle_url($sorturl, array('sort' => 'firstname')), $strname),
        html_writer::link(new moodle_url($sorturl, array('sort' => 'ratingpeer')), $strratingpeer),
        html_writer::link(new moodle_url($sorturl, array('sort' => 'time')), $strtime)
    );
    $table->colclasses = array('', 'firstname', 'ratingpeer', 'time');
    $table->data = array();

    // If the scale was changed after ratingpeers were submitted some ratingpeers may have a value above the current maximum
    // We can't just do count($scalemenu) - 1 as custom scales start at index 1, not 0
    $maxratingpeer = max(array_keys($scalemenu));

    foreach ($ratingpeers as $ratingpeer) {
        //Undo the aliasing of the user id column from user_picture::fields()
        //we could clone the ratingpeer object or preserve the ratingpeer id if we needed it again
        //but we don't
        $ratingpeer->id = $ratingpeer->userid;

        $row = new html_table_row();
        $row->attributes['class'] = 'ratingpeeritemheader';
        if ($course && $course->id) {
            $row->cells[] = $OUTPUT->user_picture($ratingpeer, array('courseid' => $course->id));
        } else {
            $row->cells[] = $OUTPUT->user_picture($ratingpeer);
        }
        $row->cells[] = fullname($ratingpeer);
        if ($ratingpeer->ratingpeer > $maxratingpeer) {
            $ratingpeer->ratingpeer = $maxratingpeer;
        }
        $row->cells[] = $scalemenu[$ratingpeer->ratingpeer];
        $row->cells[] = userdate($ratingpeer->timemodified);
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}
if ($popup) {
    echo $OUTPUT->close_window_button();
}
echo $OUTPUT->footer();
