<?php
require_once('../config.php');
require_once($CFG->dirroot.'/peergrade/lib.php');

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

global $PAGE;
$context = optional_param('context', null, PARAM_INT);
$PAGE->set_context($context);

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);

$PAGE->set_url('/peergrading/remove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){

    $user = $_GET['user'];
    $course = $_GET['course'];
    $postid = $_GET['removepost'];


    //see if post exists
    $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));

    if(!empty($postinfo)){
        //verify if post is assigned to this user
        $postsuser = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user));
        if(!empty($postsuser)){
            $topeergrade = array();
            $topeergrade = explode(';', $postsuser->poststopeergrade);
            $topeergrade = array_filter($topeergrade);

            adjust_database();

            if(in_array($postid, $topeergrade)){
                $key = array_search($postid, $topeergrade);
                unset($topeergrade[$key]);
                $topeergrade = array_filter($topeergrade);
                $posts_topeergrade = implode(';', $topeergrade);

                $numpostsassigned = $postsuser->numpostsassigned;
                $numposts = $numpostsassigned - 1;

                // update in the database
                $data = new stdClass();
                $data->id = $postsuser->id;
                $data->poststopeergrade = $posts_topeergrade;
                $data->numpostsassigned = $numposts;

                $peergraders = $DB->get_record('peerforum_posts', array('id' => $postid))->peergraders;
                $peers = explode(';', $peergraders);
                $peers = array_filter($peers);

                if(in_array($user, $peers)){
                    $key = array_search($user, $peers);
                    unset($peers[$key]);
                    $peers = array_filter($peers);
                    $peers_updated = implode(';', $peers);

                    $data_2 = new stdClass();
                    $data_2->id = $postid;
                    $data_2->peergraders = $peers_updated;

                    $DB->update_record("peerforum_posts", $data_2);
                }


                $DB->update_record("peerforum_peergrade_users", $data);
                $DB->delete_records('peerforum_time_assigned', array('courseid' => $courseid, 'userid' => $user, 'postid' => $postid));

            } else {
               //post was not assigned to peergrade
               $erro = $OUTPUT->notification("Post ($postid) was not assigned to ($user) to peergrade.", 'notifyproblem');
               $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
               redirect($returnurl, $erro, 10);

           }
            //remove from blocked too
            $blocked = array();
            $blocked = explode(';', $postsuser->postsblocked);
            $blocked = array_filter($blocked);


            if(in_array($postid, $blocked)){
                $key = array_search($postid, $blocked);
                unset($blocked[$key]);
                $blocked = array_filter($blocked);
                $posts_blocked = implode(';', $blocked);

                // update in the database
                $data = new stdClass();
                $data->id = $postsuser->id;
                $data->postsblocked = $posts_blocked;

                $DB->update_record("peerforum_peergrade_users", $data);
            }
        }
    } else{
        //post does not exist
        $erro = $OUTPUT->notification("Post ($postid) does not exist.", 'notifyproblem');
        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        redirect($returnurl, $erro, 10);

    }


$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
