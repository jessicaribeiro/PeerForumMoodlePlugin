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
 * This page receives non-ajax peergrade submissions
 *
 * It is similar to rate_ajax.php. Unlike rate_ajax.php a return url is required.
 *
 * @package    core_peergrade
 * @category   peergrade
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');


$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/block_student.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);


$user = $_GET['user'];
$status = $_GET['status'];

global $DB;

$id_db = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user));

if(!empty($id_db)){
    $id = $id_db->id;

    $data = new stdClass();
    $data->id = $id;
    $data->userblocked = !$status;

    $DB->update_record('peerforum_peergrade_users', $data);
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
