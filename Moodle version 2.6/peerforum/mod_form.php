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
 * @package mod-peerforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_peerforum_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB;

        $mform    =& $this->_form;

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('peerforumname', 'peerforum'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->add_intro_editor(true, get_string('peerforumintro', 'peerforum'));

        $peerforumtypes = peerforum_get_peerforum_types();
        core_collator::asort($peerforumtypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('peerforumtype', 'peerforum'), $peerforumtypes);
        $mform->addHelpButton('type', 'peerforumtype', 'peerforum');
        $mform->setDefault('type', 'general');

        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'peerforum'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->peerforum_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'peerforum'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'peerforum');
        $mform->setDefault('maxbytes', $CFG->peerforum_maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'peerforum'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'peerforum');
        $mform->setDefault('maxattachments', $CFG->peerforum_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'peerforum'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'peerforum');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'peerforum'));

        $options = array();
        $options[PEERFORUM_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'peerforum');
        $options[PEERFORUM_FORCESUBSCRIBE] = get_string('subscriptionforced', 'peerforum');
        $options[PEERFORUM_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'peerforum');
        $options[PEERFORUM_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled','peerforum');
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'peerforum'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'peerforum');

        $options = array();
        $options[PEERFORUM_TRACKING_OPTIONAL] = get_string('trackingoptional', 'peerforum');
        $options[PEERFORUM_TRACKING_OFF] = get_string('trackingoff', 'peerforum');
        if ($CFG->peerforum_allowforcedreadtracking) {
            $options[PEERFORUM_TRACKING_FORCED] = get_string('trackingon', 'peerforum');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'peerforum'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'peerforum');
        $default = $CFG->peerforum_trackingtype;
        if ((!$CFG->peerforum_allowforcedreadtracking) && ($default == PEERFORUM_TRACKING_FORCED)) {
            $default = PEERFORUM_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->peerforum_enablerssfeeds) && $CFG->peerforum_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'peerforum');
            $choices[2] = get_string('posts', 'peerforum');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'peerforum');

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'peerforum');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
        }

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'peerforum'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','peerforum');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'peerforum'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'peerforum');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'peerforum'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'peerforum');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'peerforum'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'peerforum');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_peerforum');

//-------------------------------------------------------------------------------
/*NEW CONFIGURATIONS*/

/*RATINGPEER LOCAL CONFIGURATIONS*/

    require_once($CFG->dirroot.'/ratingpeer/lib.php');
    $rm = new ratingpeer_manager();

    $mform->addElement('header', 'modstandardratingpeers', get_string('ratingpeers', 'peerforum'));

    $permission=CAP_ALLOW;
    $rolenamestring = null;
    if (!empty($this->_cm)) {
        $context = context_module::instance($this->_cm->id);

        $rolenames = get_role_names_with_caps_in_context($context, array('mod/peerforum:rateratingpeer', 'mod/'.$this->_cm->modname.':rateratingpeer'));
        $rolenamestring = implode(', ', $rolenames);
    } else {
        $rolenamestring = get_string('capabilitychecknotavailable','peerforum');
    }
    $mform->addElement('static', 'rolewarningpeer', get_string('rolewarningpeer','peerforum'), $rolenamestring);
    $mform->addHelpButton('rolewarningpeer', 'rolewarningpeer', 'peerforum');

    $mform->addElement('select', 'assessed', get_string('aggregatetyperate', 'peerforum') , $rm->get_aggregate_types());
    $mform->setDefault('assessed', 0);
    $mform->addHelpButton('assessed', 'aggregatetyperate', 'peerforum');

    $mform->addElement('modgrade', 'scale', get_string('scale'), false);
    $mform->disabledIf('scale', 'assessed', 'eq', 0);

    $mform->addElement('checkbox', 'ratingtime', get_string('ratingpeertime', 'peerforum'));
    $mform->disabledIf('ratingpeertime', 'assessed', 'eq', 0);

    $mform->addElement('date_time_selector', 'assesstimestart', get_string('from'));
    $mform->disabledIf('assesstimestart', 'assessed', 'eq', 0);
    $mform->disabledIf('assesstimestart', 'ratingpeertime');

    $mform->addElement('date_time_selector', 'assesstimefinish', get_string('to'));
    $mform->disabledIf('assesstimefinish', 'assessed', 'eq', 0);
    $mform->disabledIf('assesstimefinish', 'ratingpeertime');


/*PEERGRADE LOCAL CONFIGURATIONS*/
        $mform->addElement('header', 'gradescale', get_string('peergrading', 'peerforum'));

        require_once($CFG->dirroot.'/peergrade/lib.php');
        require_once($CFG->dirroot.'/mod/peerforum/locallib.php');

        $pm = new peergrade_manager();

        $permission = CAP_ALLOW;
        $rolenamestring = null;


        if (!empty($this->_cm)) {
            $context = context_module::instance($this->_cm->id);
            $rolenames = get_role_names_with_caps_in_context($context, array('mod/peerforum:peergrade', 'mod/'.$this->_cm->modname.':peergrade'));
            $rolenamestring = implode(', ', $rolenames);
        } else {
            $rolenamestring = get_string('capabilitychecknotavailable','peerforum');
        }
        $mform->addElement('static', 'rolewarningpeer', get_string('rolewarningpeer','peerforum'), $rolenamestring);
        $mform->addHelpButton('rolewarningpeer', 'rolewarningpeer', 'peerforum');


        //Aggregate type (allow or not peergrade)
        $mform->addElement('select', 'peergradeassessed', get_string('peeraggregatetype', 'peerforum') , $pm->get_aggregate_types());
        $mform->setDefault('peergradeassess', 0);
        $mform->addHelpButton('peergradeassessed', 'peeraggregatetype', 'peerforum');

        $mform->addElement('select', 'finalgrademode', get_string('finalgrademode', 'peerforum'), peerforum_get_final_grade_modes());
        $mform->addHelpButton('finalgrademode', 'finalgrademode', 'peerforum');
        $mform->setDefault('finalgrademode', PEERFORUM_MODE_PROFESSORSTUDENT);
        $mform->disabledIf('finalgrademode', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);


        $grades = peerforum::available_maxgrades_list();

        $mform->addElement('select', 'professorpercentage', get_string('graderatepeers', 'peerforum') , $grades);
        $mform->setDefault('professorpercentage', 100);
        $mform->addHelpButton('professorpercentage', 'professorpercentage', 'peerforum');
        $mform->disabledIf('professorpercentage', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        $mform->addElement('select', 'studentpercentage', get_string('gradepeergrades', 'peerforum') , $grades);
        $mform->setDefault('studentpercentage', 100);
        $mform->addHelpButton('studentpercentage', 'studentpercentage', 'peerforum');
        $mform->disabledIf('studentpercentage', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        // Peer scale (only points)
        $opt = array(
               1  => '1',
               2  => '2',
               3  => '3',
               4  => '4',
               5  => '5',
               6  => '6',
               7  => '7',
               8  => '8',
               9  => '9',
               10 => '10'
           );
       $mform->addElement('select', 'peergradescale', get_string('peergradescale', 'peerforum') , $opt);
       $mform->setDefault('peergradescale', 4);
       $mform->addHelpButton('peergradescale', 'peergradescale', 'peerforum');
       $mform->disabledIf('peergradescale', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

       // peergrade visibility configurations
       $visibility = array(
           'public' => get_string('public', 'peerforum'),
           'private' => get_string('private', 'peerforum'),
           'onlyprofessor' => get_string('onlyprofessor', 'peerforum'),
       );

       $mform->addElement('select', 'peergradesvisibility', get_string('peergradesvisibility', 'peerforum'), $visibility);
       $mform->addHelpButton('peergradesvisibility', 'peergradesvisibility', 'peerforum');
       $mform->setDefault('peergradesvisibility', 'public');
       $mform->disabledIf('peergradesvisibility', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);


       $peergradetypes = array(
           'always' => get_string('always', 'peerforum'),
           'after peergrade ends' => get_string('afterpeergradeends', 'peerforum'),
       );

       $mform->addElement('select', 'whenpeergrades', get_string('when', 'peerforum'), $peergradetypes);
       $mform->setDefault('whenpeergrades', 'always');
       $mform->addHelpButton('whenpeergrades', 'when', 'peerforum');
       $mform->disabledIf('whenpeergrades', 'peergradesvisibility', 'eq', 'onlyprofessor');

       $mform->disabledIf('whenpeergrades', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);


       $yesno = array(0 => get_string('no'),
                           1 => get_string('yes'));

       // Select if its necessary to give written feedback in a post
       $mform->addElement('selectyesno', 'enablefeedback', get_string('enablefeedback', 'peerforum'));
       $mform->setDefault('enablefeedback', 1);
       $mform->addHelpButton('enablefeedback', 'enablefeedback', 'peerforum');
       $mform->disabledIf('enablefeedback', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

       $feedbacktypes = array(
           'always' => get_string('always', 'peerforum'),
           'after peergrade ends' => get_string('afterpeergradeends', 'peerforum'),
       );

       //feedback visibility
       $mform->addElement('select', 'feedbackvisibility', get_string('feedbackvisibility', 'peerforum'), $visibility);
       $mform->addHelpButton('feedbackvisibility', 'feedbackvisibility', 'peerforum');
       $mform->setDefault('feedbackvisibility', 'public');
       $mform->disabledIf('feedbackvisibility', 'enablefeedback', 'eq', 0);
       $mform->disabledIf('feedbackvisibility', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);


       $mform->addElement('select', 'whenfeedback', get_string('when', 'peerforum'), $peergradetypes);
       $mform->setDefault('whenfeedback', 'always');
       $mform->addHelpButton('whenfeedback', 'when', 'peerforum');
       $mform->disabledIf('whenfeedback', 'enablefeedback', 'eq', '0');
       $mform->disabledIf('whenfeedback', 'feedbackvisibility', 'eq', 'onlyprofessor');

       $mform->disabledIf('whenfeedback', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        // Select if the peer grader remains anonymous or not
        $mform->addElement('selectyesno', 'remainanonymous', get_string('remainanonymous', 'peerforum'));
        $mform->addHelpButton('remainanonymous', 'remainanonymous', 'peerforum');
        $mform->disabledIf('remainanonymous', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        // Number of peergraders
        $optgraders = array(
             1  => '1',
             2  => '2',
             3  => '3',
             4  => '4',
             5  => '5',
             6  => '6',
             7  => '7',
             8  => '8',
             9  => '9',
             10 => '10'
         );

         $mform->addElement('select', 'selectpeergraders', get_string('selectpeergraders', 'peerforum') , $optgraders);
         $mform->setDefault('selectpeergraders', 5);
         $mform->addHelpButton('selectpeergraders', 'selectpeergraders', 'peerforum');
         $mform->disabledIf('selectpeergraders', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        // $mingraders = peerforum::available_mingraders();

         // Min of peegraders

         $mingraders = array(
              1  => '1',
              2  => '2',
              3  => '3',
              4  => '4',
              5  => '5',
              6  => '6',
              7  => '7',
              8  => '8',
              9  => '9',
              10 => '10'
          );

          if(isset($this->current->minpeergraders) && isset($this->current->selectpeergraders)){
              $minimum = $this->current->minpeergraders;
              $selectedpeergraders = $this->current->selectpeergraders;
          } else {
              $minimum = 1;
              $selectedpeergraders = 5;

          }

          if($minimum > $selectedpeergraders){
              $records = $DB->get_records('peerforum', array('course' => $COURSE->id));

              $minimum = $selectedpeergraders;

              foreach ($records as $key => $value) {
                  $data = new stdClass();
                  $id = $records[$key]->id;
                  $data->id = $id;
                  $data->minpeergraders = $minimum;

                  $DB->update_record('peerforum', $data);
              }
              purge_all_caches();

              $mform->addElement('select', 'minpeergraders', get_string('minpeergraders', 'peerforum') , $mingraders);
              $mform->setDefault('minpeergraders', $minimum);
              $mform->addHelpButton('minpeergraders', 'minpeergraders', 'peerforum');
              $mform->disabledIf('minpeergraders', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

          } else {
              $mform->addElement('select', 'minpeergraders', get_string('minpeergraders', 'peerforum') , $mingraders);
              $mform->setDefault('minpeergraders', 1);
              $mform->addHelpButton('minpeergraders', 'minpeergraders', 'peerforum');
              $mform->disabledIf('minpeergraders', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);
          }

         // Select if peer grading ends when min of peer grades are done
         $mform->addElement('selectyesno', 'finishpeergrade', get_string('finishpeergrade', 'peerforum'));
         $mform->addHelpButton('finishpeergrade', 'finishpeergrade', 'peerforum');
         $mform->disabledIf('finishpeergrade', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

         //Time period to peergrade (in days)
         $mintime = 3;

         $mform->addElement('text', 'timetopeergrade', get_string('timetopeergrade', 'peerforum'));
         $mform->setType('timetopeergrade', PARAM_INT);
         $mform->setDefault('timetopeergrade', 1);
         $mform->addRule('timetopeergrade', null, 'numeric', null, 'client');
         $mform->addHelpButton('timetopeergrade', 'timetopeergrade', 'peerforum');
         $mform->disabledIf('timetopeergrade', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

         $mform->addElement('checkbox', 'peergradetime', get_string('peergradetime', 'peerforum'));
         $mform->disabledIf('peergradetime', 'peergradeassessed', 'eq', 0);
         $mform->disabledIf('peergradetime', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        $mform->addElement('date_time_selector', 'peergradeassesstimestart', get_string('from'));
        $mform->disabledIf('peergradeassesstimestart', 'peerassessed', 'eq', 0);
        $mform->disabledIf('peergradeassesstimestart', 'peergradetime');
        $mform->disabledIf('peergradeassesstimestart', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

        $mform->addElement('date_time_selector', 'peergradeassesstimefinish', get_string('to'));
        $mform->disabledIf('peergradeassesstimefinish', 'peerassessed', 'eq', 0);
        $mform->disabledIf('peergradeassesstimefinish', 'peergradetime');
        $mform->disabledIf('peergradeassesstimefinish', 'peergradeassessed', 'eq', PEERGRADE_AGGREGATE_NONE);

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

    }

    function definition_after_data() {
        parent::definition_after_data();
        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'peerforum'), 'news');
            $mform->addHelpButton('type', 'namenews', 'peerforum');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'peerforum'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled']=
            !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions']=1;
        }
        $default_values['completionrepliesenabled']=
            !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies']=1;
        }
        $default_values['completionpostsenabled']=
            !empty($default_values['completionposts']) ? 1 : 0;
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts']=1;
        }
    }

      function add_completion_rules() {
        $mform =& $this->_form;

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpostsenabled', '', get_string('completionposts','peerforum'));
        $group[] =& $mform->createElement('text', 'completionposts', '', array('size'=>3));
        $mform->setType('completionposts',PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup', get_string('completionpostsgroup','peerforum'), array(' '), false);
        $mform->disabledIf('completionposts','completionpostsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completiondiscussionsenabled', '', get_string('completiondiscussions','peerforum'));
        $group[] =& $mform->createElement('text', 'completiondiscussions', '', array('size'=>3));
        $mform->setType('completiondiscussions',PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup', get_string('completiondiscussionsgroup','peerforum'), array(' '), false);
        $mform->disabledIf('completiondiscussions','completiondiscussionsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '', get_string('completionreplies','peerforum'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup','peerforum'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completiondiscussionsgroup','completionrepliesgroup','completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions']!=0) ||
            (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ||
            (!empty($data['completionpostsenabled']) && $data['completionposts']!=0);
    }

    function get_data() {
        $data = parent::get_data();
        if (!$data) {
            return false;
        }
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
        return $data;
    }

    function validation($data, $files) {
        if (isset($data['assessed']) && $data['assessed'] > 0 && empty($data['scale'])) {
            $errors['assessed'] = get_string('scaleselectionrequired', 'rating');
        }

        if (isset($data['peerassessed']) && $data['peerassessed'] > 0 && empty($data['peerscale'])) {
            $errors['peerassessed'] = get_string('scaleselectionrequired', 'peerforum');
        }
    }

}
