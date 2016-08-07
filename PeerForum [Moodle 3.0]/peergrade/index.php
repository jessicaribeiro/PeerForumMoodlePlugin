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
 * A page to display a list of peergrades for a given item (forum post etc)
 *
 * @package    core_peergrade
 * @category   peergrade
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
* Additional functions for PeerForum' peegrading
*
* @package    core_peergrade
* @author     2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once("../config.php");
require_once("lib.php");

$contextid  = required_param('contextid', PARAM_INT);
$component  = required_param('component', PARAM_COMPONENT);
$peergradearea = required_param('peergradearea', PARAM_AREA);
$itemid     = required_param('itemid', PARAM_INT);
$peergradescaleid    = required_param('peergradescaleid', PARAM_INT);
$sort       = optional_param('sort', '', PARAM_ALPHA);
$popup      = optional_param('popup', 0, PARAM_INT); // Any non-zero value if in a popup window.

list($context, $course, $cm) = get_context_info_array($contextid);
require_login($course, false, $cm);


$url = new moodle_url('/peergrade/index.php', array('contextid' => $contextid,
                                                 'component' => $component,
                                                 'peergradearea' => $peergradearea,
                                                 'itemid' => $itemid,
                                                 'peergradescaleid' => $peergradescaleid));




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

$canviewallpeergrades = has_capability('mod/peerforum:viewall', $context);

switch ($sort) {
    case 'firstname':
        $sqlsort = "u.firstname ASC";
        break;
    case 'peergrade':
        $sqlsort = "p.peergrade ASC";
        break;
    default:
        $sqlsort = "p.timemodified ASC";
}

$peergradescalemenu = make_grades_menu($peergradescaleid);


$strpeergrade  = get_string('peergrade', 'peerforum');
$strname    = get_string('name');
$strtime    = get_string('time');

$PAGE->set_title(get_string('allpeergradesforitem', 'peerforum'));
echo $OUTPUT->header();

$peergradeoptions = new stdClass;
$peergradeoptions->context = $context;
$peergradeoptions->component = $component;
$peergradeoptions->peergradearea = $peergradearea;
$peergradeoptions->itemid = $itemid;
$peergradeoptions->sort = $sqlsort;

$rm = new peergrade_manager();
$peergrades = $rm->get_all_peergrades_for_item($peergradeoptions);
if (!$peergrades) {
    $msg = get_string('nopeergrades', 'peerforum');
    echo html_writer::tag('div', $msg, array('class' => 'mdl-align'));
} else {
    // To get the sort URL, copy the current URL and remove any previous sort.
    $sorturl = new moodle_url($url);
    $sorturl->remove_params('sort');

    $table = new html_table;
    $table->cellpadding = 3;
    $table->cellspacing = 3;
    $table->attributes['class'] = 'generalbox peergradetable';
    $table->head = array(
        '',
        html_writer::link(new moodle_url($sorturl, array('sort' => 'firstname')), $strname),
        html_writer::link(new moodle_url($sorturl, array('sort' => 'peergrade')), $strpeergrade),
        html_writer::link(new moodle_url($sorturl, array('sort' => 'time')), $strtime)
    );
    $table->colclasses = array('', 'firstname', 'peergrade', 'time');
    $table->data = array();

    // If the scale was changed after peergrades were submitted some peergrades may have a value above the current maximum.
    // We can't just do count($scalemenu) - 1 as custom scales start at index 1, not 0.
    $maxpeergrade = max(array_keys($peergradescalemenu));


    foreach ($peergrades as $peergrade) {
        if (!$canviewallpeergrades and $USER->id != $peergrade->userid) {
            continue;
        }

        // Undo the aliasing of the user id column from user_picture::fields().
        // We could clone the peergrade object or preserve the peergrade id if we needed it again
        // but we don't.
        $peergrade->id = $peergrade->userid;

        $row = new html_table_row();
        $row->attributes['class'] = 'peergradeitemheader';
        if ($course && $course->id) {
            $row->cells[] = $OUTPUT->user_picture($peergrade, array('courseid' => $course->id));
        } else {
            $row->cells[] = $OUTPUT->user_picture($peergrade);
        }
        $row->cells[] = fullname($peergrade);
        if ($peergrade->peergrade > $maxpeergrade) {
            $peergrade->peergrade = $maxpeergrade;
        }
        $row->cells[] = $peergradescalemenu[$peergrade->peergrade];
        $row->cells[] = userdate($peergrade->timemodified);
        $table->data[] = $row;
    }
    echo html_writer::table($table);
}
if ($popup) {
    echo $OUTPUT->close_window_button();
}
echo $OUTPUT->footer();
