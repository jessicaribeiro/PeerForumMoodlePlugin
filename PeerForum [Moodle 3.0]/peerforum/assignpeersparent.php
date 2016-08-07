<?php

/**
 * This page receives ajax peergrade submissions
 *
 * @package    mod
 * @subpackage peerforum
 * @copyright  2016 Jessica Ribeiro
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');

$PAGE->set_url('/mod/peerforum/assignpeersparent.php', array());

require_login(null, false, null, false, true);

$itemid = required_param('itemid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$postauthor = required_param('postauthor', PARAM_INT);

adjust_database();

$postparent_id = $DB->get_record('peerforum_posts', array('id' => $itemid))->parent;
$postparent = $DB->get_record('peerforum_posts', array('id' => $postparent_id));

if(!empty($postparent_id)){

    for($i=0; $i <= 10; $i++){
        if($postparent == null){
            break;
        }
        if(($postparent->userid != $postauthor)){
            $postparent_id = $DB->get_record('peerforum_posts', array('id' => $postparent->id))->parent;
            $postparent = $DB->get_record('peerforum_posts', array('id' => $postparent_id));
            continue;
        } else if ($postparent->userid == $postauthor){
            if($postparent->peergraders == 0){
                $postparent_id = $DB->get_record('peerforum_posts', array('id' => $postparent->id))->parent;
                $postparent = $DB->get_record('peerforum_posts', array('id' => $postparent_id));
                continue;
            } else if ($postparent->peergraders != 0){
                break;
            }
        }
    }
}

if(!empty($postparent)){
    $parentpeergraders = $DB->get_record('peerforum_posts', array('id' => $postparent->id))->peergraders;

    $peers_parent = explode(';', $parentpeergraders);
    $peers = array_filter($peers_parent);

    foreach ($peers as $i => $value) {

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $peers[$i]));

        $id_peer = $peers_info->iduser;

        update_peergrader_of_peerforum_posts($itemid, $id_peer);
        update_more_peerforum_users_assigned($itemid, $id_peer);

        if(!empty($peers_info)){
            update_peergrader_of_peergrade_users($peers_info, $itemid);

            $time_db = new stdclass();
            $time_db->courseid = $courseid;
            $time_db->postid = $itemid;
            $time_db->userid = $id_peer;
            $time_db->timeassigned = time();
            $time_db->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time_db);
        }
    }

    $peers_assigned = array();
    $peernames = array();
    $peerids = null;
    $graded = $DB->get_records('peerforum_peergrade', array('itemid' => $itemid));

    foreach ($peers as $key => $value) {

        if(in_array($peers[$key], $graded->userid)){
            $color = '#339966';
        } else {
            $color = '#cc3300';
        }

        $peer_name = get_student_name($peers[$key]);

        array_push($peernames, html_writer::tag('span', $peer_name, array('id' => 'peersassigned'.$itemid, 'style'=> 'color:'.$color.';')));
        array_push($peernames, html_writer::tag('span',  '; ', array('style'=> 'color:grey;')));
    }
    $peersnames = null;
    foreach ($peernames as $y => $value) {
        $peersnames .= $peernames[$y];
    }

        $students_assigned = get_students_can_be_assigned_id($itemid);

        $students_assign = $students_assigned;

        $students = get_students_name($students_assign);

        $selectstudentrandom = get_string('selectstudentrandom', 'peerforum');
        $assignstudentstr = get_string('assignstudentstr', 'peerforum');

        $studentsarray = array(UNSET_STUDENT_SELECT => $assignstudentstr, UNSET_STUDENT => $selectstudentrandom) + $students;

        $studentattrs = array('class'=>'menuassignpeer studentinput','id'=>'menuassignpeer'.$itemid);
        $students_html = html_writer::select($studentsarray, 'menuassignpeer'.$itemid, $studentsarray[UNSET_STUDENT_SELECT], false, $studentattrs);

        $students_assigned_rmv = get_students_assigned($courseid, $itemid);
        $students_rmv = get_students_name($students_assigned_rmv);

        $removestudentstr = get_string('removestudent', 'peerforum');
        $studentsarray_rmv = array(UNSET_STUDENT_SELECT => $removestudentstr, UNSET_STUDENT => $selectstudentrandom) + $students_rmv;

        $studentattrs_rmv = array('class'=>'menuremovepeer studentinput','id'=>'menuremovepeer'.$itemid);
        $students_rmv_html = html_writer::select($studentsarray_rmv, 'menuremovepeer'.$itemid, $studentsarray_rmv[UNSET_STUDENT_SELECT], false, $studentattrs_rmv);
}

$result = true;

echo json_encode(array('result' => $result, 'itemid' => $itemid, 'peers' => $peers, 'peersnames' => $peersnames,  'canassign' => $students_html, 'canremove' => $students_rmv_html));
