<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);

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
