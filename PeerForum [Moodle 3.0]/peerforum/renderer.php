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

 /**
  * Custom renderer classes (render_peergrade and render_ratingpeer) that extends the plugin_renderer_base and
  * is used by the peerforum module.
  *
  * @package    mod
  * @subpackage peerforum
  * @author     2016 Jessica Ribeiro <jessica.ribeiro@tecnico.ulisboa.pt>
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  **/

class mod_peerforum_renderer extends plugin_renderer_base {

//----------- New renders of PeerForum --------------//

/**
 * Produces the html that represents this ratingpeer in the UI
 *
 * @param ratingpeer $ratingpeer the page object on which this ratingpeer will appear
 * @return string
 */
public function render_ratingpeer(ratingpeer $ratingpeer) {
    global $CFG, $USER, $PAGE, $DB;

    if ($ratingpeer->settings->aggregationmethod == RATINGPEER_AGGREGATE_NONE) {
        return null;//ratingpeers are turned off
    }

    $ratingpeermanager = new ratingpeer_manager();
    // Initialise the JavaScript so ratingpeers can be done by AJAX.
    $ratingpeermanager->initialise_ratingpeer_javascript($PAGE);


    $strratepeer = get_string("ratepeer", "peerforum");
    $ratingpeerhtml = ''; //the string we'll return

    // variables
    $post_topeergrade = $ratingpeer->itemid;
    $peerforum_id = $ratingpeer->peerforum;
    $user_login = $USER->id;

    // Get info from database
    $peerforum = $DB->get_record('peerforum', array('id'=>$peerforum_id));


    //verify if peer grade can only be shown after rating is done
    $showafterpeergrade = $peerforum->showafterpeergrade;
    $canshowafterpeergrade = true;

    if($showafterpeergrade){
        //see if rating is enabled
        if($peerforum->peergradeassessed != 0){
            //verify if post was already rated by this user
            $already_peergraded = $DB->get_record('peerforum_peergrade', array('itemid' => $post_topeergrade, 'userid' => $user_login));
            if(!empty($already_peergraded)){
                $canshowafterpeergrade = true;
            } else {
                $canshowafterpeergrade = false;
            }
        }
    }

    $formstart = null;
    // if the item doesn't belong to the current user, the user has permission to ratepeer
    // and we're within the assessable period
    if ($ratingpeer->user_can_ratepeer()) {

        $ratepeerurl = $ratingpeer->get_ratepeer_url();
        $inputs = $ratepeerurl->params();

        //start the ratingpeer form
        $formattrs = array(
            'id'     => "postratingpeer{$ratingpeer->itemid}",
            'class'  => 'postratingpeerform',
            'method' => 'post',
            'action' => $ratepeerurl->out_omit_querystring()
        );
        $formstart  = html_writer::start_tag('form', $formattrs);
        $formstart .= html_writer::start_tag('div', array('class' => 'ratingpeerform'));

        // add the hidden inputs
        foreach ($inputs as $name => $value) {
            $attributes = array('type' => 'hidden', 'class' => 'ratingpeerinput', 'name' => $name, 'value' => $value);
            $formstart .= html_writer::empty_tag('input', $attributes);
        }

        if (empty($ratingpeerhtml)) {
            $ratingpeerhtml .= $strratepeer.': ';
        }
        $ratingpeerhtml = $formstart.$ratingpeerhtml;

        $scalearray = array(RATINGPEER_UNSET_RATINGPEER => $strratepeer.'...') + $ratingpeer->settings->scale->scaleitems;
        $scaleattrs = array('class'=>'postratingpeermenu ratingpeerinput','id'=>'menuratingpeer'.$ratingpeer->itemid);
        $ratingpeerhtml .= html_writer::label($ratingpeer->ratingpeer, 'menuratingpeer'.$ratingpeer->itemid, false, array('class' => 'accesshide'));
        $ratingpeerhtml .= html_writer::select($scalearray, 'ratingpeer', $ratingpeer->ratingpeer, false, $scaleattrs);

        //output submit button
        $ratingpeerhtml .= html_writer::start_tag('span', array('class'=>"ratingpeersubmit"));

        $attributes = array('type' => 'submit', 'class' => 'postratingpeermenusubmit', 'id' => 'postratingpeersubmit'.$ratingpeer->itemid, 'value' => s(get_string('ratepeer', 'peerforum')));
        $ratingpeerhtml .= html_writer::empty_tag('input', $attributes);

        if (!$ratingpeer->settings->scale->isnumeric) {
            // If a global scale, try to find current course ID from the context
            if (empty($ratingpeer->settings->scale->courseid) and $coursecontext = $ratingpeer->context->get_course_context(false)) {
                $courseid = $coursecontext->instanceid;
            } else {
                $courseid = $ratingpeer->settings->scale->courseid;
            }
            $ratingpeerhtml .= $this->help_icon_scale($courseid, $ratingpeer->settings->scale);
        }
        $ratingpeerhtml .= html_writer::end_tag('span');
        $ratingpeerhtml .= html_writer::end_tag('div');
        $ratingpeerhtml .= html_writer::end_tag('form');
    }

    if(has_capability('mod/peerforum:viewallratingpeer', $PAGE->context)){
        $isstudent = false;
    } else {
        $isstudent = true;
    }

    // permissions check - can they view the aggregate?
    //if ($ratingpeer->user_can_view_aggregate()) {
    if ($isstudent && $peerforum->showratings && $canshowafterpeergrade|| !$isstudent && $canshowafterpeergrade) {

        $aggregatelabel = $ratingpeermanager->get_aggregate_label($ratingpeer->settings->aggregationmethod);
        $aggregatestr   = $ratingpeer->get_aggregate_string();
        $aggregatehtml  = html_writer::tag('span', $aggregatestr, array('id' => 'ratingpeeraggregate'.$ratingpeer->itemid, 'class' => 'ratingpeeraggregate')).' ';
        if ($ratingpeer->count > 0) {
            $countstr = "({$ratingpeer->count})";
        } else {
            $countstr = '-';
        }
        $aggregatehtml .= html_writer::tag('span', $countstr, array('id'=>"ratingpeercount{$ratingpeer->itemid}", 'class' => 'ratingpeercount')).' ';

        $ratingpeerhtml .= html_writer::tag('span', $aggregatelabel, array('class'=>'ratingpeer-aggregate-label'));

        if ($ratingpeer->settings->permissions->viewall && $ratingpeer->settings->pluginpermissions->viewall) {

            $nonpopuplink = $ratingpeer->get_view_ratingpeers_url();
            $popuplink = $ratingpeer->get_view_ratingpeers_url(true);

            $action = new popup_action('click', $popuplink, 'ratingpeers', array('height' => 400, 'width' => 600));
            $ratingpeerhtml .= $this->action_link($nonpopuplink, $aggregatehtml, $action);

        } else {

            $ratingpeerhtml .= $aggregatehtml;

        }
    }
    return $ratingpeerhtml;
}

/**
 * Produces the html that represents this peergrade in the UI
 *
 * @param peergrade $peergrade the page object on which this ratingpeer will appear
 * @return string
 */
public function render_peergrade(peergrade $peergrade) {
        global $CFG, $USER, $DB, $PAGE, $COURSE, $OUTPUT;

        $expired_post = true;

        $systemcontext = context_system::instance();

        if (empty($peergrade->settings->peergradescale->courseid) and $coursecontext = $peergrade->context->get_course_context(false)) {
            $courseid = $coursecontext->instanceid;
        } else {
            $courseid = $peergrade->settings->peergradescale->courseid;
        }

        // variables
        $post_topeergrade = $peergrade->itemid;
        $peerforum_id = $peergrade->peerforum;
        $user_login = $USER->id;

        // Get info from database
        $peerforum = $DB->get_record('peerforum', array('id'=>$peerforum_id));
        $post_author = $DB->get_record('peerforum_posts', array('id' => $post_topeergrade))->userid;

        $final_grade_mode = $peerforum->finalgrademode;

        if ($peergrade->settings->aggregationmethod == PEERGRADE_AGGREGATE_NONE) {
            return null;//peergrades are turned off
        }

        $peergrademanager = new peergrade_manager();


        $strpeergrade = get_string("peergrade", "peerforum");
        $peergradehtml = '';


        // get 'edit' from url
        $actual_url = $_SERVER['REQUEST_URI'];
        $values = parse_url($actual_url, PHP_URL_QUERY);
        $getvalues = explode('&', $values);

        $editpostid = -1;
        $currentpage = 0;

        foreach($getvalues as $i => $values){
            $val = explode('=', $getvalues[$i]);
            if($val[0] == 'editpostid'){
                $editpostid = $val[1];
            }
            if($val[0] == 'page'){
                $currentpage = $val[1];
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

        if(has_capability('mod/peerforum:viewallpeergrades', $PAGE->context)){
            $isstudent = false;
        } else {
            $isstudent = true;
        }

        $can_user_peergrade_opt = can_user_peergrade_opt($isstudent, $final_grade_mode);

        $already_peergraded = $peergrade->post_already_peergraded($post_topeergrade, $user_login);

    //verify if peer grade can only be shown after rating is done
    $showafterrating = $peerforum->showafterrating;
    $canshowafterrating = true;

    if($showafterrating){
        //see if rating is enabled
        if($peerforum->assessed != 0){
            //verify if post was already rated by this user
            $already_rated = $DB->get_record('peerforum_ratingpeer', array('itemid' => $post_topeergrade, 'userid' => $user_login));
            if(!empty($already_rated)){
                $canshowafterrating = true;
            } else {
                $canshowafterrating = false;
            }
        }
    }

        if ($isstudent && $peerforum->showpeergrades && $canshowafterrating|| !$isstudent && $canshowafterrating) {

            //link notas
            $aggregatelabel = $peergrademanager->get_aggregate_label($peergrade->settings->aggregationmethod);
            $aggregatestr   = $peergrade->get_aggregate_string();


            $aggregatehtml = html_writer::tag('span', $aggregatestr, array('id' => 'peergradeaggregate'.$post_topeergrade, 'class' => 'peergradeaggregate')).' ';
            if ($peergrade->count > 0) {
                $countstr = "({$peergrade->count})";
            } else {
                $countstr = '-';
            }

            $aggregatehtml .= html_writer::tag('span', $countstr, array('id'=>"peergradecount{$post_topeergrade}", 'class' => 'peergradecount')).' ';

            $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

            $peergradehtml .= html_writer::tag('span',  $aggregatelabel, array('class'=>'peergrade-aggregate-label'));

            if(!$isstudent){
                $nonpopuplink = $peergrade->get_view_peergrades_url();
                $popuplink = $peergrade->get_view_peergrades_url(true);

                $action = new popup_action('click', $popuplink, 'peergrades', array('height' => 400, 'width' => 600));
                $peergradehtml .= $this->action_link($nonpopuplink, $aggregatehtml, $action);
            } else {
                $peergradehtml .= $aggregatehtml;
            }
        }
        //$peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

        $formstart = null;


        $enablefeedback = $peerforum->enablefeedback;

        $criteria = $peerforum->peergradecriteria;

        $peergradeurl = null;

        if($criteria == 'numeric scale'){
            // Initialise the JavaScript so peergrades can be done by AJAX.
            $peergrademanager->initialise_peergrade_javascript($PAGE);

            $peergradeurl = $peergrade->get_peergrade_url(null, $peergrade->returnurl, false);

        } else if($criteria == 'other'){
            $peergrademanager->initialise_peergradecriteria_javascript($PAGE);

            $peergradeurl = $peergrade->get_peergrade_url(null, $peergrade->returnurl, true);
        }


        //start the peergrade form
        $formattrs = array(
        'id'     => "postpeergrade{$post_topeergrade}",
        'class'  => 'postpeergradeform',
        'method' => 'post',
        'action' => $peergradeurl->out_omit_querystring()
        );

        $peergrade_end = $peergrade->verify_end_peergrade_post($post_topeergrade, $peerforum);

        $time_to_edit = verify_time_to_edit($post_topeergrade, $user_login);

        if($isstudent){
            $post_time = verify_post_expired($post_topeergrade, $peerforum, $user_login, $courseid);

            if(!empty($post_time)){
                $post_expired = $post_time->post_expired;
                $time_interval = $post_time->time_interval;
                $time_current = $post_time->time_current;
            } else {
                $post_expired = true;
            }

        } else {
            $post_expired = false;
        }

        $expired_post = $post_expired;


        if(!$peergrade_end || !$post_expired){

            if ($peergrade->user_can_peergrade()) {

                //Verify if user can peergrade this post
                $can_peergrade = $peergrade->can_peergrade_this_post($user_login, $post_topeergrade, $courseid);

                // PEERGRADE POST//
                if($can_peergrade || !$isstudent){
                    if(!$post_expired){

/*[FORM - postpeergradeform*/$formstart  = html_writer::start_tag('form', $formattrs);
/*[DIV - peergradeform*/     $formstart .= html_writer::start_tag('div', array('class' => 'peergradeform'));


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

                        if($user_login != $peergrade->itemuserid){
                            $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($user_login, $post_topeergrade, $courseid);
                        } else {
                            $already_peergraded_by_user = 0;
                        }

                        $can_user_peergrade = can_user_peergrade($already_peergraded_by_user, $editpostid, $post_topeergrade);

                            if($can_user_peergrade){

                                $peergradescalearray = array(PEERGRADE_UNSET_PEERGRADE => $strpeergrade.'...') + $peergrade->settings->peergradescale->peergradescaleitems;

                                $peergradescaleattrs = array('class'=>'postpeergrademenu peergradeinput','id'=>'menupeergrade'.$post_topeergrade);

                                $peergradehtml .= html_writer::label($peergrade->peergrade, 'menupeergrade'.$post_topeergrade, false, array('class' => 'accesshide'));

                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                if($isstudent){
                                    $time_left = get_time_left($time_interval);

                                } else {
                                    $time_left = '-';
                                }

                                if(!$isstudent){
                                    $user_blocked = 0;
                                    $is_exclusive = 0;
                                } else {
                                    $user_blocked = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user_login))->userblocked;
                                    $is_exclusive = $peergrade->verify_exclusivity($peergrade->itemuserid, $user_login, $courseid);
                                }


                                if($can_user_peergrade_opt){
                                    if(!$user_blocked && !$is_exclusive){

                                        //Time left to peergrade
                                        $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                        $peergradehtml .= html_writer::tag('span', "Time left to peergrade: ".$time_left."", array('style'=> 'color: #ff6666;'));//color: #6699ff;
                                        $peergradehtml .= html_writer::tag('hr', '', array('style' => 'height:2px; width:98%; background-color: #e3e3e3;')); // Should produce <hr />

                                    }
                                }

                                $criteria = $peerforum->peergradecriteria;

                                // select peer grade (select a grade)
                                if($can_user_peergrade_opt){
                                    if((!$user_blocked && !$is_exclusive)){
                                        if($criteria == 'numeric scale'){
                                            $peergradehtml .= html_writer::tag('span', "Select a grade: ", array('style'=> 'color: black;'));//color: #6699ff;
                                            $peergradehtml .= html_writer::select($peergradescalearray, 'peergrade', $peergrade->peergrade, false, $peergradescaleattrs);
                                        } else if ($criteria == 'other'){

/*[DIV - peergradeform*/                   // $peergradehtml .= html_writer::start_tag('div', array('class' => 'peergradecriteria'));

                                            $inputs = $peergradeurl->params();

                                            // add the hidden inputs
                                            foreach ($inputs as $name => $value) {
                                                $attributes = array('type' => 'hidden', 'class' => 'criteriainput', 'name' => $name, 'value' => $value);
                                                $peergradehtml .= html_writer::empty_tag('input', $attributes);
                                            }


                                            $gradecriteria1 = $peerforum->gradecriteria1;
                                            $gradecriteria2 = $peerforum->gradecriteria2;
                                            $gradecriteria3 = $peerforum->gradecriteria3;

                                            if(!empty($gradecriteria1)){

                                                $attribute1 = array('type' => 'hidden', 'class' => 'criteriainput', 'name' => 'criteria1', 'value' => $gradecriteria1);
                                                $peergradehtml .= html_writer::empty_tag('input', $attribute1);
                                                $gradechosen1_db = $DB->get_record('peerforum_peergradecriteria', array('itemid' => $post_topeergrade, 'userid' => $user_login, 'criteria' => $gradecriteria1));

                                                if(!$gradechosen1_db){
                                                    $gradechosen1 = $peergrade->peergrade;
                                                } else {
                                                    $gradechosen1 = $gradechosen1_db->grade;
                                                }

                                                $criteriaattrs = array('class'=>'menu1peergradecriteria criteriainput','id'=>'menu1peergradecriteria'.$post_topeergrade);
                                                $peergradehtml .= html_writer::tag('span', "Select a grade for ".$gradecriteria1.": ", array('style'=> 'color: black;'));//color: #6699ff;
                                                $peergradehtml .= html_writer::select($peergradescalearray, 'menu1peergradecriteria'.$post_topeergrade, $gradechosen1, false, $criteriaattrs);
                                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                            }
                                            if(!empty($gradecriteria2)){
                                                $attribute2 = array('type' => 'hidden', 'class' => 'criteriainput', 'name' => 'criteria2', 'value' => $gradecriteria2);
                                                $peergradehtml .= html_writer::empty_tag('input', $attribute2);
                                                $gradechosen2_db = $DB->get_record('peerforum_peergradecriteria', array('itemid' => $post_topeergrade, 'userid' => $user_login, 'criteria' => $gradecriteria2));

                                                if(!$gradechosen2_db){
                                                    $gradechosen2 = $peergrade->peergrade;
                                                } else {
                                                    $gradechosen2 = $gradechosen2_db->grade;
                                                }

                                                $criteriaattrs = array('class'=>'menu2peergradecriteria criteriainput','id'=>'menu2peergradecriteria'.$post_topeergrade);
                                                $peergradehtml .= html_writer::tag('span', "Select a grade for ".$gradecriteria2.": ", array('style'=> 'color: black;'));//color: #6699ff;
                                                $peergradehtml .= html_writer::select($peergradescalearray, 'menu2peergradecriteria'.$post_topeergrade, $gradechosen2, false, $criteriaattrs);
                                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                            }
                                            if(!empty($gradecriteria3)){
                                                $attribute3 = array('type' => 'hidden', 'class' => 'criteriainput', 'name' => 'criteria3', 'value' => $gradecriteria3);
                                                $peergradehtml .= html_writer::empty_tag('input', $attribute3);
                                                $gradechosen3_db = $DB->get_record('peerforum_peergradecriteria', array('itemid' => $post_topeergrade, 'userid' => $user_login, 'criteria' => $gradecriteria3));

                                                if(!$gradechosen3_db){
                                                    $gradechosen3 = $peergrade->peergrade;
                                                } else {
                                                    $gradechosen3 = $gradechosen3_db->grade;
                                                }

                                                $criteriaattrs = array('class'=>'menu3peergradecriteria criteriainput','id'=>'menu3peergradecriteria'.$post_topeergrade);
                                                $peergradehtml .= html_writer::tag('span', "Select a grade for ".$gradecriteria3.": ", array('style'=> 'color: black;'));//color: #6699ff;
                                                $peergradehtml .= html_writer::select($peergradescalearray, 'menu3peergradecriteria'.$post_topeergrade, $gradechosen3, false, $criteriaattrs);
                                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                            }

/*FORM - peergradeform]*/   // $peergradehtml .= html_writer::end_tag('div');
                                        }
                                    }
                                }
                            }
/*DIV - peergradeform]*/    $peergradehtml .= html_writer::end_tag('div');

//------------------- WRITE FEEDBACK -----------------------------------//
                    if($can_user_peergrade){
                            //the user can write feedback
                            if($enablefeedback){
                                if(($isstudent && $final_grade_mode != 1) || (!$isstudent && $final_grade_mode != 2) || $final_grade_mode == 3){

                                    if(!$user_blocked && !$is_exclusive){

                                        $info = $DB->get_record('peerforum_peergrade', array('itemid' => $post_topeergrade, 'userid' => $user_login));

                                        $attributes = array('name' => "feedbacktext".$post_topeergrade, 'form' => "postpeergrade{$post_topeergrade}",'class' => 'feedbacktext','id'=> 'feedbacktext'.$post_topeergrade, 'value' => 'null_feedback', 'wrap' => 'virtual', 'style' => 'height:100%; width:98%; max-width:98%;', 'rows' => '5', 'cols' => '5', 'placeholder' => get_string('writefeedback', 'peerforum'));

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

                            if($can_user_peergrade_opt){

                                if(!$user_blocked && !$is_exclusive){

                                    //Feedback autor
                                    $anonymouspeergrader = $peerforum->remainanonymous;

                                    if($anonymouspeergrader){
                                        $grader = '[Your peergrade to this post is anonymous]';
                                    }else{
                                        $grader = '[Your peergrade to this post is public]';
                                    }

                                    //output submit button
                                    $attbutton = array('type' => 'submit', 'name' => 'postpeergrademenusubmit'.$post_topeergrade, 'class' => 'postpeergrademenusubmit', 'id' => 'postpeergradesubmit'.$post_topeergrade, 'value' => s(get_string('peergrade', 'peerforum')));
                                    $peergradehtml .= html_writer::empty_tag('input', $attbutton);

                                    $peergradehtml .= html_writer::tag('div', $grader, array('class' => 'author')); // Author.
                                }
                        }
                    }
/*FORM - postpeergradeform]*/$peergradehtml .= html_writer::end_tag('form');
                }
            }
        }
    } else {

        if($peergrade_end){
            $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
            $peergradehtml .= html_writer::tag('span', "The activity of peer grading this post has ended.", array('style'=> 'color: #6699ff;'));

        } else if($post_expired){
            //No Time left to peergrade
            if($can_user_peergrade_opt){

                if(!$isstudent){
                    $user_blocked = 0;
                    $is_exclusive = 0;
                } else {
                    $user_blocked = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $user_login))->userblocked;
                    $is_exclusive = $peergrade->verify_exclusivity($peergrade->itemuserid, $user_login, $courseid);
                }

                if($isstudent && ((!$user_blocked && !$is_exclusive))){
                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                    $peergradehtml .= html_writer::tag('span', "Your time to peergrade this post has expired", array('style'=> 'color: #6699ff;'));
                }
            }
        }
    }
            $students_assigned = get_not_assigned_users($post_topeergrade);


            //$students_assigned = get_students_can_be_assigned($courseid, $post_topeergrade, $peergrade->itemuserid);

/*--------------- DISPLAY PEERGRADE & FEEDBACK ------------- */

                //See if exists any feedback in the DB
                $all_feedback = $peergrade->exists_feedback($post_topeergrade);

                if(!empty($all_feedback) && $canshowafterrating){
                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                    //button colapse/expand
                    $PAGE->requires->js('/mod/peerforum/collapse.js');
                    $expandstr = 'Expand all peergrades';

                    //post assigned to this user?
                    $peergraders = get_post_peergraders($post_topeergrade);

                    if($user_login != $peergrade->itemuserid){
                        $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($user_login, $post_topeergrade, $courseid);
                    }
                    else {
                        $already_peergraded_by_user = 0;
                    }

                    //post assigned to this user?
                    $peergraders = get_post_peergraders($post_topeergrade);

                    $is_assigned = false;
                    if(in_array($user_login, $peergraders)){
                        $is_assigned = true;
                    }

                    if(($already_peergraded_by_user && $editpostid == -1) || ($user_login == $peergrade->itemuserid && $already_peergraded_by_user) || !$isstudent || !$is_assigned || ($is_assigned && $post_expired)){
                        $peergradehtml .= $OUTPUT->action_link($CFG->dirroot . '/mod/peerforum/collapse.php', $expandstr, new component_action('click', 'peerforum_collapse', array('postid' => $post_topeergrade)), array('id' => 'actionlink'.$post_topeergrade));
                    }

/*[DIV - peergradefeedbacks*/$peergradehtml .= html_writer::start_tag('div', array('id'=> 'peergradefeedbacks'.$post_topeergrade, 'class' => 'peergradefeedbacks', 'style' => 'display:none;'));


                    $int_peergrader = 0;

                    foreach ($all_feedback as $i => $value){
                        $postid = $all_feedback[$i]->itemid;
                        $userid = $all_feedback[$i]->userid;

                        $can_see_grades = $peergrade->can_see_grades($peerforum, $user_login, $postid, $userid, $expired_post);
                        $can_see_feedbacks = $peergrade->can_see_feedbacks($peerforum, $user_login, $postid, $userid, $expired_post);

                        $time_see_grades = $peergrade->time_to_see_grades($peerforum, $user_login, $postid, $userid, $expired_post);
                        $time_see_feedbacks = $peergrade->time_to_see_feedbacks($peerforum, $user_login, $postid, $userid, $expired_post);

                        $int_peergrader = $int_peergrader + 1;

                        if($can_see_grades && $time_see_grades || $can_see_feedbacks && $time_see_feedbacks){

                            if($user_login != $peergrade->itemuserid){
                                $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($user_login, $post_topeergrade, $courseid);
                            }
                            else {
                                $already_peergraded_by_user = 0;
                            }

                            //post assigned to this user?
                            $peergraders = get_post_peergraders($post_topeergrade);

                            $is_assigned = false;
                            if(in_array($user_login, $peergraders)){
                                $is_assigned = true;
                            }


                            if(($already_peergraded_by_user && $editpostid == -1) || ($user_login == $peergrade->itemuserid && $already_peergraded_by_user) || !$isstudent || !$is_assigned || ($is_assigned && $post_expired)){

/*[FORM - postpeergradeform*/   $formstart  = html_writer::start_tag('form', $formattrs);
/*[DIV - peergradeform*/        $formstart .= html_writer::start_tag('div', array('class' => 'peergradeform'));

                                    $inputs = $peergradeurl->params();

                                    // add the hidden inputs
                                    foreach ($inputs as $name => $value) {
                                        $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                                        $peergradehtml .= html_writer::empty_tag('input', $attributes);
                                    }


/*[FORM - postpeergradeform*/   $peergradehtml  .= html_writer::start_tag('form', $formattrs);

                                    $inputs = $peergradeurl->params();

                                    // add the hidden inputs
                                    foreach ($inputs as $name => $value) {
                                        $attributes = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                                        $peergradehtml .= html_writer::empty_tag('input', $attributes);
                                    }


/*[DIV - peergradeform_feedbacks*/  $peergradehtml .= html_writer::start_tag('div', array('class' => 'peergradeform_feedbacks'));
/*[DIV - peerforumpostseefeedback*/ $peergradehtml .= html_writer::start_tag('div', array('class'=>'peerforumpostseefeedback clearfix',
                                                                                'role' => 'region',
                                                                                'aria-label' => get_string('givefeedback', 'peerforum')));

                                    $feedbackstr = $all_feedback[$i]->feedback;

                                    $input_feedback = new stdClass();
                                    $input_feedback->text = $feedbackstr;


                                    // add the feedback hidden inputs
                                    $att = array('type' => 'hidden', 'class' => 'writtenfeedbacktext', 'id' => 'writtenfeedbacktext', 'value' => 'feedback_null');
                                    $peergradehtml .= html_writer::tag('input', '',$att);

/*[DIV - row header*/               $peergradehtml .= html_writer::start_tag('div', array('class'=>'row header'));
/*[DIV - topic*/                    $peergradehtml .= html_writer::start_tag('div', array('class'=>'topic'));

                                    //Feedback autor
                                    $anonymouspeergrader = $peerforum->remainanonymous;

                                    $timemodified = $peergrade->get_time_modified($i);
                                    $by = new stdClass();

                                    if(!$isstudent){
                                        $grader = $userid;
                                        $user_obj = $DB->get_record('user', array('id' => $userid));
                                        $peergradehtml .= $this->user_picture($user_obj);
                                        $by->name = html_writer::link(new moodle_url('/user/view.php', array('id'=>$user_obj->id)), $user_obj->firstname .' '. $user_obj->lastname);

                                    } else {
                                        if($anonymouspeergrader){
                                            if($userid == $user_login){
                                                $grader = $userid;
                                                $user_obj = $DB->get_record('user', array('id' => $userid));
                                                $peergradehtml .= $this->user_picture($user_obj);
                                                $by->name = html_writer::link(new moodle_url('/user/view.php', array('id'=>$user_obj->id)), $user_obj->firstname .' '. $user_obj->lastname);
                                            } else {
                                                 $grader = 'Grader '.$int_peergrader;
                                                 $peergradehtml .= html_writer::empty_tag('img', array('src' => new moodle_url('/mod/peerforum/pix/user.png') , 'alt' => 'user_anonymous', 'style' => 'width:32px;height:32px;', 'class' => 'icon', 'align' => 'left'));
                                                 $by->name =  $grader;
                                             }
                                        }else{
                                             $grader = $userid;
                                             $user_obj = $DB->get_record('user', array('id' => $userid));
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

/*--------------- //DISPLAY PEERGRADE ---------------*/

                                    if(($can_see_grades && $time_see_grades && $is_assigned && $already_peergraded_by_user) || ($can_see_grades && $time_see_grades && !$is_assigned) || ($is_assigned && $post_expired)){
                                        $criteria = $peerforum->peergradecriteria;

                                        if($criteria == 'numeric scale'){

                                            $peergrade_given = $all_feedback[$i]->peergrade;
                                            $peergradehtml .= html_writer::tag('span', 'Peer grade: ', array('id'=>'outfeedback', 'class'=>'outfeedback', 'style' => "font-weight:bold"));
                                            $peergradehtml .= html_writer::tag('span', $peergrade_given, array('id'=>'outfeedback', 'class'=>'outfeedback'));

                                        } else if ($criteria == 'other'){

                                            $peergrade_given = $all_feedback[$i]->peergrade;
                                            $allgrades = $DB->get_records('peerforum_peergradecriteria', array('itemid' => $postid, 'userid' => $userid));

                                            foreach ($allgrades as $key => $value) {
                                                $criteria = $allgrades[$key]->criteria;
                                                $grade_given = $allgrades[$key]->grade;
                                                $peergradehtml .= html_writer::tag('span', $criteria.': ', array('id'=>'outfeedback', 'class'=>'outfeedback', 'style' => "font-weight:bold; color:grey"));
                                                $peergradehtml .= html_writer::tag('span', $grade_given, array('id'=>'outfeedback', 'class'=>'outfeedback'));
                                                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                            }

                                            $peergradehtml .= html_writer::tag('span', 'Final peer grade: ', array('id'=>'outfeedback', 'class'=>'outfeedback', 'style' => "font-weight:bold"));
                                            $peergradehtml .= html_writer::tag('span', $peergrade_given, array('id'=>'outfeedback', 'class'=>'outfeedback'));

                                        }

                                    } else {
                                        $peergradehtml .= html_writer::tag('span', 'Peer grade not available.', array('id'=>'outfeedback', 'class'=>'outfeedback'));
                                    }
                                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                                    if($enablefeedback){

                                        if(($can_see_feedbacks && $time_see_feedbacks && $is_assigned && $already_peergraded_by_user) || ($can_see_feedbacks && $time_see_feedbacks && !$is_assigned) || ($is_assigned && $post_expired)){
                                            $peergradehtml .= html_writer::tag('span', 'Feedback: ', array('id'=>'outfeedback', 'class'=>'outfeedback', 'style' => "font-weight:bold"));
                                            $peergradehtml .= html_writer::tag('span', $feedbackstr, array('id'=>'outfeedback', 'class'=>'outfeedback'));

                                        } else {
                                            $peergradehtml .= html_writer::tag('span', 'Feedback not available.', array('id'=>'outfeedback', 'class'=>'outfeedback'));
                                        }
                                    }
/*DIV - topic]*/                    $peergradehtml .= html_writer::end_tag('div');
/*DIV - row header]*/               $peergradehtml .= html_writer::end_tag('div'); // row


                                    //Edit peergrade
                                    if($user_login != $peergrade->itemuserid){
                                        $already_peergraded_by_user = $peergrade->post_already_peergraded_by_user($user_login, $post_topeergrade, $courseid);
                                    }
                                    else {
                                        $already_peergraded_by_user = 0;
                                    }

                                    $user_blocked_db = $DB->get_record('peerforum_peergrade_users', array('courseid'=>$courseid, 'iduser' => $userid));

                                    if(!empty($user_blocked_db)){
                                        $user_blocked = $user_blocked_db->userblocked;
                                    } else {
                                        $user_blocked = 1;
                                    }
                                    $is_exclusive = $peergrade->verify_exclusivity($peergrade->itemuserid, $user_login, $courseid);


                                    if($isstudent){
                                        $post_time = verify_post_expired($post_topeergrade, $peerforum, $user_login, $courseid);

                                        if(!empty($post_time)){
                                            $post_expired = $post_time->post_expired;
                                            $time_interval = $post_time->time_interval;
                                            $time_current = $post_time->time_current;
                                        } else {
                                            $post_expired = true;
                                        }
                                    } else {
                                        $post_expired = false;
                                    }


                                    if(!$post_expired){
                                        if(!$peergrade_end || $time_to_edit){
                                            if($can_user_peergrade_opt){
                                                if(!$user_blocked && !$is_exclusive){
                                                    if(($already_peergraded_by_user && $display == '2')){
                                                        if($user_login == $userid){
                                                            $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                                                            $editbutton = array('type' => 'submit', 'name' => 'editpeergrade'.$post_topeergrade, 'class' => 'editpeergrade', 'id' => 'editpeergrade'.$post_topeergrade,'value' => s(get_string('editpeergrade', 'peerforum')));
                                                            $peergradehtml .= html_writer::empty_tag('input', $editbutton);
                                                        }
                                                    }
                                                }
                                        }
                                    }
                                }
/*DIV - peerforumpostseefeedback]*/ $peergradehtml .= html_writer::end_tag('div');
/*DIV - peergradeform_feedbacks]*/  $peergradehtml .= html_writer::end_tag('div');
/*FORM - postpeergradeform]*/       $peergradehtml .= html_writer::end_tag('form');
                            }
                        }
                    }
/*FORM - postpeergradeform]*/   $peergradehtml .= html_writer::end_tag('form');

/*DIV - peergradeform]*/        $peergradehtml .= html_writer::end_tag('div');
        }

            if(!$isstudent){

                $PAGE->requires->js('/mod/peerforum/collapse.js');
                $expandstr = 'Expand details';
                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />

                $peergradehtml .= $OUTPUT->action_link($CFG->dirroot . '/mod/peerforum/collapse.php', $expandstr, new component_action('click', 'peerforum_collapse_config', array('postid' => $post_topeergrade)), array('id' => 'actionlink_config'.$post_topeergrade));
/*[DIV - peergradeconfig*/ $peergradehtml .= html_writer::start_tag('div', array('id'=> 'peergradeconfig'.$post_topeergrade, 'class' => 'peergradeconfig', 'style' => 'display:none;'));

                //Assign peer grader
                // Students assigned to peer grade this post
                $peers_topeergrade = get_post_peergraders($post_topeergrade);

                $students = get_students_name($students_assigned);


                //Assign peer grader
                $assignpeerurl = new moodle_url('/mod/peerforum/assignpeer.php', array('itemid' => $post_topeergrade, 'courseid' => $courseid, 'postauthor' => $post_author));
                $formattrs = array(
                'id'     => "menuassignpeerform{$post_topeergrade}",
                'class'  => 'menuassignpeerform',
                'method' => 'post',
                'action' => $assignpeerurl->out_omit_querystring()
                );

/*[FORM - menuassignpeerform*/$peergradehtml  .= html_writer::start_tag('form', $formattrs);

                $inputs = $assignpeerurl->params();

                // add the hidden inputs
                foreach ($inputs as $name => $value) {
                    $attributes = array('type' => 'hidden', 'class' => 'studentinput', 'name' => $name, 'value' => $value);
                    $peergradehtml .= html_writer::empty_tag('input', $attributes);
                }

                $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                $peergradehtml .= html_writer::tag('span', "Students assigned to peer grade this post: ", array('style'=> 'color: grey;'));//color: #6699ff;


                if(empty($peers_topeergrade)){
/*[DIV - nonepeers*/$peergradehtml .= html_writer::start_tag('div', array('id'=> 'nonepeers'.$post_topeergrade, 'class' => 'nonepeers', 'style' => 'display:block;'));
                } else {
/*[DIV - nonepeers*/$peergradehtml .= html_writer::start_tag('div', array('id'=> 'nonepeers'.$post_topeergrade, 'class' => 'nonepeers', 'style' => 'display:none;'));
                }

                //assign parent peers if is not discussion topic
                $parent = $DB->get_record('peerforum_posts', array('id' => $post_topeergrade))->parent;
                if($parent != 0){
                    $PAGE->requires->js('/mod/peerforum/assignpeersparent.js');
                    $assignpeersparentstr = get_string('assignpeergradersparent', 'peerforum');
                    $peergradehtml .= $OUTPUT->action_link($CFG->dirroot . '/mod/peerforum/assignpeersparent.php', $assignpeersparentstr, new component_action('click', 'peerforum_assignpeersparent', array('itemid' => $post_topeergrade, 'courseid' => $courseid, 'postauthor' => $post_author)), array('id' => 'actionlinkpeers'.$post_topeergrade));
                }
/*DIV - nonepeers]]*/$peergradehtml .= html_writer::end_tag('div');

                if(!empty($peers_topeergrade)){

                    $peersnames_array = get_peersnames($peers_topeergrade, $post_topeergrade);

                    $peersnames = null;
                    foreach ($peersnames_array as $y => $value) {
                        $peersnames .= $peersnames_array[$y];
                    }

                    $peergradehtml .= html_writer::tag('span', $peersnames, array('id' => 'peersassigned'.$post_topeergrade));
                } else {
                    $peergradehtml .= html_writer::tag('span', '', array('id' => 'peersassigned'.$post_topeergrade, 'style'=> 'color:'.'grey'.';'));
                }


                // Show options about assign/remove peers
                if($peerforum->showdetails == 1){
                    $peergrademanager->initialise_assignpeer_javascript($PAGE);

                    $selectstudentrandom = get_string('selectstudentrandom', 'peerforum');
                    $assignstudentstr = get_string('assignstudentstr', 'peerforum');

                    $studentsarray = array(UNSET_STUDENT_SELECT => $assignstudentstr, UNSET_STUDENT => $selectstudentrandom) + $students;

                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                    $peergradehtml .= html_writer::tag('span', "Select student to ASSIGN this post to peer grade: ", array('style'=> 'color: grey;'));//color: #6699ff;

                    //Assign peer grader
                    $studentattrs = array('class'=>'menuassignpeer studentinput','id'=>'menuassignpeer'.$post_topeergrade);
                    $peergradehtml .= html_writer::select($studentsarray, 'menuassignpeer'.$post_topeergrade, $studentsarray[UNSET_STUDENT_SELECT], false, $studentattrs);

    /*FORM - menuassignpeerform]*/$peergradehtml .= html_writer::end_tag('form');

                    // Remove peer grader
                    $peergrademanager->initialise_removepeer_javascript($PAGE);

                    $studenturl_rmv = new moodle_url('/peergrade/removestudent.php');
                    $formattrs_rmv = array(
                    'id'     => "poststudentmenurmv{$post_topeergrade}",
                    'class'  => 'poststudentmenurmv',
                    'method' => 'post',
                    'action' => $studenturl_rmv->out_omit_querystring()
                    );

    /*[FORM - poststudentmenurmv*/$peergradehtml  .= html_writer::start_tag('form', $formattrs_rmv);

                    $inputs_rmv = $peergradeurl->params();

                    // add the hidden inputs
                    foreach ($inputs_rmv as $name => $value) {
                        $attributes_rmv = array('type' => 'hidden', 'class' => 'peergradeinput', 'name' => $name, 'value' => $value);
                        $peergradehtml .= html_writer::empty_tag('input', $attributes_rmv);
                    }


                    $students_assigned_rmv = get_assigned_users($post_topeergrade);

                    $students_rmv = get_students_name($students_assigned_rmv);

                    //Remove peer grader
                    $removepeerurl = new moodle_url('/mod/peerforum/removepeer.php', array('itemid' => $post_topeergrade, 'courseid' => $courseid, 'postauthor' => $post_author));
                    $formattrs = array(
                    'id'     => "menuremovepeerform{$post_topeergrade}",
                    'class'  => 'menuremovepeerform',
                    'method' => 'post',
                    'action' => $removepeerurl->out_omit_querystring()
                    );

    /*[FORM - menuremovepeerform*/$peergradehtml .= html_writer::start_tag('form', $formattrs);

                    $inputs = $removepeerurl->params();

                    // add the hidden inputs
                    foreach ($inputs as $name => $value) {
                        $attributes = array('type' => 'hidden', 'class' => 'studentinput', 'name' => $name, 'value' => $value);
                        $peergradehtml .= html_writer::empty_tag('input', $attributes);
                    }

                    $removestudentstr = get_string('removestudent', 'peerforum');
                    $studentsarray_rmv = array(UNSET_STUDENT_SELECT => $removestudentstr, UNSET_STUDENT => $selectstudentrandom) + $students_rmv;

                    $peergradehtml .= html_writer::tag('span', "Select student to REMOVE this post to peer grade: ", array('style'=> 'color: grey;'));//color: #6699ff;

                    $studentattrs_rmv = array('class'=>'menuremovepeer studentinput','id'=>'menuremovepeer'.$post_topeergrade);
                    $peergradehtml .= html_writer::select($studentsarray_rmv, 'menuremovepeer'.$post_topeergrade, $studentsarray_rmv[UNSET_STUDENT], false, $studentattrs_rmv);

                } else {
                    $peergradehtml .= html_writer::tag('br', ''); // Should produce <br />
                }

/*FORM - menuremovepeerform]*/$peergradehtml .= html_writer::end_tag('form');
/*FORM - poststudentmenurmv]*/$peergradehtml .= html_writer::end_tag('form');
/*DIV - peergradeconfig]*/$peergradehtml .= html_writer::end_tag('div');
            }
        return $peergradehtml;
    }

//------------------------------------------------//


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
}
