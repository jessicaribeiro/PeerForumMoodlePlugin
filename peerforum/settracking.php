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
 * Set tracking option for the peerforum.
 *
 * @package   mod_peerforum
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The peerforum to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $peerforum = $DB->get_record("peerforum", array("id" => $id))) {
    print_error('invalidpeerforumid', 'peerforum');
}

if (! $course = $DB->get_record("course", array("id" => $peerforum->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("peerforum", $peerforum->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/peerforum/' . $returnpage, array('id' => $course->id, 'f' => $peerforum->id));
$returnto = peerforum_go_back_to($returnpageurl);

if (!peerforum_tp_can_track_peerforums($peerforum)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->peerforum = format_string($peerforum->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('peerforumid' => $peerforum->id),
);

if (peerforum_tp_is_tracked($peerforum) ) {
    if (peerforum_tp_stop_tracking($peerforum->id)) {
        $event = \mod_peerforum\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "peerforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (peerforum_tp_start_tracking($peerforum->id)) {
        $event = \mod_peerforum\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "peerforum", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}