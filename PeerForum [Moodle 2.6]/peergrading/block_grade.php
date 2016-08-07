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

$PAGE->set_url('/peergrading/block_grade.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

$grader = $_GET['grader'];
$postid = $_GET['postid'];
$status = $_GET['status'];

global $DB;

$data = new stdClass();
//verify if grade is already blocked
if($status == 1){ // grade is blocked -> unblock (delete from DB->blockedgrades)

    $peergrade_blocked = $DB->get_record('peerforum_blockedgrades', array('itemid' => $postid, 'userid' => $grader));

    $data->contextid = $peergrade_blocked->contextid;
    $data->component =  $peergrade_blocked->component;
    $data->peergradearea = $peergrade_blocked->peergradearea;
    $data->peergrade = $peergrade_blocked->peergrade;
    $data->peergradescaleid = $peergrade_blocked->peergradescaleid;
    $data->userid = $peergrade_blocked->userid;
    $data->itemid = $peergrade_blocked->itemid;
    $data->feedback = $peergrade_blocked->feedback;
    $data->scaleid = $peergrade_blocked->scaleid;
    $data->timecreated = $peergrade_blocked->timecreated;
    $data->timemodified = $peergrade_blocked->timemodified;
    $data->peergraderid = $peergrade_blocked->peergraderid;

    $DB->insert_record('peerforum_peergrade', $data);

    $DB->delete_records('peerforum_blockedgrades', array('itemid' => $postid, 'userid' => $grader));

}
if($status == 0){ // grade is not blocked -> block (insert in DB->blockedgrades)
    $peergrade_given = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $grader));

    $data->contextid = $peergrade_given->contextid;
    $data->component =  $peergrade_given->component;
    $data->peergradearea = $peergrade_given->peergradearea;
    $data->itemid = $peergrade_given->itemid;
    $data->scaleid = $peergrade_given->scaleid;
    $data->peergrade = $peergrade_given->peergrade;
    $data->userid = $peergrade_given->userid;
    $data->timecreated = $peergrade_given->timecreated;
    $data->timemodified = $peergrade_given->timemodified;
    $data->peergradescaleid = $peergrade_given->peergradescaleid;
    $data->peergraderid = $peergrade_given->peergraderid;
    $data->feedback = $peergrade_given->feedback;
    $data->isoutlier = 0;


    $DB->insert_record('peerforum_blockedgrades', $data);

    $DB->delete_records('peerforum_peergrade', array('id' => $peergrade_given->id));
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
