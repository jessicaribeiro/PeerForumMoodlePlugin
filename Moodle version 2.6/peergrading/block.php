<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This page receives non-ajax peergrade submissions
 *
 * It is similar to rate_ajax.php. Unlike rate_ajax.php a return url is required.
 *
 * @package    core_peergrade
 * @category   peergrade
 * @copyright  2010 Andrew Davis
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
//$peerforumid = required_param('peerforum', PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$user = required_param('user', PARAM_INT);
$status = required_param('status', PARAM_INT);


$PAGE->set_url('/peergrading/block.php',  array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'postid' => $postid, 'user' => $user, 'status' => $status));

require_login($courseid);

//if(has_capability('mod/peerforum:viewpanelpeergrades', $context){

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
        //$data->poststopeergrade = $posts_updated;
            if($status == 0){
                $data->postspeergradedone = $posts_donepeergrade;
            }
            $data->postsblocked = $posts_blocked;
            $data->poststopeergrade = $posts_topeergrade;

        $DB->update_record("peerforum_peergrade_users", $data);

    }
//} else {
//    print_error('sectionpermissiondenied', 'peergrade');
//}

$returnurl = new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

redirect($returnurl);
