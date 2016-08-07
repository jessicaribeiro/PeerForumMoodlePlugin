<?php

/**
 * @package    block
 * @subpackage peerblock
 * @copyright  2016 Jessica Ribeiro
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

/**
* Return the number of posts a user has to grade in a course
*
* @global object
* @param int $userid
* @param int $courseid
* @return int number of posts to grade.
*/
function get_num_posts_to_grade($userid, $courseid){
    global $DB;

    //get all the posts to peergrade
    $sql = "SELECT p.iduser, p.poststopeergrade
            FROM {peerforum_peergrade_users} p
            WHERE p.iduser = $userid AND p.courseid = $courseid";

    $all_posts = $DB->get_records_sql($sql);

    $posts_to_peergrade = array();

    if(!empty($all_posts[$userid]->poststopeergrade)){
        $posts_to_peergrade = explode(";", ($all_posts[$userid]->poststopeergrade));
        $posts_to_peergrade = array_filter($posts_to_peergrade);
    }

    $num_to_grade = count($posts_to_peergrade);

    return $num_to_grade;
}
/**
* Return the time of the oldest post in a course
*
* @global object
* @param int $userid
* @param int $courseid
* @return DateTime time of the oldest post.
*/
function get_time_old_post($userid, $courseid){
    global $DB;

    //get all the posts to peergrade
    $sql = "SELECT p.iduser, p.poststopeergrade
            FROM {peerforum_peergrade_users} p
            WHERE p.iduser = $userid AND p.courseid = $courseid";

    $all_posts = $DB->get_records_sql($sql);

    $posts = array();
    if(!empty($all_posts[$userid]->poststopeergrade)){
        $posts = explode(";", ($all_posts[$userid]->poststopeergrade));
        $posts = array_filter($posts);
        $first_key = key($posts);
        $old_post = $posts[$first_key];

        $time = get_time_expire($old_post, $userid);

        return $time;
    } else {
        return null;
    }
}
