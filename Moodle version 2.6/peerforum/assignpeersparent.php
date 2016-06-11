<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');


$PAGE->set_url('/mod/peerforum/assignpeersparent.php', array());

require_login(null, false, null, false, true);
//require_sesskey();

$itemid = required_param('itemid', PARAM_INT);
//$peerid = required_param('peerid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$postauthor = required_param('postauthor', PARAM_INT);


adjust_database();

$postparent_id = $DB->get_record('peerforum_posts', array('id' => $itemid))->parent;
$postparent = $DB->get_record('peerforum_posts', array('id' => $postparent_id));


if(!empty($postparent_id)){

    for($i=0; $i <= 10; $i++){
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

    $data = new stdClass();
    $data->id = $itemid;
    $data->peergraders = $parentpeergraders;

    $DB->update_record("peerforum_posts", $data);


    $peers_parent = explode(';', $parentpeergraders);
    $peers = array_filter($peers_parent);

    foreach ($peers as $i => $value) {

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $peers[$i]));

        $id_peer = $peers_info->iduser;

        if(!empty($peers_info)){
            $poststograde = $peers_info->poststopeergrade;

            $posts = explode(';', $poststograde);

            $posts = array_filter($posts);
            array_push($posts, $itemid);
            $posts = array_filter($posts);

            $posts_updated = array();
            $posts_updated = implode(';', $posts);

            $numpostsassigned = $peers_info->numpostsassigned;

            $numposts = $numpostsassigned + 1;

            $data2 = new stdClass();
            $data2->id = $peers_info->id;
            $data2->poststopeergrade = $posts_updated;
            $data2->numpostsassigned = $numposts;

            $DB->update_record("peerforum_peergrade_users", $data2);

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
    $last_key = max(array_keys($peers));
    $peernames = array();
    $peerids = null;
    foreach ($peers as $key => $value) {
        $graded = $DB->get_record('peerforum_peergrade', array('itemid' => $itemid, 'userid' => $peers[$key]));
        if(!empty($graded)){
            $color = '#339966';
        } else {
            $color = '#cc3300';
        }

        $peer_name = get_student_name($peers[$key]);

        if($key != $last_key){

            array_push($peernames, html_writer::tag('span', $peer_name, array('id' => 'peersassigned'.$itemid, 'style'=> 'color:'.$color.';')));
            array_push($peernames, html_writer::tag('span',  '; ', array('style'=> 'color:grey;')));

        } else {
            array_push($peernames, html_writer::tag('span', $peer_name, array('style'=> 'color:'.$color.';')));
        }

    }
    $peersnames = null;
    foreach ($peernames as $y => $value) {
        $peersnames .= $peernames[$y];
    }


        $students_assigned = get_students_can_be_assigned($courseid, $itemid, $postauthor);

        $students_assign = array();
        foreach ($students_assigned as $key => $value) {
            $id = $students_assigned[$key]->id;
            $students_assign[$id] = $id;
        }

        $students = get_students_name($students_assign);

        $selectstudentrandom = get_string('selectstudentrandom', 'peerforum');
        $assignstudentstr = get_string('assignstudentstr', 'peerforum');

        $studentsarray = array(UNSET_STUDENT_SELECT => $assignstudentstr, UNSET_STUDENT => $selectstudentrandom) + $students;

        $studentattrs = array('class'=>'menuassignpeer studentinput','id'=>'menuassignpeer'.$itemid);
        $students_html = html_writer::select($studentsarray, 'menuassignpeer'.$itemid, $studentsarray[UNSET_STUDENT_SELECT], false, $studentattrs);

        $students_assigned_rmv = get_students_assigned($courseid, $itemid);
        $students_rmv = get_students_name($students_assigned_rmv);

        $selectstudentrandom_rmv = get_string('removestudent', 'peerforum');
        $studentsarray_rmv = array(UNSET_STUDENT => $selectstudentrandom_rmv) + $students_rmv;

        $studentattrs_rmv = array('class'=>'menuremovepeer studentinput','id'=>'menuremovepeer'.$itemid);
        $students_rmv_html = html_writer::select($studentsarray_rmv, 'menuremovepeer'.$itemid, $studentsarray_rmv[UNSET_STUDENT], false, $studentattrs_rmv);


}

$result = true;

echo json_encode(array('result' => $result, 'itemid' => $itemid, 'peers' => $peers, 'peersnames' => $peersnames,  'canassign' => $students_html, 'canremove' => $students_rmv_html));
