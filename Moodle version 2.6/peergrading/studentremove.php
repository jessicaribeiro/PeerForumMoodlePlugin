<?php
require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
$postid = required_param('itemid', PARAM_INT);
$grader = required_param('grader', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/studentremove.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'grader' => $grader));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){

    if($_POST["removestudent"]){
        global $DB;
        //remove peer grader from peerforum_posts
        $post = $DB->get_record('peerforum_posts', array('id' => $postid));
        $peergraders = explode(';', $post->peergraders);
        $peergraders = array_filter($peergraders);

        if(in_array($grader, $peergraders)){
            $key = array_search($grader, $peergraders);
            unset($peergraders[$key]);
            $peergraders = array_filter($peergraders);
            $peergraders_upd = implode(';', $peergraders);

            $data = new stdClass();
            $data->id = $postid;
            $data->peergraders = $peergraders_upd;

            $DB->update_record("peerforum_posts", $data);

            $graderid = (int)$grader;

            //assign new grader to this post
            assign_one_peergrader($postid, $courseid, $graderid);
        }

        //remove post from peergrader
        $info_grader = $DB->get_record('peerforum_peergrade_users', array('courseid' => $courseid, 'iduser' => $grader));

        $to_peergrade = explode(';', $info_grader->poststopeergrade);
        $to_peergrade = array_filter($to_peergrade);
        $blocked = explode(';', $info_grader->postsblocked);
        $blocked = array_filter($blocked);

        $data2 = new stdClass();
        $data2->id = $info_grader->id;

        if(in_array($postid, $to_peergrade)){
            $key = array_search($postid, $to_peergrade);
            unset($to_peergrade[$key]);
            $to_peergrade = array_filter($to_peergrade);
            $to_peergrade_upd = implode(';', $to_peergrade);

            $data2->poststopeergrade = $to_peergrade_upd;
            $DB->update_record("peerforum_peergrade_users", $data2);

        }

        if(in_array($postid, $blocked)){
            $key = array_search($postid, $blocked);
            unset($blocked[$key]);
            $blocked = array_filter($blocked);
            $blocked_upd = implode(';', $blocked);

            $data2->postsblocked = $blocked_upd;
            $DB->update_record("peerforum_peergrade_users", $data2);

        }

        $grade = $DB->get_records('peerforum_peergrade', array('itemid' => $postid, 'userid' => $grader));

        if(!empty($grade)){
            $DB->delete_records('peerforum_peergrade', array('itemid' => $postid, 'userid' => $grader));
        }


    }
/*} else {
    print_error('sectionpermissiondenied', 'peergrade');
}*/

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
?>
