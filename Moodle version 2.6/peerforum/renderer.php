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
 * This file contains a custom renderer class used by the peerforum module.
 *
 * @package   mod_peerforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the peerforum module.
 *
 * @package   mod_peerforum
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class mod_peerforum_renderer extends plugin_renderer_base {

    /**
     * Returns the navigation to the previous and next discussion.
     *
     * @param mixed $prev Previous discussion record, or false.
     * @param mixed $next Next discussion record, or false.
     * @return string The output.
     */
    public function neighbouring_discussion_navigation($prev, $next) {
        $html = '';
        if ($prev || $next) {
            $html .= html_writer::start_tag('div', array('class' => 'discussion-nav clearfix'));
            $html .= html_writer::start_tag('ul');
            if ($prev) {
                $url = new moodle_url('/mod/peerforum/discuss.php', array('d' => $prev->id));
                $html .= html_writer::start_tag('li', array('class' => 'prev-discussion'));
                $html .= html_writer::link($url, format_string($prev->name),
                    array('aria-label' => get_string('prevdiscussiona', 'mod_peerforum', format_string($prev->name))));
                $html .= html_writer::end_tag('li');
            }
            if ($next) {
                $url = new moodle_url('/mod/peerforum/discuss.php', array('d' => $next->id));
                $html .= html_writer::start_tag('li', array('class' => 'next-discussion'));
                $html .= html_writer::link($url, format_string($next->name),
                    array('aria-label' => get_string('nextdiscussiona', 'mod_peerforum', format_string($next->name))));
                $html .= html_writer::end_tag('li');
            }
            $html .= html_writer::end_tag('ul');
            $html .= html_writer::end_tag('div');
        }
        return $html;
    }

    /**
     * This method is used to generate HTML for a subscriber selection form that
     * uses two user_selector controls
     *
     * @param user_selector_base $existinguc
     * @param user_selector_base $potentialuc
     * @return string
     */
    public function subscriber_selection_form(user_selector_base $existinguc, user_selector_base $potentialuc) {
        $output = '';
        $formattributes = array();
        $formattributes['id'] = 'subscriberform';
        $formattributes['action'] = '';
        $formattributes['method'] = 'post';
        $output .= html_writer::start_tag('form', $formattributes);
        $output .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey()));

        $existingcell = new html_table_cell();
        $existingcell->text = $existinguc->display(true);
        $existingcell->attributes['class'] = 'existing';
        $actioncell = new html_table_cell();
        $actioncell->text  = html_writer::start_tag('div', array());
        $actioncell->text .= html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'subscribe', 'value'=>$this->page->theme->larrow.' '.get_string('add'), 'class'=>'actionbutton'));
        $actioncell->text .= html_writer::empty_tag('br', array());
        $actioncell->text .= html_writer::empty_tag('input', array('type'=>'submit', 'name'=>'unsubscribe', 'value'=>$this->page->theme->rarrow.' '.get_string('remove'), 'class'=>'actionbutton'));
        $actioncell->text .= html_writer::end_tag('div', array());
        $actioncell->attributes['class'] = 'actions';
        $potentialcell = new html_table_cell();
        $potentialcell->text = $potentialuc->display(true);
        $potentialcell->attributes['class'] = 'potential';

        $table = new html_table();
        $table->attributes['class'] = 'subscribertable boxaligncenter';
        $table->data = array(new html_table_row(array($existingcell, $actioncell, $potentialcell)));
        $output .= html_writer::table($table);

        $output .= html_writer::end_tag('form');
        return $output;
    }

    /**
     * This function generates HTML to display a subscriber overview, primarily used on
     * the subscribers page if editing was turned off
     *
     * @param array $users
     * @param object $peerforum
     * @param object $course
     * @return string
     */
    public function subscriber_overview($users, $peerforum , $course) {
        $output = '';
        $modinfo = get_fast_modinfo($course);
        if (!$users || !is_array($users) || count($users)===0) {
            $output .= $this->output->heading(get_string("nosubscribers", "peerforum"));
        } else if (!isset($modinfo->instances['peerforum'][$peerforum->id])) {
            $output .= $this->output->heading(get_string("invalidmodule", "error"));
        } else {
            $cm = $modinfo->instances['peerforum'][$peerforum->id];
            $canviewemail = in_array('email', get_extra_user_fields(context_module::instance($cm->id)));
            $strparams = new stdclass();
            $strparams->name = format_string($peerforum->name);
            $strparams->count = count($users);
            $output .= $this->output->heading(get_string("subscriberstowithcount", "peerforum", $strparams));
            $table = new html_table();
            $table->cellpadding = 5;
            $table->cellspacing = 5;
            $table->tablealign = 'center';
            $table->data = array();
            foreach ($users as $user) {
                $info = array($this->output->user_picture($user, array('courseid'=>$course->id)), fullname($user));
                if ($canviewemail) {
                    array_push($info, $user->email);
                }
                $table->data[] = $info;
            }
            $output .= html_writer::table($table);
        }
        return $output;
    }

    /**
     * This is used to display a control containing all of the subscribed users so that
     * it can be searched
     *
     * @param user_selector_base $existingusers
     * @return string
     */
    public function subscribed_users(user_selector_base $existingusers) {
        $output  = $this->output->box_start('subscriberdiv boxaligncenter');
        $output .= html_writer::tag('p', get_string('forcesubscribed', 'peerforum'));
        $output .= $existingusers->display(true);
        $output .= $this->output->box_end();
        return $output;
    }

    /**
     * Generate the HTML for an icon to be displayed beside the subject of a timed discussion.
     *
     * @param object $discussion
     * @param bool $visiblenow Indicicates that the discussion is currently
     * visible to all users.
     * @return string
     */
    public function timed_discussion_tooltip($discussion, $visiblenow) {
        $dates = array();
        if ($discussion->timestart) {
            $dates[] = get_string('displaystart', 'mod_peerforum').': '.userdate($discussion->timestart);
        }
        if ($discussion->timeend) {
            $dates[] = get_string('displayend', 'mod_peerforum').': '.userdate($discussion->timeend);
        }

        $str = $visiblenow ? 'timedvisible' : 'timedhidden';
        $dates[] = get_string($str, 'mod_peerforum');

        $tooltip = implode("\n", $dates);
        return $this->pix_icon('i/calendar', $tooltip, 'moodle', array('class' => 'smallicon timedpost'));
    }

    /**
     * Display a peerforum post in the relevant context.
     *
     * @param \mod_peerforum\output\peerforum_post $post The post to display.
     * @return string
     */
    public function render_peerforum_post_email(\mod_peerforum\output\peerforum_post_email $post) {
        $data = $post->export_for_template($this);
        return $this->render_from_template('mod_peerforum/' . $this->peerforum_post_template(), $data);
    }

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function peerforum_post_template() {
        return 'peerforum_post';
    }

    public function render_peergrade(peergrade $peergrade) {
        global $CFG, $USER, $DB, $PAGE, $COURSE, $OUTPUT;

        $systemcontext = context_system::instance();

        if (empty($peergrade->settings->peergradescale->courseid) and $coursecontext = $peergrade->context->get_course_context(false)) {
            $courseid = $coursecontext->instanceid;
        } else {
            $courseid = $peergrade->settings->peergradescale->courseid;
        }

        $peerforum = $DB->get_record('peerforum', array('id'=>$peergrade->peerforum));

        $final_grade_mode = $peerforum->finalgrademode;

        if (user_has_role_assignment($USER->id, 5) ) {
            $isstudent_login = true;
        } else {
            $isstudent_login = false;
        }


        if ($peergrade->settings->aggregationmethod == PEERGRADE_AGGREGATE_NONE) {
            return null;//peergrades are turned off
        }

        $peergrademanager = new peergrade_manager();
        // Initialise the JavaScript so peergrades can be done by AJAX.
        $peergrademanager->initialise_peergrade_javascript($PAGE);

        $strpeergrade = get_string("peergrade", "peerforum");
        $peergradehtml = ''; //the string we'll return


        // get 'edit' from url
        $actual_url = $_SERVER['REQUEST_URI'];
        $values = parse_url($actual_url, PHP_URL_QUERY);
        $getvalues = explode('&', $values);


        $editpostid = -1;

        foreach($getvalues as $i => $values){
            $val = explode('=', $getvalues[$i]);
            if($val[0] == 'editpostid'){
                $editpostid = $val[1];
            }
        }

        //get 'display' from url
        $display = '2';
        foreach($getvalues as $i => $values){
            if($getvalues[$i] != 'display=1' && $getvalues[$i] != 'display=2'){
                continue;
            } else {
                if($getvalues[$i] == 'display=1'){
                    $display = '1';
                }
                if($getvalues[$i] == 'display=2'){
                    $display = '2';

                }
            }
        }

        $user_id = $DB->get_record('peerforum_posts', array('id' => $peergrade->itemid))->userid;

        if (user_has_role_assignment($user_id ,5)  ) {
            $isstudent = true;
        } else {
            $isstudent = false;
        }


        if($isstudent){

            $already_peergraded = $peergrade->post_already_peergraded($peergrade->itemid, $USER->id);

            $post_time = $peergrade->verify_post_expired($peergrade->itemid, $peerforum, $peergrade);

            $post_expired = $post_time->post_expired;
            $time_interval = $post_time->time_interval;
            $time_current = $post_time->time_current;


            if($post_expired){
                $peergrade->update_post_expired($peergrade->itemid, $USER->id, $courseid, $peerforum);
            }


            if(!$post_expired){

            // permissions check - can they view the aggregate?
                if ($peergrade->user_can_view_aggregate()) {

                    //link notas
                    $aggregatelabel = $peergrademanager->get_aggregate_label($peergrade->settings->aggregationmethod);
                    $aggregatestr   = $peergrade->get_aggregate_string();


                    $aggregatehtml = html_writer::tag('span', $aggregatestr, array('id' => 'peergradeaggregate'.$peergrade->itemid, 'class' => 'peergradeaggregate')).' ';
                    if ($peergrade->count > 0) {
                        $countstr = "({$peergrade->count})";
                    } else {
                        $countstr = '-';
                    }

                    $aggregatehtml .= html_writer::tag('span', $countstr, array('id'=>"peergradecount{$peergrade->itemid}", 'class' => 'peergradecount')).' ';

                    $peergradehtml .= html_writer::tag('span', $aggregatelabel, array('class'=>'peergrade-aggregate-label'));
                    if ($peergrade->settings->permissions->viewall && $peergrade->settings->pluginpermissions->viewall) {

                        $nonpopuplink = $peergrade->get_view_peergrades_url();
                        $popuplink = $peergrade->get_view_peergrades_url(true);

                        $action = new popup_action('click', $popuplink, 'peergrades', array('height' => 400, 'width' => 600));
                        $peergradehtml .= $this->action_link($nonpopuplink, $aggregatehtml, $action);
                    } else {
                        $peergradehtml .= $aggregatehtml;
                    }
                }
        }
            $formstart = null;
            // if the item doesn't belong to the current user, the user has permission to peergrade
            // and we're within the assessable period
                $peergradeurl = $peergrade->get_peergrade_url(null, $peergrade->returnurl);

                $enablefeedback = $peerforum->enablefeedback;


            //start the peergrade form
            $formattrs = array(
            'id'     => "postpeergrade{$peergrade->itemid}",
            'class'  => 'postpeergradeform',
            'method' => 'post',
            'action' => $peergradeurl->out_omit_querystring()
            );

            $peergrade_end = $peergrade->verify_end_peergrade_post($peergrade->itemid, $peerforum);


            if((time() - $peergrade->itemtimecreated) < $CFG->maxeditingtime){
                $time_to_edit = 1;
            } else {
                $time_to_edit = 0;

            }


            if(!$post_expired && !$peergrade_end){

                if ($peergrade->user_can_peergrade()) {

                    //Verify if user can peergrade this post
                    $can_peergrade = $peergrade->can_peergrade_this_post($USER->id,$peergrade->itemid, $courseid);

                    /*---------------------------------*/
                    // PEERGRADE POST//

                    if($can_peergrade || has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){

        /*FORM1*/       $formstart  = html_writer::start_tag('form', $formattrs);

        /*DIV1.1*/        $formstart .= html_writer::start_tag('div', array('class' => 'peergradeform'));

                            $inputs = $peergradeurl->params();

                            // add the hidden inputs
                            foreach ($inputs as $name => $value) {
                                $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                                $formstart .= html_writer::empty_tag('input', $attributes);
                            }

                            if (empty($peergradehtml)) {
                                $peergradehtml .= $strpeergrade.': ';
                            }
                                $peergradehtml = $formstart.$peergradehtml;

                            if($USER->id != $peergrade->itemuserid){
                                $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($USER->id, $peergrade->itemid, $courseid);
                            }
                            else {
                                $already_peergraded_by_user = 0;
                            }


                            if((!$already_peergraded_by_user && $editpostid == -2) || ($already_peergraded_by_user && $editpostid == -2) || (!$already_peergraded_by_user && $editpostid == -1) || ($already_peergraded_by_user && $editpostid == $peergrade->itemid)){

                                $peergradescalearray = array(PEERGRADE_UNSET_PEERGRADE => $strpeergrade.'...') + $peergrade->settings->peergradescale->peergradescaleitems;

                                $peergradescaleattrs = array('class'=>'postpeergrademenu peergradeinput','id'=>'menupeergrade'.$peergrade->itemid);


                                $peergradehtml .= html_writer::label($peergrade->peergrade, 'menupeergrade'.$peergrade->itemid, false, array('class' => 'accesshide'));

                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                if(!has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
                                    $time_left = $time_interval->d;
                                } else {
                                    $time_left = '-';
                                }

                                if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
                                    $user_blocked = 0;
                                    $is_exclusive = 0;
                                } else {
                                    $user_blocked = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $USER->id))->userblocked;
                                    $is_exclusive = $peergrade->verify_exclusivity($peergrade->itemuserid, $USER->id, $courseid);
                                }

                                if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){

                                    if(!$user_blocked && !$is_exclusive){

                                        //Time left to peergrade
                                        $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                        $peergradehtml .= html_writer::tag('span', "Time left to peergrade: ".$time_left." day(s)", array('style'=> 'color: #ff6666;'));//color: #6699ff;


                                        $peergradehtml .= html_writer::tag('hr', ''); // Should produce <hr />
                                    }
                                }

                                // select a grade
                                if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){
                                    if((!$user_blocked && !$is_exclusive)){
                                        $peergradehtml .= html_writer::tag('span', "Select a grade: ", array('style'=> 'color: black;'));//color: #6699ff;
                                        $peergradehtml .= html_writer::select($peergradescalearray, 'peergrade', $peergrade->peergrade, false, $peergradescaleattrs);
                                    }
                                }

                    }
        /*DIV1.1*/        $peergradehtml .= html_writer::end_tag('div');

                        /*-----------------------------*/

                    if((!$already_peergraded_by_user && $editpostid == -2) || ($already_peergraded_by_user && $editpostid == -2) || (!$already_peergraded_by_user && $editpostid == -1) || ($already_peergraded_by_user && $editpostid == $peergrade->itemid)){

                            // WRITE FEEDBACK //

                            //the user can write feedback
                            if($enablefeedback){
                                if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){

                                    if(!$user_blocked && !$is_exclusive){

                                        $info = $DB->get_record('peerforum_peergrade', array('itemid' => $peergrade->itemid, 'userid' => $USER->id));

                                        $attributes = array('name' => "feedbacktext".$peergrade->itemid, 'form' => "postpeergrade{$peergrade->itemid}",'class' => 'feedbacktext','id'=> 'feedbacktext'.$peergrade->itemid, 'value' => 'null_feedback', 'wrap' => 'virtual', 'style' => 'height:100%; width:98%; max-width:98%;', 'rows' => '5', 'cols' => '5', 'placeholder' => get_string('writefeedback', 'peerforum'));

                                        if(!empty($info)){
                                            $feedback_given = $info->feedback;
                                            $peergradehtml .= html_writer::tag('textarea', $feedback_given ,$attributes);
                                        } else {
                                            $peergradehtml .= html_writer::tag('textarea', PEERGRADE_UNSET_FEEDBACK ,$attributes);
                                        }
                                    }
                                }

                                    if (!$peergrade->settings->peergradescale->isnumeric) {
                                    // If a global scale, try to find current course ID from the context
                                            $peergradehtml .= $this->help_icon_scale($courseid, $peergrade->settings->peergradescale);
                                    }

                            }
                            if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){

                                if(!$user_blocked && !$is_exclusive){

                                    //Feedback autor
                                    $anonymouspeergrader = $peerforum->remainanonymous;

                                    if($anonymouspeergrader){
                                        $grader = '[Your peergrade to this post is anonymous]';
                                    }else{
                                        $grader = '[Your peergrade to this post is public]';
                                    }

                                    //output submit button
                                    $attbutton = array('type' => 'submit', 'name' => 'postpeergrademenusubmit'.$peergrade->itemid, 'class' => 'postpeergrademenusubmit', 'id' => 'postpeergradesubmit'.$peergrade->itemid, 'value' => s(get_string('peergrade', 'peerforum')));
                                    $peergradehtml .= html_writer::empty_tag('input', $attbutton);

                                    $peergradehtml .= html_writer::tag('div', $grader, array('class' => 'author')); // Author.
                                }
                        }
                    }
            /*FORM1*/       $peergradehtml .= html_writer::end_tag('form');

                }
            }
        } else {

            if($peergrade_end){
                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                $peergradehtml .= html_writer::tag('span', "The activity of peer grading this post has ended.", array('style'=> 'color: #6699ff;'));

            } else if($post_expired){
                //No Time left to peergrade

                if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){

                    if($isstudent && ((!$user_blocked && !$is_exclusive))){
                        $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                        $peergradehtml .= html_writer::tag('span', "Your time to peergrade this post has expired", array('style'=> 'color: #6699ff;'));
                    }
                }
            }
        }

            /*---------------------------------*/

            //DISPLAY FEEDBACK
                //See if exists any feedback in the DB
                $all_feedback = $peergrade->exists_feedback($peergrade->itemid);

                if(!empty($all_feedback)){

                    $int_peergrader = 0;
                    foreach ($all_feedback as $i => $value){

                        $can_see_grades = $peergrade->can_see_grades($peerforum, $USER->id, $all_feedback[$i]->itemid);
                        $can_see_feedbacks = $peergrade->can_see_feedbacks($peerforum, $USER->id, $all_feedback[$i]->itemid);

                        $time_see_grades = $peergrade->time_to_see_grades($peerforum, $USER->id, $all_feedback[$i]->itemid);
                        $time_see_feedbacks = $peergrade->time_to_see_feedbacks($peerforum, $USER->id, $all_feedback[$i]->itemid);

                        $int_peergrader = $int_peergrader + 1;

                        $user = $all_feedback[$i]->userid;

                        if($can_see_grades && $time_see_grades || $can_see_feedbacks && $time_see_feedbacks){

                            if($USER->id != $peergrade->itemuserid){
                                $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($USER->id, $peergrade->itemid, $courseid);
                            }
                            else {
                                $already_peergraded_by_user = 0;
                            }


                        if(($already_peergraded_by_user && $editpostid == -1) || ($USER->id == $peergrade->itemuserid)){

                /*FORM1*/       $formstart  = html_writer::start_tag('form', $formattrs);
                /*DIV1.1*/        $formstart .= html_writer::start_tag('div', array('class' => 'peergradeform'));


                                $inputs = $peergradeurl->params();

                                // add the hidden inputs
                                foreach ($inputs as $name => $value) {
                                    $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                                    $peergradehtml .= html_writer::empty_tag('input', $attributes);
                                }

                /*FORM1*/       $peergradehtml  .= html_writer::start_tag('form', $formattrs);

                                $inputs = $peergradeurl->params();

                                // add the hidden inputs
                                foreach ($inputs as $name => $value) {
                                    $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                                    $peergradehtml .= html_writer::empty_tag('input', $attributes);
                                }


                /*DIV1*/        $peergradehtml .= html_writer::start_tag('div', array('class' => 'peergradeform_feedbacks'));
                /*DIV2*/        $peergradehtml .= html_writer::start_tag('div', array('class'=>'peerforumpostseefeedback clearfix',
                                                                            'role' => 'region',
                                                                            'aria-label' => get_string('givefeedback', 'peerforum')));

                                $feedbackstr = $all_feedback[$i]->feedback;

                                $input_feedback = new stdClass();
                                $input_feedback->text = $feedbackstr;


                                // add the feedback hidden inputs
                                $att = array('type' => 'hidden', 'class' => 'writtenfeedbacktext', 'id' => 'writtenfeedbacktext', 'value' => 'feedback_null');
                                $peergradehtml .= html_writer::tag('input', '',$att);

                /*DIV3*/        $peergradehtml .= html_writer::start_tag('div', array('class'=>'row header'));
                /*DIV4*/        $peergradehtml .= html_writer::start_tag('div', array('class'=>'topic'));

                                //Feedback autor
                                $anonymouspeergrader = $peerforum->remainanonymous;

                                $timemodified = $peergrade->get_time_modified($i);
                                $by = new stdClass();

                                if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
                                    $grader = $user;
                                    $user_obj = $DB->get_record('user', array('id' => $user));
                                    $peergradehtml .= $this->user_picture($user_obj);
                                    $by->name = html_writer::link(new moodle_url('/user/view.php', array('id'=>$user_obj->id)), $user_obj->firstname .' '. $user_obj->lastname);

                                } else {
                                    if($anonymouspeergrader){
                                         $grader = 'Grader '.$int_peergrader;
                                         $peergradehtml .= html_writer::empty_tag('img', array('src' => new moodle_url('/mod/peerforum/pix/user.png') , 'alt' => 'user_anonymous', 'style' => 'width:32px;height:32px;', 'class' => 'icon', 'align' => 'left'));
                                         $by->name =  $grader;

                                    }else{
                                         $grader = $user;
                                         $user_obj = $DB->get_record('user', array('id' => $user));
                                         $peergradehtml .= $this->user_picture($user_obj);
                                         $by->name = html_writer::link(new moodle_url('/user/view.php', array('id'=>$user_obj->id)), $user_obj->firstname .' '. $user_obj->lastname);
                                    }
                                }

                                $by->date = userdate($timemodified);
                                $peergradehtml .= html_writer::tag('div', get_string('bynameondate', 'peerforum', $by), array('class'=>'author',
                                                                           'role' => 'heading',
                                                                           'aria-level' => '2',
                                                                           'style' => 'position: relative;  top:8px; left:3px;'));

                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />


                                if($can_see_grades && $time_see_grades){
                    //CAN SEE 2            if(($cansee_grade != 0 && $USER->id == $user) || $USER->id == $peergrade->itemuserid || ($seeprofessor_grade == 1 && has_capability('mod/peerforum:viewallpeergrades', $PAGE->context))){
                                    $peergrade_given = $all_feedback[$i]->peergrade;
                                    $peergradehtml .= html_writer::tag('span', 'Peer grade: '.$peergrade_given, array('id'=>'outfeedback', 'class'=>'outfeedback'));
                    //CAN SEE 2            }
                                }
                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                if($enablefeedback){

            //CAN SEE 3            if(($cansee_feedback != 0 && $USER->id == $user) || $USER->id == $peergrade->itemuserid || ($seeprofessor_feed == 1 && has_capability('mod/peerforum:viewallpeergrades', $PAGE->context))){
                                    if($can_see_feedbacks && $time_see_feedbacks){
                                        $peergradehtml .= html_writer::tag('span', 'Feedback: '.$feedbackstr, array('id'=>'outfeedback', 'class'=>'outfeedback'));
                                    }
                //CAN SEE 3                    }
                                }
                /*DIV4*/        $peergradehtml .= html_writer::end_tag('div');

                /*DIV3*/        $peergradehtml .= html_writer::end_tag('div'); // row


                                //Edit peergrade
                                if($USER->id != $peergrade->itemuserid){
                                    $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($USER->id, $peergrade->itemid, $courseid);
                                }
                                else {
                                    $already_peergraded_by_user = 0;
                                }

                                $user_blocked = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $all_feedback[$i]->userid))->userblocked;
                                $is_exclusive = $peergrade->verify_exclusivity($peergrade->itemuserid, $USER->id, $courseid);

                                if(!$peergrade_end || $time_to_edit){
                                    if(($isstudent_login && $final_grade_mode != 1) || (!$isstudent_login && $final_grade_mode != 2) || $final_grade_mode == 3){

                                        if(!$user_blocked && !$is_exclusive){
                                            if(($already_peergraded_by_user && $display == '2')){
                                                if($USER->id == $all_feedback[$i]->userid){
                                                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                                    $editbutton = array('type' => 'submit', 'name' => 'editpeergrade'.$peergrade->itemid, 'class' => 'editpeergrade', 'id' => 'editpeergrade'.$peergrade->itemid,'value' => s(get_string('editpeergrade', 'peerforum')));
                                                    $peergradehtml .= html_writer::empty_tag('input', $editbutton);
                                                }
                                            }
                                        }
                                }
                            }

                /*DIV2*/        $peergradehtml .= html_writer::end_tag('div');
                /*DIV1*/        $peergradehtml .= html_writer::end_tag('div');

                /*FORM1*/       $peergradehtml .= html_writer::end_tag('form');


        }
    }
                }
        }
        /*FORM3*/

        if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
            if($isstudent){

                //Assign peer grader
                $studenturl = new moodle_url('/peergrade/assignstudent.php');
                $formattrs = array(
                'id'     => "poststudentmenu{$peergrade->itemid}",
                'class'  => 'poststudentform',
                'method' => 'post',
                'action' => $studenturl->out_omit_querystring()
                );

        /*FORM1*/   $peergradehtml  .= html_writer::start_tag('form', $formattrs);

                $inputs = $peergradeurl->params();

                // add the hidden inputs
                foreach ($inputs as $name => $value) {
                    $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                    $peergradehtml .= html_writer::empty_tag('input', $attributes);
                }


                $peers_topeergrade = get_post_peergraders($peergrade->itemid);
                $peers_name = get_students_name($peers_topeergrade);

                $students_assigned = get_students_can_be_assigned($courseid, $peergrade->itemid, $peergrade->itemuserid);
                $students = get_students_name($students_assigned);

                if(!empty($peers_topeergrade)){
                    $peergradehtml .= html_writer::tag('span', "Students assigned to peer grade this post: ", array('style'=> 'color: grey;'));//color: #6699ff;
                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />


                    $peers_assigned = array();
                    $peers_name = array_filter($peers_name);

                    $peers_name = implode('; ', $peers_name);

                    $peergradehtml .= html_writer::tag('span', $peers_name, array('style'=> 'color: grey;'));//color: #6699ff;
                } else {
                    $peergradehtml .= html_writer::tag('span', "No students assigned to peer grade this post.", array('style'=> 'color: grey;'));//color: #6699ff;
                }

                $selectstudentrandom = get_string('selectstudentrandom', 'peerforum');
                $studentsarray = array(UNSET_STUDENT => $selectstudentrandom) + $students;

                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                $peergradehtml .= html_writer::tag('span', "Select student to assign this post to peer grade: ", array('style'=> 'color: grey;'));//color: #6699ff;
                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />



                if(!empty($students_assigned)){
                    $studentattrs = array('class'=>'poststudentmenu studentinput','id'=>'menustudents'.$peergrade->itemid);
                    $peergradehtml .= html_writer::select($studentsarray, 'menustudents'.$peergrade->itemid, $studentsarray[UNSET_STUDENT], false, $studentattrs);
                    $assignpeer_button = array('type' => 'submit', 'name' => 'assignpeer'.$peergrade->itemid, 'class' => 'assignpeer', 'id' => 'assignpeer'.$peergrade->itemid,'value' => s(get_string('assignpeer', 'peerforum')));
                } else {
                    $studentattrs = array('class'=>'poststudentmenu studentinput','id'=>'menustudents'.$peergrade->itemid, 'disabled' => true);
                    $peergradehtml .= html_writer::select($studentsarray, 'menustudents'.$peergrade->itemid, $studentsarray[UNSET_STUDENT], false, $studentattrs);
                    $assignpeer_button = array('type' => 'submit' ,'disabled' => true, 'name' => 'assignpeer'.$peergrade->itemid, 'class' => 'assignpeer', 'id' => 'assignpeer'.$peergrade->itemid,'value' => s(get_string('assignpeer', 'peerforum')));
                }

                $peergradehtml .= html_writer::empty_tag('input', $assignpeer_button);

                $peergradehtml .= html_writer::end_tag('form');

                // Remove peer grader
                $studenturl_rmv = new moodle_url('/peergrade/removestudent.php');
                $formattrs_rmv = array(
                'id'     => "poststudentmenurmv{$peergrade->itemid}",
                'class'  => 'poststudentmenurmv',
                'method' => 'post',
                'action' => $studenturl_rmv->out_omit_querystring()
                );


    /*FORM1*/   $peergradehtml  .= html_writer::start_tag('form', $formattrs_rmv);

                $inputs_rmv = $peergradeurl->params();

                // add the hidden inputs
                foreach ($inputs_rmv as $name => $value) {
                    $attributes_rmv = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                    $peergradehtml .= html_writer::empty_tag('input', $attributes_rmv);
                }

                $students_assigned_rmv = get_students_assigned($courseid, $peergrade->itemid);
                $students_rmv = get_students_name($students_assigned_rmv);

                $selectstudentrandom_rmv = get_string('selectstudent', 'peerforum');
                $studentsarray_rmv = array(UNSET_STUDENT => $selectstudentrandom_rmv) + $students_rmv;

                $peergradehtml .= html_writer::tag('span', "Select student to remove this post to peer grade: ", array('style'=> 'color: grey;'));//color: #6699ff;
                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                if(!empty($students_assigned_rmv)){
                    $studentattrs_rmv = array('class'=>'poststudentmenu studentinput','id'=>'menustudents_rmv'.$peergrade->itemid);
                    $peergradehtml .= html_writer::select($studentsarray_rmv, 'menustudents_rmv'.$peergrade->itemid, $studentsarray_rmv[UNSET_STUDENT], false, $studentattrs_rmv);
                    $removepeer_button = array('type' => 'submit', 'name' => 'removepeer'.$peergrade->itemid, 'class' => 'removepeer', 'id' => 'removepeer'.$peergrade->itemid,'value' => s(get_string('removepeer', 'peerforum')));
                } else {
                    $studentattrs_rmv = array('class'=>'poststudentmenu studentinput','id'=>'menustudents_rmv'.$peergrade->itemid, 'disabled' => true);
                    $peergradehtml .= html_writer::select($studentsarray_rmv, 'menustudents_rmv'.$peergrade->itemid, $studentsarray_rmv[UNSET_STUDENT], false, $studentattrs_rmv);
                    $removepeer_button = array('type' => 'submit' ,'disabled' => true, 'name' => 'removepeer'.$peergrade->itemid, 'class' => 'removepeer', 'id' => 'removepeer'.$peergrade->itemid,'value' => s(get_string('removepeer', 'peerforum')));
                }

                $peergradehtml .= html_writer::empty_tag('input', $removepeer_button);

                $peergradehtml .= html_writer::end_tag('form');

            }
        }

        /*FORM3*/

        return $peergradehtml;
        }
    }
}
