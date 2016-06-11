<?php
define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/peerforum/lib.php');


$PAGE->set_url('/mod/peerforum/assignpeer.php', array());

require_login(null, false, null, false, true);
//require_sesskey();

$itemid = required_param('itemid', PARAM_INT);
$peerid = required_param('peerid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$postauthor = required_param('postauthor', PARAM_INT);


    if($peerid == UNSET_STUDENT || $peerid == UNSET_STUDENT_SELECT){

        $all_students = get_students_can_be_assigned($courseid, $itemid, $postauthor);

        $id = array_rand($all_students, '1');

        $peerid = $id;

        $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
        $peers = explode(';', $peergraders);

        $peers = array_filter($peers);
        array_push($peers, $peerid);
        $peers = array_filter($peers);

        $peers_updated = implode(';', $peers);

        $data = new stdClass();
        $data->id = $itemid;
        $data->peergraders = $peers_updated;

        $DB->update_record("peerforum_posts", $data);

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $peerid));

        if(!empty($peers_info)){
            $poststograde = $peers_info->poststopeergrade;

            $posts = explode(';', $poststograde);

            adjust_database();

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

            $time = new stdclass();
            $time->courseid = $courseid;
            $time->postid = $itemid;
            $time->userid = $peers_info->iduser;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);

        } else {
            $data2 = new stdClass();
            $data2->courseid = $courseid;
            $data2->iduser = $peerid;
            $data2->poststopeergrade = $itemid;
            $data2->postspeergradedone = -1;
            $data2->postsblocked = -1;
            $data2->postsexpired = -1;
            $data2->numpostsassigned = 1;

            $DB->insert_record("peerforum_peergrade_users", $data2);

            $time = new stdclass();
            $time->courseid = $courseid;
            $time->postid = $itemid;
            $time->userid = $peerid;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);
        }
    }

    else if($peerid != UNSET_STUDENT || $peerid != UNSET_STUDENT_SELECT) {

        $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
        $peers = explode(';', $peergraders);

        $peers = array_filter($peers);
        array_push($peers, $peerid);
        $peers = array_filter($peers);

        $peers_updated = implode(';', $peers);

        $data = new stdClass();
        $data->id = $itemid;
        $data->peergraders = $peers_updated;

        $DB->update_record("peerforum_posts", $data);

        $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $peerid));

        if(!empty($peers_info)){
            $poststograde = $peers_info->poststopeergrade;

            $posts = explode(';', $poststograde);

            adjust_database();

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

            $time = new stdclass();
            $time->courseid = $courseid;
            $time->postid = $itemid;
            $time->userid = $peers_info->iduser;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);

        } else {
            $data2 = new stdClass();
            $data2->courseid = $courseid;
            $data2->iduser = $peerid;
            $data2->poststopeergrade = $itemid;
            $data2->postspeergradedone = -1;
            $data2->postsblocked = -1;
            $data2->postsexpired = -1;
            $data2->numpostsassigned = 1;


            $DB->insert_record("peerforum_peergrade_users", $data2);

            $time = new stdclass();
            $time->courseid = $courseid;
            $time->postid = $itemid;
            $time->userid = $peerid;
            $time->timeassigned = time();
            $time->timemodified = time();

            $DB->insert_record("peerforum_time_assigned", $time);
        }
    }


    $peers_topeergrade = get_post_peergraders($itemid);

    $peers_assigned = array();
    $last_key = max(array_keys($peers_topeergrade));
    $peernames = array();
    $peerids = null;
    foreach ($peers_topeergrade as $key => $value) {
        $graded = $DB->get_record('peerforum_peergrade', array('itemid' => $itemid, 'userid' => $peers_topeergrade[$key]));
        if(!empty($graded)){
            $color = '#339966';
        } else {
            $color = '#cc3300';
        }

        $peer_name = get_student_name($peers_topeergrade[$key]);

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

    //if(!empty($students_assign)){
        $studentattrs = array('class'=>'menuassignpeer studentinput','id'=>'menuassignpeer'.$itemid);
        $students_html = html_writer::select($studentsarray, 'menuassignpeer'.$itemid, $studentsarray[UNSET_STUDENT_SELECT], false, $studentattrs);
    //} else {
    //    $studentattrs = array('class'=>'poststudentmenu studentinput','id'=>'menustudents'.$itemid, 'disabled' => true);
    //    $students_html = html_writer::select($studentsarray, 'menuassignpeer'.$itemid, $studentsarray[UNSET_STUDENT_SELECT], false, $studentattrs);
    //}

    $students_assigned_rmv = get_students_assigned($courseid, $itemid);
    $students_rmv = get_students_name($students_assigned_rmv);

    $selectstudentrandom_rmv = get_string('removestudent', 'peerforum');
    $studentsarray_rmv = array(UNSET_STUDENT => $selectstudentrandom_rmv) + $students_rmv;

    $studentattrs_rmv = array('class'=>'menuremovepeer studentinput','id'=>'menuremovepeer'.$itemid);
    $students_rmv_html = html_writer::select($studentsarray_rmv, 'menuremovepeer'.$itemid, $studentsarray_rmv[UNSET_STUDENT], false, $studentattrs_rmv);


$result = true;

echo json_encode(array('result' => $result, 'peerid' => $peerid, 'itemid' => $itemid, 'peersnames' => $peersnames, 'courseid' => $courseid, 'canassign' => $students_html, 'canremove' => $students_rmv_html));
