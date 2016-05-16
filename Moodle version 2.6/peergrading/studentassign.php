<?php

require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
$itemid = required_param('itemid', PARAM_INT);
$postauthor = required_param('postauthor', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/studentassign.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'itemid' => $itemid, 'postauthor' => $postauthor));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){

    //Select student to assign post to peergrade
    if(isset($_POST["assignstd".$itemid])) {

        $student_id = $_POST['menustds'.$itemid];

        global $DB;

        if($student_id != UNSET_STUDENT){
            $student_id = str_replace(".","",$student_id);
        }

        if($student_id == UNSET_STUDENT){

            $all_students = get_students_can_be_assigned($courseid, $itemid, $postauthor);

            //$first_key = key($all_students);
            //$size = count($all_students)-1;
            //$last_key = key(array_slice($all_students, -1, 1, TRUE));

            //$random = rand($first_key, $last_key);

            $id = array_rand($all_students, 1);
            //shuffle($numbers);
            //$random = array_slice($numbers, 0, 1);

            $student_id = $id;
        }

            if($student_id != 0){

                $peergraders = $DB->get_record('peerforum_posts', array('id' => $itemid))->peergraders;
                $peers = explode(';', $peergraders);

                //if(!empty($peergraders)){
                    $peers = array_filter($peers);
                    array_push($peers, $student_id);
                    $peers = array_filter($peers);
            //    }
                $peers_updated = implode(';', $peers);

                $data = new stdClass();
                $data->id = $itemid;
                $data->peergraders = $peers_updated;

                $DB->update_record("peerforum_posts", $data);

                $peers_info = $DB->get_record('peerforum_peergrade_users', array('courseid'=> $courseid, 'iduser' => $student_id));

                if(!empty($peers_info)){
                    $poststograde = $peers_info->poststopeergrade;

                    $posts = explode(';', $poststograde);

                    $posts = array_filter($posts);
                    array_push($posts, $itemid);
                    $posts = array_filter($posts);

                    $posts_updated = array();
                    $posts_updated = implode(';', $posts);


                    $data2 = new stdClass();
                    $data2->id = $peers_info->id;
                    $data2->poststopeergrade = $posts_updated;

                    $DB->update_record("peerforum_peergrade_users", $data2);

                } else{
                    $data2 = new stdClass();
                    $data2->courseid = $courseid;
                    $data2->iduser = $student_id;
                    $data2->poststopeergrade = $itemid;

                    $DB->insert_record("peerforum_peergrade_users", $data2);
                }
            }
    }
/*} else {
    print_error('sectionpermissiondenied', 'peergrade');
}*/

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
?>
