<?php

/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

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

$PAGE->set_url('/peergrading/assign.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

require_login($courseid);

$user = $_GET['user'];
$course = $_GET['courseid'];
$postid = $_POST['assignpost'];

//see if post exists
$postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));

if(!empty($postinfo)){
    //verify if conflit exists between user and post author
    $conflit = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));
    $postauthor = $postinfo->userid;

    if($postinfo->userid == $user){
        $erro = $OUTPUT->notification("You cannot assign posts whose author is the student itself.", 'notifyproblem');
        $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        redirect($returnurl, $erro, 10);
    }

    foreach ($conflit as $key => $value) {
            $conflit_stds = explode(';', $conflit[$key]->idstudents);
            $conflit_stds = array_filter($conflit_stds);

            $user_db = $DB->get_record('user', array('id' => $user));
            $user_name = $user_db->firstname .' '. $user_db->lastname;
            $author_db = $DB->get_record('user', array('id' => $postinfo->userid));
            $author_name = $author_db->firstname .' '. $author_db->lastname;

            if($postinfo->userid == $user){
                $erro = $OUTPUT->notification("You cannot assign posts whose author is the student itself.", 'notifyproblem');
                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }

            if(in_array($postauthor, $conflit_stds) && in_array($user, $conflit_stds)){
                $erro = $OUTPUT->notification("There's a conflit between post author $author_name and  $user_name. Post cannot be assigned", 'notifyproblem');
                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }
        }

        //verify if post was not already assigned to this user
        $postsuser = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user));
        if(!empty($postsuser)){
            $topeergrade = array();
            $topeergrade = explode(';', $postsuser->poststopeergrade);
            $topeergrade = array_filter($topeergrade);

            adjust_database();

            if(!in_array($postid, $topeergrade)){
                array_push($topeergrade, $postid);
                $topeergrade = array_filter($topeergrade);
                $posts_topeergrade = implode(';', $topeergrade);

                $numpostsassigned = $postsuser->numpostsassigned;
                $numposts = $numpostsassigned + 1;

                // update in the database
                $data = new stdClass();
                $data->id = $postsuser->id;
                $data->poststopeergrade = $posts_topeergrade;
                $data->numpostsassigned = $numposts;

                $DB->update_record("peerforum_peergrade_users", $data);

                $time = new stdclass();
                $time->courseid = $courseid;
                $time->postid = $postid;
                $time->userid = $user;
                $time->timeassigned = time();
                $time->timemodified = time();

                $DB->insert_record("peerforum_time_assigned", $time);

                $peergraders = $DB->get_record('peerforum_posts', array('id' => $postid))->peergraders;
                $peers = explode(';', $peergraders);
                $peers = array_filter($peers);

                if(!in_array($user, $peers)){
                    array_push($peers, $user);
                    $peers = array_filter($peers);

                    $peers_updated = implode(';', $peers);

                    $data_2 = new stdClass();
                    $data_2->id = $postid;
                    $data_2->peergraders = $peers_updated;

                    $DB->update_record("peerforum_posts", $data_2);
                }

            } else {
                //already assigned to peergrade
                $user_db = $DB->get_record('user', array('id' => $user));
                $user_name = $user_db->firstname .' '. $user_db->lastname;
                $erro = $OUTPUT->notification("Post id [$postid] already assigned to user $user_name to peergrade.", 'notifyproblem');
                $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                redirect($returnurl, $erro, 10);
            }
        }
} else {
    //post does not exist
    $erro = $OUTPUT->notification("Post [$postid] does not exist.", 'notifyproblem');
    $returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
    redirect($returnurl, $erro, 10);

}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
