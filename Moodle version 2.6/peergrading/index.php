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
 * Lists all the users within a given course.
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_user
 */

require_once('../config.php');
if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

require_once($CFG->libdir.'/tablelib.php');


$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
//$peerforumid = required_param('peerforum', PARAM_INT);
$peerforumid = optional_param('peerforum', null, PARAM_INT);
//$edit  = optional_param('edit', null, PARAM_INT);



//$urlparams = compact('id','userid', 'modid', 'courseid', 'display');
$urlparams = compact('userid', 'courseid', 'display', 'peerforumid');

foreach ($urlparams as $var => $val) {
    if (empty($val)) {
        unset($urlparams[$var]);
    }
}
$PAGE->set_url('/peergrading/index.php', $urlparams);


$PAGE->requires->css('/peergrading/style.css');

if (isset($userid) && empty($courseid)) {
    $context = context_user::instance($userid);
} else if (!empty($courseid) && $courseid != SITEID) {
    $context = context_course::instance($courseid);
} else {
    $context = context_system::instance();
}
$PAGE->set_context($context);

$sitecontext = context_system::instance();

// Add courseid if modid or groupid is specified: This is used for navigation and title.
if (!empty($modid) && empty($courseid)) {
    $courseid = $DB->get_field('course_modules', 'course', array('id' => $modid));
}

if(empty($userid)){
    $userid = $USER->id;
}

if (!empty($modid)) {
    if (!$mod = $DB->get_record('course_modules', array('id' => $modid))) {
        print_error(get_string('invalidmodid', 'blog'));
    }
    $courseid = $mod->course;
}

if ((empty($courseid) ? true : $courseid == SITEID) && empty($userid)) {
    $COURSE = $DB->get_record('course', array('format' => 'site'));
    $courseid = $COURSE->id;
}

if (!empty($courseid)) {
    if (!$course = $DB->get_record('course', array('id' => $courseid))) {
        print_error('invalidcourseid');
    }

    $courseid = $course->id;


} else {
    $coursecontext = context_course::instance(SITEID);
}

$contextid = context_course::instance($courseid);

$courseid = (empty($courseid)) ? SITEID : $courseid;

$usernode = $PAGE->navigation->find('user'.$userid, null);
if ($usernode && $courseid != SITEID) {
    $url = new moodle_url($PAGE->url);
}

require_login($courseid);

/// Output the page
$strpeerblock      = get_string('pluginname', 'block_peerblock');

global $DB;
$coursename = $DB->get_record('course', array('id' => $courseid))->fullname;
$url_block = new moodle_url('/course/view.php?id='.$courseid);

$pagetitle = get_string('pluginname', 'block_peerblock');

$PAGE->navbar->add($coursename, $url_block);
$PAGE->navbar->add($strpeerblock);

$PAGE->set_title(format_string($pagetitle));
$PAGE->set_pagelayout('incourse');

$PAGE->set_heading($pagetitle);
echo $OUTPUT->header();


//POSTS TO PEERGRADE
$poststopeergrade = get_string('poststopeergrade', 'block_peerblock');
$postspeergraded = get_string('postspeergraded', 'block_peerblock');
$postsexpired = get_string('postsexpired', 'block_peerblock');

$managegraders = get_string('managegraders', 'block_peerblock');
$viewpeergrades = get_string('viewpeergrades', 'block_peerblock');
$manageposts = get_string('manageposts', 'block_peerblock');
$manageconflits = get_string('manageconflits', 'block_peerblock');


echo $OUTPUT->box_start();

    $row = array();

    if(empty($display)){
        $display = '1';
    }

    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        if($display == '-1' || $display == '1'){
            $display = '-1';
            $currenttab = 'display_manageposts';

        } else if ($display == '-2' || $display == '2'){
            $display = '-2';
            $currenttab = 'display_manager_managegraders';

        } else if ($display == '3'){
            $display = '3';
            $currenttab = 'display_viewpeergrades';


        } else if ($display == '4'){
            $display = '4';
            $currenttab = 'display_manageconflits';

        }


        $row[] = new tabobject('display_manageposts',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => '-1')),
                                    $manageposts);


        $row[] = new tabobject('display_manager_managegraders',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '-2')),
                                    $managegraders);

        $row[] = new tabobject('display_viewpeergrades',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '3')),
                                    $viewpeergrades);

        $row[] = new tabobject('display_manageconflits',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '4')),
                                    $manageconflits);

    }

    if(!has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        if($display == '1' || $display == '-1' || $display == '-2' || $display == '3' || $display == '4'){
            $currenttab = 'display_to_peergrade';
        }
        if($display == '2'){
            $currenttab = 'display_peergraded';
        }
        if($display == '5'){
            $currenttab = 'display_postsexpired';
        }

        $row[] = new tabobject('display_to_peergrade',
                               new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '1')),
                               $poststopeergrade);



        $row[] = new tabobject('display_peergraded',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '2')),
                                    $postspeergraded);

        $row[] = new tabobject('display_postsexpired',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '5')),
                                    $postsexpired);
    }



    echo '<div class="groupdisplay">';
    echo $OUTPUT->tabtree($row, $currenttab);
    echo '</div>';

// Manage Posts
if($display == '-1'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        $posts = get_all_posts_info();

        if(empty($posts)){
            echo 'No posts to display.';
        }

       if(!empty($posts)){

        echo '<tr><td> This table show the posts that can be peer graded or that were already peer graded and can be edited. You can decide to block a student to give a grade to a post or disable the grade given.</td></tr>';

        echo '<table class="managepeers"">'.
          '<tr">'.
            '<td bgcolor=#cccccc><b> Subject </b></td>'.
            '<td bgcolor=#cccccc><b> Post author </b></td>'.
            '<td bgcolor=#cccccc><b> Peer grader </b></td>'.
            '<td bgcolor=#cccccc><b> Remove grader </b></td>'.
            '<td bgcolor=#cccccc><b> Time left to grade </b></td>'.
            '<td bgcolor=#cccccc><b> Gave grade? </b></td>'.
            '<td bgcolor=#cccccc><b> Status </b></td>'.
            '<td bgcolor=#cccccc><b> Block/Unblock post to grader </b></td>'.
          '</tr>';

          $even = true;

          foreach ($posts as $i => $value) {

              $postid = $posts[$i]->postid;
              $subject = $posts[$i]->subject;
              $postsubject = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$posts[$i]->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $subject);

              $count_graders = count($posts[$i]->peergraders);

             // if($count_graders > 0){
                  $count = $count_graders + 2;
                  if($even){
                      $color = '#f2f2f2';
                      $even = !$even;
                  } else {
                      $color = 	'#ffffff';
                      $even = !$even;
                  }

                  $post_author_id = $DB->get_record('peerforum_posts', array('id'=>$postid))->userid;
                  $post_author = $DB->get_record('user', array('id' => $post_author_id));
                  $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$post_author->id)), $post_author->firstname .' '. $post_author->lastname);

                  echo '<tr>'.'<td height="1" width="200" bgcolor='."$color".' rowspan='.$count.'>'. '(id:'.$postid.') '.$postsubject.'</td>'.'<td height="1" width="80" bgcolor='."$color".' rowspan='.$count.'>'. $author_link.'</td>';

                  for($k = 0; $k < $count_graders; $k++){
                      $peergraderid = $posts[$i]->peergraders[$k];
                      $status = get_post_status($postid, $peergraderid, $courseid);
                      $time = $posts[$i]->timeexpire ." day(s)";


                      $grader = $DB->get_record('user', array('id' => $peergraderid));

                      $peergrader = html_writer::link(new moodle_url('/user/view.php', array('id'=> $peergraderid)), $grader->firstname .' '. $grader->lastname);

                      $baseurl = new moodle_url('/peergrading/block.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'postid' => $postid, 'user' => $peergraderid, 'status' => $status));
                     // $baseurl = $url.'&postid='.$postid.'&user='.$peergraderid.'&status='.$status;


                      $peergradedone = verify_peergrade($postid, $peergraderid);

                      if($peergradedone == 1){
                          $done = '<font color="green">Yes</font>';
                          $disable = 'disabled';
                      } else {
                          $done = '<font color="red">No</font>';
                          $disable = '';
                      }


                      if($status == 1){
                          $button = '<form action="' . $baseurl . '" method="post">'.
                                      '<div class="buttons"><input '.$disable.' style="margin-top:10px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                      '</div></form>';
                      } else {
                          $button = '<form action="' . $baseurl . '" method="post">'.
                                      '<div class="buttons"><input '.$disable.' style="margin-top:10px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                      '</div></form>';
                      }

                      if($status == 1){
                          $status_str =  '<font color="red">Blocked</font>' ;
                      } else {
                          $status_str = '<font color="green">Unblocked</font>';
                      }

                      $src = new moodle_url('/peergrading/pix/delete.png');
                      $remove_url = new moodle_url('/peergrading/studentremove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'itemid' => $postid, 'grader' => $peergraderid, 'itemid' => $postid));

                      $remove_button = '<form action="' . $remove_url . '" method="post">'.
                                  '<div class="buttons"><input type="image" src="'.$src.'" alt="Submit" id="removestudent" name="removestudent" style="height:15px; width:15px" onClick="javascript:return confirm(\'Are you sure you want to remove peer grader ['.$grader->firstname .' '. $grader->lastname.'] from post id ['.$postid.'] ? This will eliminate the peer grade given by this student to this post.\');" value="'.get_string('removestd', 'block_peerblock').'"/>'.
                                  '</div>
                                    </form>';


                      echo '<tr>'.'<td height="1" width="200" bgcolor='."$color".'>'.$peergrader.'</td>'.'<td height="1" width="10" bgcolor='."$color".'>'.$remove_button.'</td>'.'<td height="1" width="80" bgcolor='."$color".'>'.$time.'</td>'.'<td height="1" width="50" bgcolor='."$color".'>'.$done.'</td>'.'<td height="1" width="60" bgcolor='."$color".'>'.$status_str.'</td>'.'<td height="1" width="40" bgcolor='."$color".'>'.$button.'</td>'.'</tr>';
                      echo '</tr>';
                  }

                  $students_assigned = get_students_can_be_assigned($courseid, $postid, $post_author_id);

                  $selectstudentrandom = get_string('selectstudentrandom', 'peerforum');

                  $students_options = '';

                  $url_select = new moodle_url('/peergrading/studentassign.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'itemid' => $postid, 'postauthor' => $post_author_id));

                  if(!empty($students_assigned)){
                      $students_options .= '<option value="'.UNSET_STUDENT.'.">'.format_string($selectstudentrandom).'</option>';

                      foreach ($students_assigned as $id => $value) {
                          $std_firstname = $DB->get_record('user', array('id' => $id))->firstname;
                          $std_lastname = $DB->get_record('user', array('id' => $id))->lastname;
                          $std_name = $std_firstname.' '.$std_lastname;

                          $students_options .= '<option value="'.$id.'.">'.format_string($std_name).'</option>';
                      }

                    $select_std = '<form action="' . $url_select . '" method="post">'.
                                '<select style="margin-top:10px;font-size: 13px;" class="poststudentmenu studentinput" name="menustds'.$postid.'" id="menustds"'.$postid.'">'.
                                $students_options.
                                '</select>'.
                                '<input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" name="assignstd'.$postid.'" class="assignstd" id="assignstd'.$postid.'" value="'.s(get_string('assignpeer', 'peerforum')).'" />'.
                                //'<div class="buttons"><input type="submit" id="assignstd" name="assignstd" value="'.get_string('assignpeer', 'peerforum').'"/>'.
                                '</div></form>';

                  } else {

                    $select_std = '<form action="' . $url_select . '" method="post">'.
                                '<select style="margin-left:3px;margin-top:10px;font-size: 13px;" disabled="true" class="poststudentmenu studentinput" name="menustds'.$postid.'" id="menustds"'.$postid.'">'.
                                '<option value="'.UNSET_STUDENT.'.">'.format_string($selectstudentrandom).'</option>'.
                                '</select>'.
                                '<input  style="margin-top:10px;font-size: 13px;" disabled="true" type="submit" name="assignstd'.$postid.'" class="assignstd" id="assignstd'.$postid.'" value="'.s(get_string('assignpeer', 'peerforum')).'" />'.
                                //'<div class="buttons"><input type="submit" id="assignstd" name="assignstd" value="'.get_string('assignpeer', 'peerforum').'"/>'.
                                '</div></form>';
                  }

                  echo '<tr>'.'<td height="1px" bgcolor='."$color".'>'.$select_std.'</td>'.'</tr>';


              //}
          }
          echo '</table>';
        }
    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
 }

// Manage Graders
if($display == '-2'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        $infograder = get_all_peergrades($courseid);
        //$students = get_students_enroled($courseid);

        if(empty($infograder)){
            echo 'No peer graders to display.';
        }

        if(!empty($infograder)){

            echo '<tr><td> This table show the posts that must be peer graded. It shows the post author and his group. You can decide to assign a post to a student to give a grade or remove a post.</td></tr>';

            echo '<table class="managepeers">'.
              '<tr>'.
                '<td bgcolor=#cccccc><b> Student </b></td>'.
                '<td bgcolor=#cccccc><b> Block/Unblock student </b></td>'.
                '<td bgcolor=#cccccc><b> Student group </b></td>'.
                '<td bgcolor=#cccccc><b> Posts to grade </b></td>'.
                '<td bgcolor=#cccccc><b> Post author </b></td>'.
                '<td bgcolor=#cccccc><b> Author group </b></td>'.
                '<td bgcolor=#cccccc><b> Remove assigned post </b></td>'.
              '</tr>';

            $even = true;

            foreach ($infograder as $i => $value) {

                if($even){
                    $color = '#f2f2f2';
                    $even = !$even;
                } else {
                    $color = '#ffffff';
                    $even = !$even;
                }

                $grader = $infograder[$i][0]->authorid; //meter com link

                if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context, $grader, false)){

                    $grader_db = $DB->get_record('user', array('id' => $grader));
                    $grader_group = $DB->get_records('peerforum_groups', array('courseid' => $courseid));

                    if(!empty($grader_group) && !empty($grader_db)){
                        foreach ($grader_group as $id => $value) {
                            $students = explode(';', $grader_group[$id]->studentsid);
                            $students = array_filter($students);


                            if(!empty($students)){
                                if(in_array($grader_db->id, $students)){
                                    $group_user = $grader_group[$id]->groupid;
                                }
                            }
                        }
                    }

                    if(empty($grader_group)){
                        $grader_group = $DB->get_record('groups_members', array('userid' => $grader_db->id));

                        if(!empty($grader_group)){
                            $group_user = $grader_group->groupid;
                        }
                    }

                    if(empty($group_user)){
                        $group_user = 'No group';
                    }

                    if(!empty($grader_db)){
                        $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);
                    }

                    if(!empty($infograder[$i][0]->poststopeergrade)){
                        //$empty = 0;
                        $count_topeergrade = count($infograder[$i][0]->poststopeergrade);
                        $count = $count_topeergrade + 2;
                        $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);

                        $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                        if($status_student == 1){
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:10px;font-size:13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        } else {
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:10px;font-size:13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        }

                        if($status_student == 1){
                            $source = new moodle_url('/peergrading/pix/blocked.png');
                            echo '<tr>'.'<td width="150" bgcolor='."$color".' rowspan='.$count.'>'.'<img src='.$source.' alt="Blocked" style="height:15px; width:15px"></img>'.' '.$grader_link.'</td>'.'<td width="40" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';
                        } else {
                            echo '<tr>'.'<td width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>'.'<td width="50" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td width="40" bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';
                        }


                        for($k = 0; $k < $count_topeergrade; $k++){
                            $postid = $infograder[$i][0]->poststopeergrade[$k];

                            if(!empty($postid)){
                                $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));

                                $postlink = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$postinfo->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $postinfo->subject);

                                $post_author = $DB->get_record('peerforum_posts', array('id' => $postid))->userid;


                                $author_db = $DB->get_record('user', array('id' => $post_author));


                                $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$author_db->id)), $author_db->firstname .' '. $author_db->lastname);

                                $groups = $DB->get_records('peerforum_groups', array('courseid'=>$courseid));

                                $author_group = 'no group';

                                if(!empty($groups)){
                                    foreach ($groups as $id => $value) {
                                        $students = explode(';', $groups[$id]->studentsid);
                                        $students = array_filter($students);

                                        if(in_array($post_author, $students)){
                                            $author_group = $groups[$id]->groupid;
                                            break;
                                        }
                                    }
                                } else {
                                    $author_group = $DB->get_record('groups_members', array('userid' => $author_db->id));
                                }

                            } else {
                                $author_group = null;
                            }

                            $remove_url = new moodle_url('/peergrading/remove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                            $remove_baseurl = $remove_url.'&user='.$grader.'&course='.$courseid.'&removepost='.$postid;

                            $source = new moodle_url('/peergrading/pix/delete.png');

                            $remove_button = '<form action="' . $remove_baseurl . '" method="post">'.
                                        '<div class="buttons"><input type="image" src="'.$source.'" alt="Submit" id="remove" name="remove" style="height:15px; width:15px" onClick="javascript:return confirm(\'Are you sure you want to remove the assigned post ['.$postid.'] from ['.$author_db->firstname .' '. $author_db->lastname.'] ?\');" value="'.get_string('remove', 'block_peerblock').'"/>'.
                                        '</div></form>';

                            if(is_object($author_group)){
                                            $author_group = $author_group->groupid;
                                        }

                            echo '<tr>'.'<td width="250" bgcolor='."$color".'>'.'(id:'.$postid.')'.'  '. $postlink .'</td>'.'<td width="150" bgcolor='."$color".'>'.$author_link.'</td>'.'<td width="50" bgcolor='."$color".'>'.$author_group.'</td>'.'<td width="50" bgcolor='."$color".'>'.$remove_button.'</td>'.'</tr>';


                        }
                        $assign_url = new moodle_url('/peergrading/assign.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

                        $assign_baseurl = $assign_url.'&'.'user='.$grader.'&courseid='.$courseid;

                        $assign_button = '<form action="' . $assign_baseurl . '" method="post">'.
                                    '<div class="buttons"><input type="text" style="width: 30px; margin-top:10px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                    '</div></form>';


                        echo '<tr>'.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</tr>';
                    } else {

                        $count = 0;

                        $assign_url = new moodle_url('/peergrading/assign.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

                        $assign_baseurl = $assign_url.'&'.'user='.$grader.'&courseid='.$courseid;

                        $assign_button = '<form action="' . $assign_baseurl . '" method="post">'.
                                    '<div class="buttons"><input type="text" style="width:30px; margin-top:10px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                    '</div></form>';

                        $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);


                        $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                        if($status_student == 1){
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        } else {
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        }

                        $source = new moodle_url('/peergrading/pix/blocked.png');

                        if($status_student == 1){
                            echo '<tr>'.'<td bgcolor='."$color".'>'.'<img src='.$source.' alt="Blocked" style="height:15px; width:15px"></img>'.' '.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                        } else {
                            echo '<tr>'.'<td bgcolor='."$color".'>'.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                        }

                    }
                        echo '</tr>';
                }

            }
                echo '</table>';
        }
    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }

}
// View peergrades
if($display == '3'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){
        $info = get_posts_grades();

        if(empty($info)){
            echo 'No information to display.';
        }

        if(!empty($info)){


            echo '<table class="managepeers"">'.
              '<tr">'.
                '<td bgcolor=#cccccc><b> Post peer graded </b></td>'.
                '<td bgcolor=#cccccc><b> Student </b></td>'.
                '<td bgcolor=#cccccc><b> Grade </b></td>'.
                '<td bgcolor=#cccccc><b> Feedback </b></td>'.
              '</tr>';

            $even = true;

            foreach ($info as $postid => $value) {
                if($even){
                    $color = '#f2f2f2';
                    $even = !$even;
                } else {
                    $color = '#ffffff';
                    $even = !$even;
                }

                //$postid = $info[$i]->itemid;
                $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));
                $postlink = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$postinfo->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $postinfo->subject);

                $count_peergrades = count($info[$postid]);

                $count = $count_peergrades + 1;

                echo '<tr>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.'(id:'.$postid.') '.$postlink.'</td>';

                for($i = 0; $i < $count_peergrades; $i++){
                    $grader = $info[$postid][$i]->user;
                    $grader_db = $DB->get_record('user', array('id' => $grader));
                    $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);

                    if(isset($info[$postid][$i]->peergrade)){
                        $grade = $info[$postid][$i]->peergrade;
                    } else {
                        $grade = '-';
                    }
                    if(isset($info[$postid][$i]->feedback)){
                        $feedback = $info[$postid][$i]->feedback;
                    } else {
                        $feedback = '-';
                    }

                    echo '<tr>'.'<td bgcolor='."$color".'>'. $grader_link .'</td>'.'<td bgcolor='."$color".'>'.$grade.'</td>'.'<td bgcolor='."$color".'>'.$feedback.'</td>'.'</tr>';

                }

                echo '</tr>';
            }
            echo '</table>';
        }
    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
}

//Manage Conflits
if($display == '4'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){
        $url = new moodle_url('/peergrading/importgroups.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        $baseurl = $url.'&'.'course='.$courseid;

        $select_file = '<form action="' . $baseurl . '" method="post" enctype="multipart/form-data">'.
                    'Select groups file: <input type="file" name="filegroups" accept=".csv">'. '<br>'.
                    '<div class="buttons"><input type="submit" style="margin-left:3px;margin-top:10px;font-size: 13px;" id="filegroupssubmit" name="filegroupssubmit" value="'.get_string('uploadfile', 'block_peerblock').'"/>'.'<br>'.
                    '<span style="color:grey">File extension: .csv</span>'.'<br>'.
                    '<span style="color:grey">File format: each file line represents a group of students separated by a comma</span>'. '<br>'.
                    '<span style="color:grey">File example:</span>'.'<br>'.
                    '<span style="color:grey">student#1,student#2</span>'.'<br>'.
                    '<span style="color:grey">student#3,student#4</span>'.'<br>'.
                    '</div></form>';

        echo $select_file;

        $currentgroups = $DB->get_records('peerforum_groups', array('courseid' => $courseid));

        $potentialmembers  = array();
        $potentialmembersoptions = '';
        $potentialmemberscount = 0;

        $currentgroupsoptions = '';

        if(!empty($currentgroups)){
            $num_groups = count($currentgroups);

            foreach ($currentgroups as $id => $value) {
                $currentgroupsoptions .= '<option disabled value="'.$currentgroups[$id]->groupid.'.">'.'Group #'. $currentgroups[$id]->groupid.'</option>';

                $students_id = explode(';', $currentgroups[$id]->studentsid);
                $students_id = array_filter($students_id);

                foreach ($students_id as $key => $value) {
                    $std_firstname = $DB->get_record('user', array('id' => $students_id[$key]))->firstname;
                    $std_lastname = $DB->get_record('user', array('id' => $students_id[$key]))->lastname;
                    $std_name = $std_firstname.' '.$std_lastname;

                $currentgroupsoptions .= '<option value="'.$students_id[$key].'.">'.format_string($std_name).'</option>';
                }
            }
        } else {
            $num_groups = 0;
        }


        $conflits = $DB->get_records('peerforum_peergrade_conflits', array('courseid' => $courseid));

        $conflitssoptions = '';

        if(!empty($conflits)){
            $num_conflits = count($conflits);
            $countconflit=1;

            $last_conflit = max(array_keys($conflits));

            foreach ($conflits as $id => $value) {
                if($id == $last_conflit){
                    $conflitssoptions .= '<option selected value="conflit:'.$conflits[$id]->id.'.">'.'Conflit #'. $countconflit.'</option>';
                }else {
                    $conflitssoptions .= '<option value="conflit:'.$conflits[$id]->id.'.">'.'Conflit #'. $countconflit.'</option>';
                }

                $students_id = explode(';', $conflits[$id]->idstudents);
                $students_id = array_filter($students_id);

                foreach ($students_id as $key => $value) {
                    $std_id = $students_id[$key];

                    $val = $conflits[$id]->id.'|'. $std_id;

                    if($std_id != -1){

                        $std_firstname = $DB->get_record('user', array('id' => $std_id))->firstname;
                        $std_lastname = $DB->get_record('user', array('id' => $std_id))->lastname;
                        $std_name = $std_firstname.' '.$std_lastname;

                        $groups = $DB->get_records('peerforum_groups', array('courseid' => $courseid));

                        foreach ($groups as $g => $value) {
                            $students_group = explode(';', $groups[$g]->studentsid);
                            if(in_array($std_id, $students_group)){
                                $std_group = $groups[$g]->groupid;
                                break;
                            }
                        }

                        $conflitssoptions .= '<option value="student:'.$val.'.">'.format_string($std_name).' (group #'.$std_group.')'.'</option>';
                    }
                }
                $countconflit = $countconflit + 1;

            }
        } else {
            $num_conflits = 0;
        }

        $url_conflits = new moodle_url('/peergrading/manageconflits.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

        ?>
        <div id="addmembersform">
            <form id="assignform" method="post" action=" <?php echo $url_conflits ?>">
            <div>
            <input type="hidden" name="sesskey" value="<?php p(sesskey()); ?>" />
            <table summary="" class="generaltable generalbox groupmanagementtable boxaligncenter">
            <tr>
              <td id="existingcell">
                  <input name="addall" id="addall" type="submit"
                            value="<?php echo get_string('addall', 'peerforum'); ?>"
                            title="<?php print_string('addall', 'peerforum'); ?>"
                            />
                  <label for="addselect"><?php print_string('existinggroups', 'peerforum', $num_groups); ?></label>
                  <div class="userselector" id="addselect_wrapper">
                  <select name="addselect[]" size="20" id="addselect" multiple="multiple"
                          onfocus="document.getElementById('assignform').add.disabled=false;
                                   document.getElementById('assignform').remove.disabled=true;
                                   document.getElementById('assignform').addselect.selectedIndex=-1;">
                  <?php echo $currentgroupsoptions ?>
                  </select></div></td>
              <td id="buttonscell">
                <p class="arrow_button">
                    <input name="addconflit" id="addconflit" type="submit"
                           value="<?php echo get_string('addconflit', 'peerforum').'&nbsp;'.$OUTPUT->rarrow(); ?>"
                           title="<?php print_string('addconflit', 'peerforum'); ?>"
                           /><br>

                   <input name="removeconflit" id="removeconflit" type="submit"
                              value="<?php echo $OUTPUT->larrow().'&nbsp;'.get_string('removeconflit', 'peerforum'); ?>"
                              title="<?php print_string('removeconflit', 'peerforum'); ?>"
                              /><br><br>

                    <input name="addstudent" id="addstudent" type="submit"
                           value="<?php echo get_string('addstudent', 'peerforum').'&nbsp;'.$OUTPUT->rarrow(); ?>"
                           title="<?php print_string('addstudent', 'peerforum'); ?>"
                           /><br>

                    <input name="removestudent" id="removestudent" type="submit"
                               value="<?php echo $OUTPUT->larrow().'&nbsp;'.get_string('removestudent', 'peerforum'); ?>"
                               title="<?php print_string('removestudent', 'peerforum'); ?>"
                               />
                </p>
              </td>
              <td id="potentialcell">
                  <input name="removeall" id="removeall" type="submit"
                            value="<?php echo get_string('removeall', 'peerforum'); ?>"
                            title="<?php print_string('removeall', 'peerforum'); ?>"
                            />
                  <label for="conflitselect"><?php print_string('numconflits', 'peerforum', $num_conflits); ?></label>
                  <div class="userselector" id="conflitselect_wrapper">
                  <select name="conflitselect[]" size="20" id="conflitselect" multiple="multiple"
                          onfocus="document.getElementById('assignform').add.disabled=true;
                                   document.getElementById('assignform').remove.disabled=false;
                                   document.getElementById('assignform').conflitselect.selectedIndex=-1;">
                 <?php echo $conflitssoptions ?>
                 </select>
                  </div>
               </td>
            </tr>
            <tr><td colspan="3" id="backcell">
                <input type="submit" name="cancel" value="<?php print_string('backtogroupings', 'group'); ?>" />
            </td></tr>
            </table>
            </div>
            </form>
        </div>


<?php
    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
}
// TO PEER GRADE
if($display == '1'){

    echo $OUTPUT->heading(format_string($poststopeergrade), 2);

    //get all the posts from the database
    $userposts = array();
    $userposts = peerforum_get_user_posts_to_peergrade($USER->id, $courseid);

    $userposts_graded = array();
    $userposts_graded = peerforum_get_user_posts_peergraded($USER->id, $courseid);

        if(!empty($userposts)){
            for($i = 0; $i < count($userposts); $i++){

                if(!in_array($userposts[$i], $userposts_graded)){

                    $post = peerforum_get_post_full($userposts[$i]);

                    if(!empty($post)){
                        $discussion = $DB->get_record('peerforum_discussions', array('id' => $post->discussion));
                        $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
                        $course = $DB->get_record('course', array('id' => $peerforum->course));
                        $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id);

                        $displaymode = get_user_preferences("peerforum_displaymode", $CFG->peerforum_displaymode);

                        $index = true;

                       peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post, $displaymode, null, true, true, true, true, $PAGE->url, $index);
                    }
                }
            }
        }
        if(empty($userposts)){
            echo 'No posts to peergrade.';
        }
}


// POSTS PEERGRADED
if($display == '2'){
    echo $OUTPUT->heading(format_string($postspeergraded), 2);

    $userposts_graded = array();
    $userposts_graded = peerforum_get_user_posts_peergraded($USER->id, $courseid);

        if(!empty($userposts_graded)){
            for($i = 0; $i < count($userposts_graded); $i++){
                $post_graded = peerforum_get_post_full($userposts_graded[$i]);

                if(!empty($post_graded)){
                    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post_graded->discussion));
                    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
                    $course = $DB->get_record('course', array('id' => $peerforum->course));
                    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id);

                    $displaymode = get_user_preferences("peerforum_displaymode", $CFG->peerforum_displaymode);

                    $index = true;

                    peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post_graded, $displaymode, null, true, true, true, false, $PAGE->url, $index);
                }
            }
        }
        if(empty($userposts_graded)){
            echo 'No posts peergraded.';
        }

}

// POSTS EXPIRED
if($display == '5'){
    echo $OUTPUT->heading(format_string($postsexpired), 2);

    $userposts_expired = array();
    $userposts_expired = peerforum_get_user_posts_expired($USER->id, $courseid);

        if(!empty($userposts_expired)){
            for($i = 0; $i < count($userposts_expired); $i++){
                $post_expired = peerforum_get_post_full($userposts_expired[$i]);

                if(!empty($post_expired)){
                    $discussion = $DB->get_record('peerforum_discussions', array('id' => $post_expired->discussion));
                    $peerforum = $DB->get_record('peerforum', array('id' => $discussion->peerforum));
                    $course = $DB->get_record('course', array('id' => $peerforum->course));
                    $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $course->id);

                    $displaymode = get_user_preferences("peerforum_displaymode", $CFG->peerforum_displaymode);

                    $index = true;

                    peerforum_print_discussion($course, $cm, $peerforum, $discussion, $post_expired, $displaymode, null, true, true, true, false, $PAGE->url, $index);
                }
            }
        }
        if(empty($userposts_graded)){
            echo 'No posts whose peer grading time has expired.';
        }

}



echo $OUTPUT->box_end();

echo $OUTPUT->footer();
