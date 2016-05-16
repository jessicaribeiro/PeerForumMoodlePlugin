<?php

if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}


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

        $time = get_time_expire($old_post);

        return $time->days;

    } else {
        return null;
    }

 }
