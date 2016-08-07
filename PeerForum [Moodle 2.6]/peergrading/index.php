<?php
/**
 * @package    core_peergrade
 * @copyright  2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @author     Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
if (is_file($CFG->dirroot.'/mod/peerforum/lib.php'))
{
    require_once($CFG->dirroot.'/mod/peerforum/lib.php');
} else {
    return;
}

require_once($CFG->libdir.'/tablelib.php');

require_once($CFG->dirroot . '/peergrade/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$display = required_param('display', PARAM_INT);
$peerforumid = optional_param('peerforum', null, PARAM_INT);
$managepostsmode = optional_param('managepostsmode', null, PARAM_INT);
$managegradersmode = optional_param('managegradersmode', null, PARAM_INT);
$viewpeergradesmode = optional_param('viewpeergradesmode', null, PARAM_INT);
$firstletter = optional_param('sifirst', null, PARAM_TEXT);
$lastletter = optional_param('silast', null, PARAM_TEXT);
$firstletterpost = optional_param('sifirstpost', null, PARAM_TEXT);
$currentpage = optional_param('page', 0, PARAM_INT);
$perpage_big = optional_param('perpage', 10, PARAM_INT);
$perpage_small = optional_param('perpage', 5, PARAM_INT);

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

adjust_database();

/// Output the page
$strpeerblock  = get_string('pluginname', 'block_peerblock');

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

$viewpeergrades = get_string('viewpeergrades', 'block_peerblock');
$manageposts = get_string('manageposts', 'block_peerblock');
$manageconflicts = get_string('manageconflicts', 'block_peerblock');
$managegraders_poststopeergrade = get_string('managegraders_poststopeergrade', 'block_peerblock');
$managegraders_postspeergraded = get_string('managegraders_postspeergraded', 'block_peerblock');
$viewgradersstats = get_string('viewgradersstats', 'block_peerblock');

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
        $currenttab = 'display_managegraders_poststopeergrade';

    } else if ($display == '3'){
        $display = '3';
        $currenttab = 'display_viewpeergrades';


    } else if ($display == '4'){
        $display = '4';
        $currenttab = 'display_manageconflicts';

    } else if ($display == '6'){
        $display = '6';
        $currenttab = 'display_managegraders_postspeergraded';

    } else if ($display == '7'){
        $display = '7';
        $currenttab = 'display_viewgradersstats';
    }

    $row[] = new tabobject('display_manageposts',
                                new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => '-1')),
                                $manageposts);

    $row[] = new tabobject('display_managegraders_poststopeergrade',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '-2')),
                                    $managegraders_poststopeergrade);

    $row[] = new tabobject('display_managegraders_postspeergraded',
                                    new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '6')),
                                    $managegraders_postspeergraded);

    $row[] = new tabobject('display_viewpeergrades',
                                new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '3')),
                                $viewpeergrades);

    $row[] = new tabobject('display_viewgradersstats',
                                new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '7')),
                                $viewgradersstats);

    $row[] = new tabobject('display_manageconflicts',
                                new moodle_url('/peergrading/index.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => '4')),
                                $manageconflicts);
}

if(!has_capability('mod/peerforum:viewpanelpeergrades', $context)){

    if($display == '1' || $display == '-1' || $display == '-2' || $display == '3' || $display == '4' || $display == '6' || $display == '7'){
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

echo '<div class="groupdisplay" style="text-align:center;">';
echo $OUTPUT->tabtree($row, $currenttab);
echo '</div>';

// Manage Posts
if($display == '-1'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        $posts = get_all_posts_info($courseid);

        if(!empty($posts)){
            if(empty($managepostsmode)){
                $managepostsmode = MANAGEPOSTS_MODE_SEEALL;
            }

            $select = new single_select(new moodle_url("/peergrading/index.php", array('courseid' => $courseid, 'userid' => $userid, 'display' => $display, 'peerforum' => $peerforumid)), 'managepostsmode', peerforum_get_manage_posts_filters(), $managepostsmode, null, "managepostsmode");
            $select->set_label(get_string('displaymanagepostsmode', 'peerforum'), array('class' => 'accesshide'));
            $select->class = "managepostsmode";

            echo $OUTPUT->render($select);

             if($managepostsmode == MANAGEPOSTS_MODE_SEEEXPIRED){
                 $posts = get_posts_expired($posts);
             }
             else if($managepostsmode == MANAGEPOSTS_MODE_SEENOTEXPIRED){
                 $posts = get_posts_not_expired($posts);
             }
             else if($managepostsmode == MANAGEPOSTS_MODE_SEENOTGRADED){
                 $posts = get_posts_not_graded($posts);
             }
             else if ($managepostsmode == MANAGEPOSTS_MODE_SEEALL){
                 $posts = get_all_posts_info($courseid);
             }
             else if ($managepostsmode == MANAGEPOSTS_MODE_SEEGRADED){
                 $posts = get_posts_graded($posts);
             }

             // initial letter
              $alpha  = explode(',', get_string('alphabet', 'langconfig'));
              $strall = get_string('all');

              $baseurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'managepostsmode' => $managepostsmode));

              echo '<div class="initialbar firstinitial">'.get_string('firstname').' of post author: ';
              if(!empty($firstletter)) {
                  echo '<a href="'.$baseurl->out().'&amp;sifirst=">'.$strall.'</a>';
              } else {
                  echo '<strong>'.$strall.'</strong>';
              }

              foreach ($alpha as $letter) {
                  if ($letter == $firstletter) {
                      echo ' <strong>'.$letter.'</strong>';
                  } else {
                      echo ' <a href="'.$baseurl->out().'&amp;sifirst='.$letter.'">'.$letter.'</a>';
                  }
              }
              echo '</div>';

              // last letter
              echo '<div class="initialbar lastinitial">'.get_string('lastname').' of post author: ';
              if(!empty($lastletter)) {
                  echo '<a href="'.$baseurl->out().'&amp;silast=">'.$strall.'</a>';
              } else {
                  echo '<strong>'.$strall.'</strong>';
              }
              foreach ($alpha as $letter) {
                  if ($letter == $lastletter) {
                      echo ' <strong>'.$letter.'</strong>';
                  } else {
                      echo ' <a href="'.$baseurl->out().'&amp;silast='.$letter.'">'.$letter.'</a>';
                  }
              }
              echo '</div>';
              echo '</br>';

              // initial letter post
               echo '<div class="initialbar firstinitial">'.get_string('firstletterpost', 'peerforum').':';
               if(!empty($firstletterpost)) {
                   echo '<a href="'.$baseurl->out().'&amp;sifirstpost=">'.$strall.'</a>';
               } else {
                   echo '<strong>'.$strall.'</strong>';
               }

               foreach ($alpha as $letterpost) {
                   if ($letterpost == $firstletterpost) {
                       echo ' <strong>'.$letterpost.'</strong>';
                   } else {
                       echo ' <a href="'.$baseurl->out().'&amp;sifirstpost='.$letterpost.'">'.$letterpost.'</a>';
                   }
               }
               echo '</div>';
               echo '</br>';
        }

        if(empty($posts)){
            echo 'No posts to display.';
            $total_posts = 0;
        }

       if(!empty($posts)){

            echo '<table class="managepeers"">'.
              '<tr">'.
                '<td bgcolor=#cccccc><b> Subject </b></td>'.
                '<td bgcolor=#cccccc><b> Post author </b></td>'.
                '<td bgcolor=#cccccc><b> Peer grader </b></td>'.
                '<td bgcolor=#cccccc><b> Remove grader </b></td>'.
                '<td bgcolor=#cccccc><b> Time left to grade </b></td>'.
                '<td bgcolor=#cccccc><b> Graded? </b></td>'.
                '<td bgcolor=#cccccc><b> Block/Unblock post to grader </b></td>'.
              '</tr>';

              $even = true;
              $subeven = true;

             krsort($posts);

             //filter students by firstname
             if(!empty($firstletter)){
                 $posts = array_filter($posts, function($a) use ($firstletter) {return $a->authorname[0] == $firstletter;});
              }

              //filter students by lastname
             if(!empty($lastletter)){
                  $posts = array_filter($posts, function($a) use ($lastletter) {$surname = explode(' ', $a->authorname)[1]; return $surname[0] == $lastletter;});
              }

              //filter posts by firstletter
             if(!empty($firstletterpost)){
                  $posts = array_filter($posts, function($a) use ($firstletterpost) {$subject = explode(' ', $a->subject)[1]; return $subject[0] == $firstletterpost;});
              }

              //pagination
              $total_posts = count($posts);
              $start = $currentpage * $perpage_small;

              if ($start > $total_posts) {
                    $currentpage = 0;
                    $start = 0;
              }

              $posts = array_slice($posts, $start, $perpage_small, true);


              foreach ($posts as $i => $value) {

                  $postid = $posts[$i]->postid;
                  $subject = $posts[$i]->subject;
                  $postsubject = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$posts[$i]->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $subject);

                  $count_graders = count($posts[$i]->peergraders);

                      $count = $count_graders + 2;

                      if($even){
                          $color = '#f2f2f2';
                      } else {
                          $color = 	'#ffffff';
                      }
                      $even = !$even;

                      $post_author_id = $posts[$i]->authorid;
                      $post_author = explode(' ', $posts[$i]->authorname);
                      $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$post_author_id)), $post_author[0] .' '. $post_author[1]);

                      echo '<tr>'.'<td height="1" width="200" bgcolor='."$color".' rowspan='.$count.'>'. '(id:'.$postid.') '.$postsubject.'</td>'.'<td height="1" width="80" bgcolor='."$color".' rowspan='.$count.'>'. $author_link.'</td>';

                      //order students by name
                      uasort($posts[$i]->peergraders, function ($a, $b) {
                          return strcmp($a->authorname, $b->authorname);
                      });

                      foreach ($posts[$i]->peergraders as $key => $value) {

                          $peergraderid = $posts[$i]->peergraders[$key]->id;
                          $status = get_post_status($postid, $peergraderid, $courseid);

                          $time_expire = get_time_expire($postid, $peergraderid);

                          if(!empty($time_expire)){
                              if($time_expire->invert == 1){
                                  $days = $time_expire->d;
                                  $months = $time_expire->m;
                                  $years = $time_expire->y;

                                  if(!empty($years)){
                                      $time = $time_expire->d.'y:'.$time_expire->d.'M:'.$time_expire->d.'d:'.$time_expire->h.'h:'.$time_expire->i.'m';
                                  } else if(!empty($months)){
                                      $time = $time_expire->d.'M:'.$time_expire->d.'d:'.$time_expire->h.'h:'.$time_expire->i.'m';
                                  } else if(!empty($days)){
                                      $time = $time_expire->d.'d:'.$time_expire->h.'h:'.$time_expire->i.'m';
                                  } else {
                                      $time = $time_expire->h.'h:'.$time_expire->i.'m';
                                  }
                              } else {
                                  $time = 'expired';
                                  update_post_expired($postid, $peergraderid, $courseid);
                              }
                          } else {
                              $time = 'expired';
                          }

                          $grader = $DB->get_record('user', array('id' => $peergraderid));
                          $name = explode(' ', $posts[$i]->peergraders[$key]->authorname);

                          $peergrader = html_writer::link(new moodle_url('/user/view.php', array('id'=> $peergraderid)), $name[0] .' '. $name[1]);

                          $baseurl = new moodle_url('/peergrading/block.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'postid' => $postid, 'user' => $peergraderid, 'status' => $status));

                          $peergradedone = verify_peergrade($postid, $peergraderid);

                          if($peergradedone == 1){
                              $src = new moodle_url('/peergrading/pix/checked.png');

                            $done = '<img src="'.$src.'" alt="Yes" style="width:15px;height:15px;">';
                              $disable = 'disabled';
                          } else {
                              $src = new moodle_url('/peergrading/pix/delete.png');

                             $done = '<img src="'.$src.'" alt="No" style="width:15px;height:15px;">';
                              $disable = '';
                          }


                          if($status == 1){
                              $button = '<form action="' . $baseurl . '" method="post">'.
                                          '<div class="buttons"><input '.$disable.' style="margin-top:1px;margin-bottom:1px;font-size: 12px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                          '</div></form>';
                          } else {
                              $button = '<form action="' . $baseurl . '" method="post">'.
                                          '<div class="buttons"><input '.$disable.' style="margin-top:1px;margin-bottom:1px;font-size: 12px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                          '</div></form>';
                          }

                          if($status == 1){
                              $status_str =  '<font color="red">Blocked</font>' ;
                              $color = '#ffb3b3';
                          } else {
                              $status_str = '<font color="green">Unblocked</font>';
                              if($subeven){
                                  $color = '#f2f2f2';
                              } else {
                                  $color = 	'#ffffff';
                              }
                              $subeven = !$subeven;
                          }

                          $src = new moodle_url('/peergrading/pix/delete.png');
                          $remove_url = new moodle_url('/peergrading/studentremove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display, 'itemid' => $postid, 'grader' => $peergraderid, 'itemid' => $postid));

                          $remove_button = '<form action="' . $remove_url . '" method="post">'.
                                      '<div class="buttons"><input type="submit" id="removestudent" name="removestudent" style="margin-top:1px;margin-bottom:1px;font-size: 12px;" onClick="javascript:return confirm(\'Are you sure you want to remove peer grader ['.$grader->firstname .' '. $grader->lastname.'] from post id ['.$postid.'] ? This will eliminate the peer grade given by this student to this post.\');" value="'.get_string('remove', 'block_peerblock').'"/>'.
                                      '</div>
                                        </form>';


                          echo '<tr>'.'<td height="1" width="200" bgcolor='."$color".'>'.$peergrader.'</td>'.'<td height="1" width="10" bgcolor='."$color".'>'.$remove_button.'</td>'.'<td height="1" width="80" bgcolor='."$color".'>'.$time.'</td>'.'<td height="1" width="50" bgcolor='."$color".'>'.$done.'</td>'.'<td height="1" width="40" bgcolor='."$color".'>'.$button.'</td>'.'</tr>';

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
                                    '<select style="margin-top:1px; margin-bottom:1px; font-size: 13px;" class="poststudentmenu studentinput" name="menustds'.$postid.'" id="menustds"'.$postid.'">'.
                                    $students_options.
                                    '</select>'.
                                    '<input style="margin-left:3px;margin-top:1px; margin-bottom:1px; font-size: 13px;" type="submit" name="assignstd'.$postid.'" class="assignstd" id="assignstd'.$postid.'" value="'.s(get_string('assignpeer', 'peerforum')).'" />'.
                                    '</div></form>';

                      } else {

                        $select_std = '<form action="' . $url_select . '" method="post">'.
                                    '<select style="margin-left:3px;margin-top:1px; margin-bottom:1px; font-size: 13px;" disabled="true" class="poststudentmenu studentinput" name="menustds'.$postid.'" id="menustds"'.$postid.'">'.
                                    '<option value="'.UNSET_STUDENT.'.">'.format_string($selectstudentrandom).'</option>'.
                                    '</select>'.
                                    '<input  style="margin-top:1px; margin-bottom:1px; font-size: 13px;" disabled="true" type="submit" name="assignstd'.$postid.'" class="assignstd" id="assignstd'.$postid.'" value="'.s(get_string('assignpeer', 'peerforum')).'" />'.
                                    '</div></form>';
                      }

                      echo '<tr>'.'<td height="1px" bgcolor='."#d9d9d9".'>'.$select_std.'</td>'.'</tr>';
              }
              echo '</table>';
      }

      //pagination
      echo '</br>';
      $pageurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'sifirst' => $firstletter, 'silast' => $lastletter, 'sifirstpost' => $firstletterpost));
      echo $OUTPUT->paging_bar($total_posts, $currentpage, $perpage_small, $pageurl);

    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
 }

// Manage Graders with posts not peergraded
if($display == '-2'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        $infograder = get_all_peergrades($courseid);

        if(!empty($infograder)){
            if(empty($managegradersmode)){
                $managegradersmode = MANAGEGRADERS_MODE_SEENOTGRADED;
            }

            $select = new single_select(new moodle_url("/peergrading/index.php", array('courseid' => $courseid, 'userid' => $userid, 'display' => $display, 'peerforum' => $peerforumid)), 'managegradersmode', peerforum_get_manage_graders_filters(), $managegradersmode, null, "managegradersmode");
            $select->set_label(get_string('displaymanagegradersmode', 'peerforum'), array('class' => 'accesshide'));
            $select->class = "managegradersmode";

            echo $OUTPUT->render($select);

             if($managegradersmode == MANAGEGRADERS_MODE_SEEEXPIRED){
                 $infograder = get_posts_expired_infograder($infograder, $courseid);
             }
             else if($managegradersmode == MANAGEGRADERS_MODE_SEENOTGRADED){
                 $infograder = get_all_peergrades($courseid);
             }

             // initial letter
              $alpha  = explode(',', get_string('alphabet', 'langconfig'));
              $strall = get_string('all');

              $baseurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'managegradersmode' => $managegradersmode));

              echo '<div class="initialbar firstinitial">'.get_string('firstname').' : ';
              if(!empty($firstletter)) {
                  echo '<a href="'.$baseurl->out().'&amp;sifirst=">'.$strall.'</a>';
              } else {
                  echo '<strong>'.$strall.'</strong>';
              }

              foreach ($alpha as $letter) {
                  if ($letter == $firstletter) {
                      echo ' <strong>'.$letter.'</strong>';
                  } else {
                      echo ' <a href="'.$baseurl->out().'&amp;sifirst='.$letter.'">'.$letter.'</a>';
                  }
              }
              echo '</div>';

              // last letter
              echo '<div class="initialbar lastinitial">'.get_string('lastname').' : ';
              if(!empty($lastletter)) {
                  echo '<a href="'.$baseurl->out().'&amp;silast=">'.$strall.'</a>';
              } else {
                  echo '<strong>'.$strall.'</strong>';
              }
              foreach ($alpha as $letter) {
                  if ($letter == $lastletter) {
                      echo ' <strong>'.$letter.'</strong>';
                  } else {
                      echo ' <a href="'.$baseurl->out().'&amp;silast='.$letter.'">'.$letter.'</a>';
                  }
              }
              echo '</div>';
              echo '</br>';
        }

        if(empty($infograder)){
            echo 'No peer graders to display.';
            $total_infograders = 0;
        }

        if(!empty($infograder)){

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
            $subeven = true;

            //order students by name
            uasort($infograder, function ($a, $b) {
                return strcmp($a[0]->authorname, $b[0]->authorname);
            });

            //filter students by firstname
            if(!empty($firstletter)){
                 $infograder = array_filter($infograder, function($a) use ($firstletter) {return $a[0]->authorname[0] == $firstletter;});
             }

             //filter students by lastname
            if(!empty($lastletter)){
                 $infograder = array_filter($infograder, function($a) use ($lastletter) {$surname = explode(' ', $a[0]->authorname)[1]; return $surname[0] == $lastletter;});
             }

             //pagination
             $total_infograders = count($infograder);
             $start = $currentpage * $perpage_small;

             if ($start > $total_infograders) {
                   $currentpage = 0;
                   $start = 0;
             }

             $infograder = array_slice($infograder, $start, $perpage_small, true);

             foreach ($infograder as $i => $value) {

                if($even){
                    $color = '#f2f2f2'; //grey
                } else {
                    $color = '#ffffff'; //white
                }
                $even = !$even;

                $grader = $infograder[$i][0]->authorid;

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
                        $group_user = '-';
                    }

                    if(!empty($grader_db)){
                        $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);
                    }

                    if($managegradersmode == MANAGEGRADERS_MODE_SEENOTGRADED){
                        if(!empty($infograder[$i][0]->poststopeergrade)){

                            $count_topeergrade = count($infograder[$i][0]->poststopeergrade);
                            $count = $count_topeergrade + 2;
                            $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);

                            $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                            $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                            if($status_student == 1){
                                $button_block = '<form action="' . $baseurl . '" method="post">'.
                                            '<div class="buttons"><input style="margin-left:3px;margin-top:1px; margin-bottom:1px;font-size:13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                            '</div></form>';

                            } else {
                                $button_block = '<form action="' . $baseurl . '" method="post">'.
                                            '<div class="buttons"><input style="margin-left:3px;margin-top:1px; margin-bottom:1px; font-size:13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                            '</div></form>';
                            }

                            if($subeven){
                                $color = '#f2f2f2';
                            } else {
                                $color = '#ffffff';
                            }
                            $subeven = !$subeven;

                            if($status_student == 1){
                                $color = '#ffb3b3';

                                $source = new moodle_url('/peergrading/pix/blocked.png');
                                echo '<tr>'.'<td height="1" width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>'.'<td height="1" width="40" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';
                            } else {
                                echo '<tr>'.'<td height="1" width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>'.'<td height="1" width="50" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td width="40" bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';

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

                                    $author_group = '-';

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
                                        if(empty($author_group)){
                                            $author_group = '-';
                                        }
                                    }

                                } else {
                                    $author_group = '-';
                                }

                                $remove_url = new moodle_url('/peergrading/remove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                                $remove_baseurl = $remove_url.'&user='.$grader.'&course='.$courseid.'&removepost='.$postid;

                                $source = new moodle_url('/peergrading/pix/blocked.png');

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
                                        '<div class="buttons"><input type="text" style="width: 30px; margin-top:1px; margin-bottom:1px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                        '</div></form>';


                            echo '<tr>'.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</tr>';
                        } else {

                            $count = 0;

                            $assign_url = new moodle_url('/peergrading/assign.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

                            $assign_baseurl = $assign_url.'&'.'user='.$grader.'&courseid='.$courseid;

                            $assign_button = '<form action="' . $assign_baseurl . '" method="post">'.
                                        '<div class="buttons"><input type="text" style="width:30px; margin-top:1px; margin-bottom:1px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                        '</div></form>';

                            $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);


                            $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                            $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                            if($status_student == 1){
                                $button_block = '<form action="' . $baseurl . '" method="post">'.
                                            '<div class="buttons"><input style="margin-left:3px;margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                            '</div></form>';
                            } else {
                                $button_block = '<form action="' . $baseurl . '" method="post">'.
                                            '<div class="buttons"><input style="margin-left:3px;margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                            '</div></form>';
                            }

                            $source = new moodle_url('/peergrading/pix/blocked.png');

                            if($status_student == 1){
                                $color = '#ffb3b3';

                                echo '<tr>'.'<td bgcolor='."$color".'>'.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                            } else {
                                if($subeven){
                                    $color = '#f2f2f2';
                                } else {
                                    $color = '#ffffff';
                                }
                                $subeven = !$subeven;
                                echo '<tr>'.'<td bgcolor='."$color".'>'.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                            }

                        }
                } else if ($managegradersmode == MANAGEGRADERS_MODE_SEEEXPIRED){
                    if(!empty($infograder[$i][0]->postsexpired)){

                        $count_topeergrade = count($infograder[$i][0]->postsexpired);
                        $count = $count_topeergrade + 2;
                        $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);

                        $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                        if($status_student == 1){
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:1px; margin-bottom:1px;font-size:13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                        '</div></form>';

                        } else {
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:1px; margin-bottom:1px; font-size:13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        }

                        if($subeven){
                            $color = '#f2f2f2';
                        } else {
                            $color = '#ffffff';
                        }
                        $subeven = !$subeven;

                        if($status_student == 1){
                            $color = '#ffb3b3';

                            $source = new moodle_url('/peergrading/pix/blocked.png');
                            echo '<tr>'.'<td height="1" width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>'.'<td height="1" width="40" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';
                        } else {
                            echo '<tr>'.'<td height="1" width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>'.'<td height="1" width="50" bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td width="40" bgcolor='."$color".' rowspan='.$count.'>'.$group_user.'</td>';

                        }

                        for($k = 0; $k < $count_topeergrade; $k++){

                            $postid = $infograder[$i][0]->postsexpired[$k];

                            if(!empty($postid)){
                                $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));

                                $postlink = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$postinfo->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $postinfo->subject);

                                $post_author = $DB->get_record('peerforum_posts', array('id' => $postid))->userid;

                                $author_db = $DB->get_record('user', array('id' => $post_author));

                                $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$author_db->id)), $author_db->firstname .' '. $author_db->lastname);

                                $groups = $DB->get_records('peerforum_groups', array('courseid'=>$courseid));

                                $author_group = '-';

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
                                    if(empty($author_group)){
                                        $author_group = '-';
                                    }
                                }

                            } else {
                                $author_group = '-';
                            }

                            $remove_url = new moodle_url('/peergrading/remove.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                            $remove_baseurl = $remove_url.'&user='.$grader.'&course='.$courseid.'&removepost='.$postid;

                            $source = new moodle_url('/peergrading/pix/blocked.png');

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
                                    '<div class="buttons"><input type="text" style="width: 30px; margin-top:1px; margin-bottom:1px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                    '</div></form>';


                        echo '<tr>'.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</tr>';
                    } else {

                        $count = 0;

                        $assign_url = new moodle_url('/peergrading/assign.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

                        $assign_baseurl = $assign_url.'&'.'user='.$grader.'&courseid='.$courseid;

                        $assign_button = '<form action="' . $assign_baseurl . '" method="post">'.
                                    '<div class="buttons"><input type="text" style="width:30px; margin-top:1px; margin-bottom:1px;" name="assignpost" value=""><input style="margin-left:3px;margin-top:10px;font-size: 13px;" type="submit" id="assign" name="assign" value="'.get_string('assign', 'block_peerblock').'"/>'.
                                    '</div></form>';

                        $status_student = get_student_status($infograder[$i][0]->authorid, $courseid);


                        $url = new moodle_url('/peergrading/block_student.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        $baseurl = $url.'&'.'user='.$infograder[$i][0]->authorid.'&status='.$status_student;


                        if($status_student == 1){
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        } else {
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-left:3px;margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        }

                        $source = new moodle_url('/peergrading/pix/blocked.png');

                        if($status_student == 1){
                            $color = '#ffb3b3';

                            echo '<tr>'.'<td bgcolor='."$color".'>'.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                        } else {
                            if($subeven){
                                $color = '#f2f2f2';
                            } else {
                                $color = '#ffffff';
                            }
                            $subeven = !$subeven;
                            echo '<tr>'.'<td bgcolor='."$color".'>'.$grader_link.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$button_block.'</td>'.'<td bgcolor='."$color".' >'.$group_user.'<td bgcolor='."$color".'>'.$assign_button.'</td>'.'</td>';
                        }

                    }
                }
                        echo '</tr>';
                }

            }
                echo '</table>';
        }
        //pagination
        echo '</br>';
        $pageurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'sifirst' => $firstletter, 'silast' => $lastletter, 'managegradersmode' => $managegradersmode));
        echo $OUTPUT->paging_bar($total_infograders, $currentpage, $perpage_small, $pageurl);
    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
}

// Manage Graders with posts peergraded
if($display == '6'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){

        $infograder = get_all_peergrades_done($courseid);

        if(!empty($infograder)){

            // initial letter
             $alpha  = explode(',', get_string('alphabet', 'langconfig'));
             $strall = get_string('all');

             $baseurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display));

             echo '<div class="initialbar firstinitial">'.get_string('firstname').' : ';
             if(!empty($firstletter)) {
                 echo '<a href="'.$baseurl->out().'&amp;sifirst=">'.$strall.'</a>';
             } else {
                 echo '<strong>'.$strall.'</strong>';
             }

             foreach ($alpha as $letter) {
                 if ($letter == $firstletter) {
                     echo ' <strong>'.$letter.'</strong>';
                 } else {
                     echo ' <a href="'.$baseurl->out().'&amp;sifirst='.$letter.'">'.$letter.'</a>';
                 }
             }
             echo '</div>';


             // last letter
             echo '<div class="initialbar lastinitial">'.get_string('lastname').' : ';
             if(!empty($lastletter)) {
                 echo '<a href="'.$baseurl->out().'&amp;silast=">'.$strall.'</a>';
             } else {
                 echo '<strong>'.$strall.'</strong>';
             }
             foreach ($alpha as $letter) {
                 if ($letter == $lastletter) {
                     echo ' <strong>'.$letter.'</strong>';
                 } else {
                     echo ' <a href="'.$baseurl->out().'&amp;silast='.$letter.'">'.$letter.'</a>';
                 }
             }
             echo '</div>';
             echo '</br>';
        }

        if(empty($infograder)){
            echo 'No peer graders to display.';
            $total_infograders = 0;
        }

        if(!empty($infograder)){

            echo '<table class="managepeers">'.
              '<tr>'.
                '<td bgcolor=#cccccc><b> Student </b></td>'.
                '<td bgcolor=#cccccc><b> Posts graded </b></td>'.
                '<td bgcolor=#cccccc><b> Post author </b></td>'.
                '<td bgcolor=#cccccc><b> Grade </b></td>'.
                '<td bgcolor=#cccccc><b> Feedback </b></td>'.
                '<td bgcolor=#cccccc><b> Time given </b></td>'.
                '<td bgcolor=#cccccc><b> Block grade </b></td>'.
              '</tr>';

            $even = true;
            $subeven = true;

            //order students by name
            uasort($infograder, function ($a, $b) {
                return strcmp($a->authorname, $b->authorname);
            });

            //filter students by firstname
            if(!empty($firstletter)){
                // $infograder = array_filter($infograder, function($a) use ($firstletter) {return $a[0]->authorname[0] == $firstletter;});
                $infograder = array_filter($infograder, function($a) use ($firstletter) {return $a->authorname[0] == $firstletter;});
             }

             //filter students by lastname
            if(!empty($lastletter)){
                 $infograder = array_filter($infograder, function($a) use ($lastletter) {$surname = explode(' ', $a->authorname)[1]; return $surname[0] == $lastletter;});
             }

             //pagination
             $total_infograders = count($infograder);
             $start = $currentpage * $perpage_small;

             if ($start > $total_infograders) {
                   $currentpage = 0;
                   $start = 0;
             }

             $infograder = array_slice($infograder, $start, $perpage_small, true);

            foreach ($infograder as $i => $value) {

                if($even){
                    $color = '#f2f2f2';
                } else {
                    $color = '#ffffff';
                }
                $even = !$even;

                $grader = $i;

                if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context, $grader, false)){

                    $grader_db = $DB->get_record('user', array('id' => $grader));

                    if(!empty($grader_db)){
                        $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);
                    }

                    $count = count($infograder[$i]->posts) + 1;

                    echo '<tr>'.'<td width="150" bgcolor='."$color".' rowspan='.$count.'>'.$grader_link.'</td>';

                    foreach ($infograder[$i]->posts as $key => $value) {
                        $postid = $key;
                        $grade = $infograder[$i]->posts[$key]->grade;
                        $feedback = $infograder[$i]->posts[$key]->feedback;
                        $time = userdate($infograder[$i]->posts[$key]->time, '%e/%m/%y');

                        $post_author = $DB->get_record('peerforum_posts', array('id' => $postid))->userid;
                        $author_db = $DB->get_record('user', array('id' => $post_author));
                        $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$author_db->id)), $author_db->firstname .' '. $author_db->lastname);

                        $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));
                        $postlink = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$postinfo->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $postinfo->subject);

                        $status_blocked = $DB->get_record('peerforum_blockedgrades', array('itemid' => $postid, 'userid' => $grader));

                        if(empty($status_blocked)){
                            $status = 0; //not blocked
                        } else {
                            $status = 1; //blocked
                        }


                        $url = new moodle_url('/peergrading/block_grade.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
                        $baseurl = $url.'&'.'grader='.$grader.'&'.'postid='.$postid.'&'.'status='.$status;

                        if($status == 1){
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('unblock', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        } else {
                            $button_block = '<form action="' . $baseurl . '" method="post">'.
                                        '<div class="buttons"><input style="margin-top:1px;margin-bottom:1px;font-size: 13px;" type="submit" id="block" name="block" value="'.get_string('block', 'block_peerblock').'"/>'.
                                        '</div></form>';
                        }

                        if($subeven){
                            $color = '#f2f2f2';
                        } else {
                            $color = '#ffffff';
                        }
                        $subeven = !$subeven;

                        if($status == 1){
                            $status_str =  '<font color="red">Blocked</font>' ;
                            $color = '#ffb3b3';
                        } else {
                            $status_str = '<font color="green">Unblocked</font>';
                        }

                        echo '<tr>'.'<td width="250" height="1" bgcolor='."$color".'>'.'(id:'.$postid.')'.'  '. $postlink .'</td>'.'<td width="150" height="1" bgcolor='."$color".'>'.$author_link.'</td>'.'<td width="50" height="1" bgcolor='."$color".'>'.$grade.'</td>'.'<td width="50" height="1" bgcolor='."$color".'>'.$feedback.'</td>'.'<td width="70" height="1" bgcolor='."$color".'><font size="2.5">'.$time.'</font></td>'.'<td width="50" height="1" bgcolor='."$color".'>'.$button_block.'</td>'.'</tr>';
                    }
                        echo '</tr>';
                }
            }
                echo '</table>';
        }
        //pagination
        echo '</br>';
        $pageurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'sifirst' => $firstletter, 'silast' => $lastletter));
        echo $OUTPUT->paging_bar($total_infograders, $currentpage, $perpage_small, $pageurl);

    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }

}

// View Peergrades
if($display == '3'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){
        $info = get_posts_grades($courseid);

        if(empty($info)){
            echo 'No information to display.';
            $total_views = 0;
        }

        if(!empty($info)){

            $peerforum_id = $DB->get_record_sql("SELECT MIN(id) as id FROM mdl_peerforum");
            $peerforum_db = $DB->get_record('peerforum', array('id' => $peerforum_id->id));

            $peerforum_seeoutliers = $peerforum_db->seeoutliers;
            $peerforum_outlierdetection = $peerforum_db->outlierdetection;
            $peerforum_outdetectvalue = $peerforum_db->outdetectvalue;
            $peerforum_blockoutliers = $peerforum_db->blockoutliers;
            $peerforum_warningoutliers = $peerforum_db->warningoutliers;

            if(!isset($peerforum_seeoutliers)){
                $seeoutliers = 1;
            } else {
                $seeoutliers = $peerforum_seeoutliers;
            }

            if(!isset($peerforum_outlierdetection)){
                $outlierdetection = 'standard deviation';
            } else {
                $outlierdetection = $peerforum_outlierdetection;
            }

            if(!isset($peerforum_outdetectvalue)){
                $outdetectvalue = 1;
            } else {
                $outdetectvalue = $peerforum_outdetectvalue;
            }

            if(!isset($peerforum_blockoutliers)){
                $blockoutliers = 0;
            } else {
                $blockoutliers = $peerforum_blockoutliers;
            }

            if(!isset($peerforum_warningoutliers)){
                $warningoutliers = 0;
            } else {
                $warningoutliers = $peerforum_warningoutliers;
            }

            if($blockoutliers == 0){
                // update peergrades from blocked grades
                $all_outliers = $DB->get_records('peerforum_blockedgrades', array('isoutlier' => 1));
                foreach ($all_outliers as $out => $value) {
                    $new_data = new stdClass();
                    $new_data->contextid = $all_outliers[$out]->contextid;
                    $new_data->component = $all_outliers[$out]->component;
                    $new_data->peergradearea = $all_outliers[$out]->peergradearea;
                    $new_data->itemid = $all_outliers[$out]->itemid;
                    $new_data->scaleid = $all_outliers[$out]->scaleid;
                    $new_data->peergrade = $all_outliers[$out]->peergrade;
                    $new_data->userid = $all_outliers[$out]->userid;
                    $new_data->timecreated = $all_outliers[$out]->timecreated;
                    $new_data->timemodified = $all_outliers[$out]->timemodified;
                    $new_data->peergradescaleid = $all_outliers[$out]->peergradescaleid;
                    $new_data->peergraderid = $all_outliers[$out]->peergraderid;
                    $new_data->feedback = $all_outliers[$out]->feedback;

                    $DB->insert_record('peerforum_peergrade', $new_data);

                    $DB->delete_records('peerforum_blockedgrades', array('itemid' => $new_data->itemid, 'userid' => $new_data->userid));
                }
            }

            if(empty($viewpeergradesmode)){
                $viewpeergradesmode = VIEWPEERGRADES_MODE_SEEALL;
            }

            $select = new single_select(new moodle_url("/peergrading/index.php", array('courseid' => $courseid, 'userid' => $userid, 'display' => $display, 'peerforum' => $peerforumid)), 'viewpeergradesmode', peerforum_get_view_peergrades_filters(), $viewpeergradesmode, null, "viewpeergradesmode");
            $select->set_label(get_string('displayviewpeergradesmode', 'peerforum'), array('class' => 'accesshide'));
            $select->class = "viewpeergradesmode";

            echo $OUTPUT->render($select);
        }

        if(!empty($info)){

            if($blockoutliers){
                echo '<tr><td> Outliers are automatically removed from final grades. Go to plugin settings to change this configuration.</td></tr>';
            }

            echo '<table class="managepeers"">'.
              '<tr">'.
                '<td bgcolor=#cccccc><b> Post peer graded </b></td>'.
                '<td bgcolor=#cccccc><b> Post author </b></td>'.
                '<td bgcolor=#cccccc><b> Grader </b></td>'.
                '<td bgcolor=#cccccc><b> Grade </b></td>'.
                '<td bgcolor=#cccccc><b> Feedback </b></td>'.
              '</tr>';

            $even = true;

            krsort($info);

            //pagination
            $total_views = count($info);
            $start = $currentpage * $perpage_big;

            if ($start > $total_views) {
                  $currentpage = 0;
                  $start = 0;
            }

            $info = array_slice($info, $start, $perpage_big, true);

            foreach ($info as $postid => $value) {
                if($even){
                    $color = '#f2f2f2';
                    $even = !$even;
                } else {
                    $color = '#ffffff';
                    $even = !$even;
                }

                $postinfo = $DB->get_record('peerforum_posts', array('id' => $postid));
                $postlink = html_writer::link(new moodle_url('/mod/peerforum/discuss.php?d='.$postinfo->discussion.'#p'.$postid, array('userid' => $userid, 'courseid' => $courseid)), $postinfo->subject);

                $author_db = $DB->get_record('user', array('id' => $postinfo->userid));

                $author_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$postinfo->userid)), $author_db->firstname .' '. $author_db->lastname);

                $count_peergrades = count($info[$postid]);

                $count = $count_peergrades + 1;

                $all_grades = array();
                $all_grades_sd = array();

                for($k = 0; $k < $count_peergrades; $k++){
                    if(isset($info[$postid][$k]->peergrade)){
                        $grade = $info[$postid][$k]->peergrade;
                        array_push($all_grades_sd, $grade);
                    }
                }

                if($seeoutliers){
                    $mean = average($all_grades_sd);
                    $mode = mode($all_grades_sd);

                    if($outlierdetection == 'standard deviation'){
                        $sd = standart_deviation($all_grades_sd);

                        if($outdetectvalue > 0){
                            $sd = $sd * $outdetectvalue;
                        }

                        $min_interval = $mean - $sd;
                        $max_interval = $mean + $sd;
                    }
                    if($outlierdetection == 'grade points'){

                        $min_interval = $mean - $outdetectvalue;
                        $max_interval = $mean + $outdetectvalue;

                    }

                    //warning interval with threshold
                    if($warningoutliers != 0){
                        $min_warning = $mean - $warningoutliers;
                        $max_warning = $mean + $warningoutliers;
                    }
                }

                $rowtoprint = array();

                for($i = 0; $i < $count_peergrades; $i++){
                    $grader = $info[$postid][$i]->user;
                    $grader_db = $DB->get_record('user', array('id' => $grader));
                    $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);

                    if(isset($info[$postid][$i]->peergrade)){
                        $grade = $info[$postid][$i]->peergrade;
                        array_push($all_grades, $grade);
                    } else {
                        $grade = '-';
                    }
                    if(isset($info[$postid][$i]->feedback)){
                        $feedback = $info[$postid][$i]->feedback;
                    } else {
                        $feedback = '-';
                    }

                    if($grade >= $min_interval && $grade <= $max_interval){
                        //not outlier (green)
                        if($grade == $mode){
                            $color_grade = '<font color="green">'.$grade.'</font>';
                            $outlier_type = 0;
                        } else if ($grade != $mode){
                            // warnign outlier
                            if($warningoutliers != 0){
                                if($grade >= $min_warning && $grade <= $max_warning){
                                    // warnign inside threshold interval (green)
                                    $color_grade = '<font color="green">'.$grade.'</font>';
                                    $outlier_type = 0;
                                } else {
                                    //warning outside threshold interval (yellow)
                                    $color_grade = '<font color="#cc9900">'.$grade.'</font>';
                                    $outlier_type = 1;
                                }
                            } else {
                                //warning outside threshold interval (yellow)
                                $color_grade = '<font color="#cc9900">'.$grade.'</font>';
                                $outlier_type = 1;
                            }
                        }
                    } else {
                        //outlier (red)
                        $color_grade = '<font color="red">'.$grade.'</font>';
                        $outlier_type = 2;

                        if($blockoutliers){
                            $peergrade_out = $DB->get_record('peerforum_peergrade', array('itemid' => $postid, 'userid' => $grader));

                            //insert into blocked grades
                            $data_out = new stdClass();
                            $data_out->contextid = $peergrade_out->contextid;
                            $data_out->component = $peergrade_out->component;
                            $data_out->peergradearea = $peergrade_out->peergradearea;
                            $data_out->itemid = $peergrade_out->itemid;
                            $data_out->scaleid = $peergrade_out->scaleid;
                            $data_out->peergrade = $peergrade_out->peergrade;
                            $data_out->userid = $peergrade_out->userid;
                            $data_out->timecreated = $peergrade_out->timecreated;
                            $data_out->timemodified = $peergrade_out->timemodified;
                            $data_out->peergradescaleid = $peergrade_out->peergradescaleid;
                            $data_out->peergraderid = $peergrade_out->peergraderid;
                            $data_out->feedback = $peergrade_out->feedback;
                            $data_out->isoutlier = 1;

                            $DB->insert_record('peerforum_blockedgrades', $data_out);

                            //remove from peergrades DB
                            $DB->delete_records('peerforum_peergrade', array('itemid' => $postid, 'userid' => $grader));
                        }
                    }
                        $row = new stdClass();
                        $row->outlier = $outlier_type;
                        $row->string = '<tr>'.'<td bgcolor='."$color".'>'. $grader_link .'</td>'.'<td bgcolor='."$color".'>'.$color_grade.'</td>'.'<td bgcolor='."$color".'>'.$feedback.'</td>'.'</tr>';
                        array_push($rowtoprint, $row);
                }

                $notprint = 0;

                if($viewpeergradesmode == VIEWPEERGRADES_MODE_SEEALL){
                    //print post link
                    echo '<tr>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.'(id:'.$postid.') '.$postlink.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$author_link.'</td>';
                    //print depending of option (outlier type) selected
                    foreach ($rowtoprint as $key => $value) {
                        echo $rowtoprint[$key]->string;
                    }
                    $notprint = 0;
                } else if ($viewpeergradesmode == VIEWPEERGRADES_MODE_SEEWARNINGS){
                    $iswarning = 0;
                    foreach ($rowtoprint as $key => $value) {
                        if($rowtoprint[$key]->outlier == 1){
                            $iswarning = 1;
                            break;
                        }
                    }
                    if($iswarning == 1){
                        $notprint = 0;
                        //print post link
                        echo '<tr>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.'(id:'.$postid.') '.$postlink.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$author_link.'</td>';
                        foreach ($rowtoprint as $k => $value) {
                            if($rowtoprint[$k]->outlier == 1){
                                echo $rowtoprint[$k]->string;
                            }
                        }
                    } else {
                        $notprint = 1;
                    }
                } else if ($viewpeergradesmode == VIEWPEERGRADES_MODE_SEEOUTLIERS){
                    $isoutlier = 0;
                    foreach ($rowtoprint as $key => $value) {
                        if($rowtoprint[$key]->outlier == 2){
                            $isoutlier = 1;
                            break;
                        }
                    }
                    if($isoutlier == 1){
                        $notprint = 0;
                        //print post link
                        echo '<tr>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.'(id:'.$postid.') '.$postlink.'</td>'.'<td bgcolor='."$color".' rowspan='.$count.'>'.$author_link.'</td>';
                        foreach ($rowtoprint as $k => $value) {
                            if($rowtoprint[$k]->outlier == 2){
                                echo $rowtoprint[$k]->string;
                            }
                        }
                    } else {
                        $notprint = 1;
                    }
                }

                if(!$notprint){

                    $discussion = $DB->get_record('peerforum_discussions', array('id' => $postinfo->discussion));
                    $peer_forum = $DB->get_record('peerforum', array('id' => $discussion->peerforum));

                    $assessed = $peer_forum->peergradeassessed;

                    if($assessed == PEERGRADE_AGGREGATE_AVERAGE){
                        $assessed_mode = '<font color="grey">'.'Average'.'</font>';
                        $assessed_grade_original = array_sum($all_grades)/count($all_grades);
                        $assessed_grade = '<font color="grey">'.number_format((float)$assessed_grade_original, 2, ',', '').'</font>';


                    } else if ($assessed == PEERGRADE_AGGREGATE_COUNT){
                        $assessed_mode = '<font color="grey">'.'Count'.'</font>';
                        $assessed_grade = '<font color="grey">'.count($all_grades).'</font>';

                    } else if ($assessed == PEERGRADE_AGGREGATE_MAXIMUM){
                        $assessed_mode = '<font color="grey">'.'Maximum'.'</font>';
                        $assessed_grade = '<font color="grey">'.max($all_grades).'</font>';

                    } else if ($assessed == PEERGRADE_AGGREGATE_MINIMUM){
                        $assessed_mode = '<font color="grey">'.'Minimum'.'</font>';
                        $assessed_grade = '<font color="grey">'.min($all_grades).'</font>';

                    } else if ($assessed == PEERGRADE_AGGREGATE_SUM){
                        $assessed_mode = '<font color="grey">'.'Sum'.'</font>';
                        $assessed_grade = '<font color="grey">'.array_sum($all_grades).'</font>';
                    }

                    $line = '<font color="grey">'.'-'.'</font>';

                    echo '<tr><td style="font-weight:300; color:black" bgcolor='."$color".'>'. $assessed_mode .'</td><td bgcolor='."$color".'> '.$line.' </td><td style="font-weight:300; color:black" bgcolor='."$color".'>'.$assessed_grade.'</td><td bgcolor='."$color".'> - </td></tr>';
                }
                echo '</tr>';
            }
            if($notprint){
                echo 'No information to display.';
            }
            echo '</table>';
        }

        //pagination
        echo '</br>';
        $pageurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display, 'viewpeergradesmode' => $viewpeergradesmode));
        echo $OUTPUT->paging_bar($total_views, $currentpage, $perpage_big, $pageurl);

    } else {
        print_error('sectionpermissiondenied', 'peergrade');
    }
}

//Manage conflicts
if($display == '4'){
    if(has_capability('mod/peerforum:viewpanelpeergrades', $context)){
        $url = new moodle_url('/peergrading/importgroups.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));
        $baseurl = $url.'&'.'course='.$courseid;

        $select_file = '<form action="' . $baseurl . '" method="post" enctype="multipart/form-data">'.
                    'Select groups file: <input type="file" name="filegroups" accept=".csv">'. '<br>'.
                    '<div class="buttons"><input type="submit" style="margin-left:3px;margin-top:10px;font-size: 13px;" id="filegroupssubmit" name="filegroupssubmit" value="'.get_string('uploadfile', 'block_peerblock').'"/>'.'<br>'.
                    '<span style="color:grey">File extension: .csv</span>'.'<br>'.
                    '<span style="color:grey">File format: each file line represents a group of students (first and last name) separated by a comma</span>'. '<br>'.
                    '<span style="color:grey">File example:</span>'.'<br>'.
                    '<span style="color:grey">student#1,student#2</span>'.'<br>'.
                    '<span style="color:grey">student#3,student#4,student#5</span>'.'<br>'.
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
                $currentgroupsoptions .= '<option disabled style="font-weight:bold; color:black" value="'.$currentgroups[$id]->groupid.'.">'.'Group #'. $currentgroups[$id]->groupid.'</option>';

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

        $conflicts = $DB->get_records('peerforum_peergrade_conflict', array('courseid' => $courseid));

        $conflictssoptions = '';

        if(!empty($conflicts)){
            $num_conflicts = count($conflicts);
            $countconflict=1;

            $last_conflict = max(array_keys($conflicts));

            foreach ($conflicts as $id => $value) {
                if($id == $last_conflict){
                    $conflictssoptions .= '<option selected style="font-weight:bold; color:black" value="conflict:'.$conflicts[$id]->id.'.">'.'Conflict #'. $countconflict.'</option>';
                }else {
                    $conflictssoptions .= '<option style="font-weight:bold; color:black" value="conflict:'.$conflicts[$id]->id.'.">'.'Conflict #'. $countconflict.'</option>';
                }

                $students_id = explode(';', $conflicts[$id]->idstudents);
                $students_id = array_filter($students_id);

                foreach ($students_id as $key => $value) {
                    $std_id = $students_id[$key];

                    $val = $conflicts[$id]->id.'|'. $std_id;

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
                        $conflictssoptions .= '<option value="student:'.$val.'.">'.format_string($std_name).' (group #'.$std_group.')'.'</option>';
                    }
                }
                $countconflict = $countconflict + 1;
            }
        } else {
            $num_conflicts = 0;
        }

        $url_conflicts = new moodle_url('/peergrading/manageconflicts.php', array('userid' => $userid, 'courseid' => $courseid, 'display' => $display));

        ?>
        <div id="addmembersform">
            <form id="assignform" method="post" action=" <?php echo $url_conflicts ?>">
            <div>
            <input type="hidden" name="sesskey" value="<?php p(sesskey()); ?>" />
            <table summary="" class="generaltable generalbox groupmanagementtable boxaligncenter">
            <tr>
              <td id="existingcell">
                  <br>
                  <input name="addall" id="addall" type="submit"
                            value="<?php echo get_string('addall', 'peerforum'); ?>"
                            title="<?php print_string('addall', 'peerforum'); ?>"
                            onclick="return confirm('This action will overwrite the existing information. Continue?')"
                            /><br><br>
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
                    <br><br><br>
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
                            onclick="return confirm('Are you sure you want to remove all conflicts?')"
                            /><br>
                <input name="addconflict" id="addconflict" type="submit"
                       value="<?php echo get_string('addconflict', 'peerforum').'&nbsp;'; ?>"
                       title="<?php print_string('addconflict', 'peerforum'); ?>"
                       />
               <input name="removeconflict" id="removeconflict" type="submit"
                          value="<?php echo '&nbsp;'.get_string('removeconflict', 'peerforum'); ?>"
                          title="<?php print_string('removeconflict', 'peerforum'); ?>"
                          onclick="return confirm('Are you sure you want to remove conflit?')"
                          /><br>

                  <label for="conflictselect"><?php print_string('numconflicts', 'peerforum', $num_conflicts); ?></label>
                  <div class="userselector" id="conflictselect_wrapper">
                  <select name="conflictselect[]" size="20" id="conflictselect" multiple="multiple"
                          onfocus="document.getElementById('assignform').add.disabled=true;
                                   document.getElementById('assignform').remove.disabled=false;
                                   document.getElementById('assignform').conflictselect.selectedIndex=-1;">
                 <?php echo $conflictssoptions ?>
                 </select>
                  </div>
               </td>
            </tr>
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

    $userposts_expired = peerforum_get_user_posts_expired($USER->id, $courseid);
    $userposts_peergraded = peerforum_get_user_posts_peergraded($USER->id, $courseid);

        if(!empty($userposts_expired)){
            for($i = 0; $i < count($userposts_expired); $i++){
                    if(!in_array($userposts_expired[$i], $userposts_peergraded)){
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
                    } else {
                        echo 'No posts whose peer grading time has expired.';
                    }
                //}
            }
        }
        if(empty($userposts_expired)){
            echo 'No posts whose peer grading time has expired.';
        }
}

// view graders statistics
if($display == '7'){
    $info_peers = $DB->get_records('peerforum_peergrade_users', array('courseid' => $courseid));

    $info_peers = order_students_by_name($info_peers);

    if(empty($info_peers)){
        echo 'No information to display.';
        $total_peers = 0;
    }

    if(!empty($info_peers)){
        echo '<table class="managepeers"">'.
          '<tr">'.
            '<td bgcolor=#cccccc><b> Student </b></td>'.
            '<td bgcolor=#cccccc><b> #Posts to grade </b></td>'.
            '<td bgcolor=#cccccc><b> #Posts graded </b></td>'.
            '<td bgcolor=#cccccc><b> #Posts blocked </b></td>'.
            '<td bgcolor=#cccccc><b> #Posts expired </b></td>'.
            '<td bgcolor=#cccccc><b> User blocked </b></td>'.
          '</tr>';

        $even = true;

        //pagination
        $total_peers = count($info_peers);
        $start = $currentpage * $perpage_big;

        if ($start > $total_peers) {
              $currentpage = 0;
              $start = 0;
        }

        $info_peers = array_slice($info_peers, $start, $perpage_big, true);

        foreach ($info_peers as $key => $value) {
            $iduser = $info_peers[$key]->iduser;

            $grader_db = $DB->get_record('user', array('id' => $iduser));
            $grader_link = html_writer::link(new moodle_url('/user/view.php', array('id'=>$grader_db->id)), $grader_db->firstname .' '. $grader_db->lastname);

            $poststopeergrade = explode(';', $info_peers[$key]->poststopeergrade);
            $poststopeergrade = array_filter($poststopeergrade);
            $numtopeergrade = count($poststopeergrade);

            $postspeergradedone = explode(';', $info_peers[$key]->postspeergradedone);
            $postspeergradedone = array_filter($postspeergradedone);
            $numpeergraded = count($postspeergradedone);

            $postsblocked = explode(';', $info_peers[$key]->postsblocked);
            $postsblocked = array_filter($postsblocked);
            $numblocked = count($postsblocked);

            $postsexpired = explode(';', $info_peers[$key]->postsexpired);
            $postsexpired = array_filter($postsexpired);
            $numexpired = count($postsexpired);

            $userblocked = $info_peers[$key]->userblocked;

            if($even){
                $color = '#f2f2f2';
            } else {
                $color = '#ffffff';
            }
            $even = !$even;

            if($userblocked){
                $userblocked = '<font color="red">Yes</font>' ;
                $color = '#ffb3b3';

            } else {
                //$userblocked = 'No';
                $userblocked = '<font color="green">No</font>' ;
            }

            echo '<tr>'.'<td width="150" bgcolor='."$color".'>'.$grader_link .'</td>'.'<td width="50" bgcolor='."$color".'>'.$numtopeergrade.'</td>'.'<td width="50" bgcolor='."$color".'>'.$numpeergraded.'</td>'.'<td width="50" bgcolor='."$color".'>'.$numblocked.'</td>'.'<td width="50" bgcolor='."$color".'>'.$numexpired.'</td>'.'<td width="50" bgcolor='."$color".'>'.$userblocked.'</td>'.'</tr>';
        }

    echo '</table>';
    }
    //pagination
    echo '</br>';
    $pageurl = new moodle_url('/peergrading/index.php', array('userid' => $userid,'courseid' => $courseid, 'display' => $display));
    echo $OUTPUT->paging_bar($total_peers, $currentpage, $perpage_big, $pageurl);
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
