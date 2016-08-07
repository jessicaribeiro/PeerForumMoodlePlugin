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

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$user = required_param('user', PARAM_INT);
$status = required_param('status', PARAM_INT);

$PAGE->set_url('/peergrading/block.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'postid' => $postid, 'user' => $user, 'status' => $status));

require_login($courseid);

// insert the post in DB in the field 'postsblocked' in the table 'peergrade_users'
$sql = "SELECT p.iduser, p.postsblocked, p.poststopeergrade, p.postspeergradedone, p.id
          FROM {peerforum_peergrade_users} p
          WHERE p.iduser = $user AND p.courseid = $courseid";

$posts = $DB->get_records_sql($sql);

//the post is assigned to the student but i want to block him to give a grade to this post
if(!empty($posts)){
    //to block a post
    if($status == 0){ //post is unblocked
        //insert the post to postsblocked
        $blocked = array();
        $blocked = explode(';', $posts[$user]->postsblocked);
        $blocked = array_filter($blocked);

        array_push($blocked, $postid);
        $blocked = array_filter($blocked);
        $posts_blocked = implode(';', $blocked);

        //remove post from poststopeergrade
        $topeergrade = array();
        $topeergrade = explode(';', $posts[$user]->poststopeergrade);
        $topeergrade = array_filter($topeergrade);

        if(!empty($topeergrade)){
            if(in_array($postid, $topeergrade)){
                $key = array_search($postid, $topeergrade);
                unset($topeergrade[$key]);
                $topeergrade = array_filter($topeergrade);
            }
        }
        $posts_topeergrade = implode(';', $topeergrade);

        //verify if post was already peergraded->delete grade given and remove from posts peergradedone
        $donepeergrade = array();
        $donepeergrade = explode(';', $posts[$user]->postspeergradedone);
        $donepeergrade = array_filter($donepeergrade);

        if(!empty($donepeergrade)){
            if(in_array($postid, $donepeergrade)){
                $key = array_search($postid, $donepeergrade);
                unset($donepeergrade[$key]);
                $donepeergrade = array_filter($donepeergrade);

                $DB->delete_records('peerforum_peergrade', array('itemid' => $postid, 'userid' => $user));
            }
        }
        $posts_donepeergrade = implode(';', $donepeergrade);
    }

    if($status == 1){ //post is blocked
        //unblock the post

        //eliminate the post from postsblocked
        $blocked = array();
        $blocked = explode(';', $posts[$user]->postsblocked);
        $blocked = array_filter($blocked);

        adjust_database();

        if(in_array($postid, $blocked)){
            $key = array_search($postid, $blocked);
            unset($blocked[$key]);
            $blocked = array_filter($blocked);
        }
        $blocked = array_filter($blocked);

        $posts_blocked = implode(';', $blocked);

        //insert post to peergrade
        $topeergrade = array();
        $topeergrade = explode(';', $posts[$user]->poststopeergrade);
        $topeergrade = array_filter($topeergrade);

        array_push($topeergrade, $postid);
        $topeergrade = array_filter($topeergrade);
        $posts_topeergrade = implode(';', $topeergrade);
    }

    // update in the database
    $data = new stdClass();
    $data->id = $posts[$user]->id;
        if($status == 0){
            $data->postspeergradedone = $posts_donepeergrade;
        }
        $data->postsblocked = $posts_blocked;
        $data->poststopeergrade = $posts_topeergrade;

    $DB->update_record("peerforum_peergrade_users", $data);
}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
