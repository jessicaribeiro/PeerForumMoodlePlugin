<?php
require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);


$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/assign.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){

    $user = $_GET['user'];
    $course = $_GET['courseid'];
    $postid = $_POST['assignpost'];

    //see if post exists
    $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));

    if(!empty($postinfo)){
        //verify if post was not already assigned to this user
        $postsuser = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user));
        if(!empty($postsuser)){
            $topeergrade = array();
            $topeergrade = explode(';', $postsuser->poststopeergrade);
            $topeergrade = array_filter($topeergrade);

            if(!in_array($postid, $topeergrade)){
                array_push($topeergrade, $postid);
                $topeergrade = array_filter($topeergrade);
                $posts_topeergrade = implode(';', $topeergrade);

                // update in the database
                $data = new stdClass();
                $data->id = $postsuser->id;
                $data->poststopeergrade = $posts_topeergrade;

                $DB->update_record("peerforum_peergrade_users", $data);

            } else {
                //already assigned to peergrade
                //echo $OUTPUT->error_text("Post ($postid) already assigned to user ($user) to peergrade.");
                $erro = $OUTPUT->notification("Post ($postid) already assigned to user ($user) to peergrade.", 'notifyproblem');
                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }
        }
    } else{
        //post does not exist
        //echo $OUTPUT->error_text("Post ($postid) does not exist.");
        $erro = $OUTPUT->notification("Post ($postid) does not exist.", 'notifyproblem');
        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        redirect($returnurl, $erro, 10);

    }

/*} else {
    print_error('sectionpermissiondenied', 'peergrade');
}*/

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
