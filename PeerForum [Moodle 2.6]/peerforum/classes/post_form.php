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
 * File containing the form definition to post in the peerforum.
 *
 * @package   mod_peerforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to post in a peerforum.
 *
 * @package   mod_peerforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerforum_post_form extends moodleform {

    /**
     * Returns the options array to use in filemanager for peerforum attachments
     *
     * @param stdClass $peerforum
     * @return array
     */
    public static function attachment_options($peerforum) {
        global $COURSE, $PAGE, $CFG;
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes, $peerforum->maxbytes);
        return array(
            'subdirs' => 0,
            'maxbytes' => $maxbytes,
            'maxfiles' => $peerforum->maxattachments,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL
        );
    }

    /**
     * Returns the options array to use in peerforum text editor
     *
     * @param context_module $context
     * @param int $postid post id, use null when adding new post
     * @return array
     */
    public static function editor_options(context_module $context, $postid) {
        global $COURSE, $PAGE, $CFG;
        // TODO: add max files and max size support
        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes, $COURSE->maxbytes);
        return array(
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $maxbytes,
            'trusttext'=> true,
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL,
            'subdirs' => file_area_contains_subdirs($context, 'mod_peerforum', 'post', $postid)
        );
    }

    /**
     * Form definition
     *
     * @return void
     */
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $coursecontext = $this->_customdata['coursecontext'];
        $modcontext = $this->_customdata['modcontext'];
        $peerforum = $this->_customdata['peerforum'];
        $post = $this->_customdata['post'];
        $edit = $this->_customdata['edit'];
        $thresholdwarning = $this->_customdata['thresholdwarning'];
        $currentpage = $this->_customdata['page'];

        $mform->addElement('hidden', 'page');
        $mform->setType('page', PARAM_INT);
        $mform->setConstants(array('page'=> $currentpage));


        $mform->addElement('header', 'general', '');//fill in the data depending on page params later using set_data

        // If there is a warning message and we are not editing a post we need to handle the warning.
        if (!empty($thresholdwarning) && !$edit) {
            // Here we want to display a warning if they can still post but have reached the warning threshold.
            if ($thresholdwarning->canpost) {
                $message = get_string($thresholdwarning->errorcode, $thresholdwarning->module, $thresholdwarning->additional);
                $mform->addElement('html', $OUTPUT->notification($message));
            }
        }

        $mform->addElement('text', 'subject', get_string('subject', 'peerforum'), 'size="48"');
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', get_string('required'), 'required', null, 'client');
        $mform->addRule('subject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('editor', 'message', get_string('message', 'peerforum'), null, self::editor_options($modcontext, (empty($post->id) ? null : $post->id)));
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', get_string('required'), 'required', null, 'client');

        if (isset($peerforum->id) && peerforum_is_forcesubscribed($peerforum)) {

            $mform->addElement('static', 'subscribemessage', get_string('subscription', 'peerforum'), get_string('everyoneissubscribed', 'peerforum'));
            $mform->addElement('hidden', 'subscribe');
            $mform->setType('subscribe', PARAM_INT);
            $mform->addHelpButton('subscribemessage', 'subscription', 'peerforum');

        } else if (isset($peerforum->forcesubscribe)&& $peerforum->forcesubscribe != PEERFORUM_DISALLOWSUBSCRIBE ||
                   has_capability('moodle/course:manageactivities', $coursecontext)) {

                $options = array();
                $options[0] = get_string('subscribestop', 'peerforum');
                $options[1] = get_string('subscribestart', 'peerforum');

                $mform->addElement('select', 'subscribe', get_string('subscription', 'peerforum'), $options);
                $mform->addHelpButton('subscribe', 'subscription', 'peerforum');
            } else if ($peerforum->forcesubscribe == PEERFORUM_DISALLOWSUBSCRIBE) {
                $mform->addElement('static', 'subscribemessage', get_string('subscription', 'peerforum'), get_string('disallowsubscribe', 'peerforum'));
                $mform->addElement('hidden', 'subscribe');
                $mform->setType('subscribe', PARAM_INT);
                $mform->addHelpButton('subscribemessage', 'subscription', 'peerforum');
            }

        if (!empty($peerforum->maxattachments) && $peerforum->maxbytes != 1 && has_capability('mod/peerforum:createattachment', $modcontext))  {  //  1 = No attachments at all
            $mform->addElement('filemanager', 'attachments', get_string('attachment', 'peerforum'), null, self::attachment_options($peerforum));
            $mform->addHelpButton('attachments', 'attachment', 'peerforum');
        }

        if (empty($post->id) && has_capability('moodle/course:manageactivities', $coursecontext)) { // hack alert
            $mform->addElement('checkbox', 'mailnow', get_string('mailnow', 'peerforum'));
        }

        if (!empty($CFG->peerforum_enabletimedposts) && !$post->parent && has_capability('mod/peerforum:viewhiddentimedposts', $coursecontext)) { // hack alert
            $mform->addElement('header', 'displayperiod', get_string('displayperiod', 'peerforum'));

            $mform->addElement('date_selector', 'timestart', get_string('displaystart', 'peerforum'), array('optional'=>true));
            $mform->addHelpButton('timestart', 'displaystart', 'peerforum');

            $mform->addElement('date_selector', 'timeend', get_string('displayend', 'peerforum'), array('optional'=>true));
            $mform->addHelpButton('timeend', 'displayend', 'peerforum');

        } else {
            $mform->addElement('hidden', 'timestart');
            $mform->setType('timestart', PARAM_INT);
            $mform->addElement('hidden', 'timeend');
            $mform->setType('timeend', PARAM_INT);
            $mform->setConstants(array('timestart'=> 0, 'timeend'=>0));
        }

        if ($groupmode = groups_get_activity_groupmode($cm, $course)) { // hack alert
            $groupdata = groups_get_activity_allowed_groups($cm);
            $groupcount = count($groupdata);
            $groupinfo = array();
            $modulecontext = context_module::instance($cm->id);

            // Check whether the user has access to all groups in this peerforum from the accessallgroups cap.
            if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modulecontext)) {
                // Only allow posting to all groups if the user has access to all groups.
                $groupinfo = array('0' => get_string('allparticipants'));
                $groupcount++;
            }

            $contextcheck = has_capability('mod/peerforum:movediscussions', $modulecontext) && empty($post->parent) && $groupcount > 1;
            if ($contextcheck) {
                foreach ($groupdata as $grouptemp) {
                    $groupinfo[$grouptemp->id] = $grouptemp->name;
                }
                $mform->addElement('select','groupinfo', get_string('group'), $groupinfo);
                $mform->setDefault('groupinfo', $post->groupid);
                $mform->setType('groupinfo', PARAM_INT);
            } else {
                if (empty($post->groupid)) {
                    $groupname = get_string('allparticipants');
                } else {
                    $groupname = format_string($groupdata[$post->groupid]->name);
                }
                $mform->addElement('static', 'groupinfo', get_string('group'), $groupname);
            }
        }
        //-------------------------------------------------------------------------------
        // buttons
        if (isset($post->edit)) { // hack alert
            $submit_string = get_string('savechanges');
        } else {
            $submit_string = get_string('posttopeerforum', 'peerforum');
        }
        $this->add_action_buttons(false, $submit_string);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);

        $mform->addElement('hidden', 'peerforum');
        $mform->setType('peerforum', PARAM_INT);

        $mform->addElement('hidden', 'discussion');
        $mform->setType('discussion', PARAM_INT);

        $mform->addElement('hidden', 'parent');
        $mform->setType('parent', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);

        $mform->addElement('hidden', 'edit');
        $mform->setType('edit', PARAM_INT);

        $mform->addElement('hidden', 'reply');
        $mform->setType('reply', PARAM_INT);

        $mform->addElement('hidden', 'page');
        $mform->setType('page', PARAM_INT);
    }

    /**
     * Form validation
     *
     * @param array $data data from the form.
     * @param array $files files uploaded.
     * @return array of errors.
     */
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (($data['timeend']!=0) && ($data['timestart']!=0) && $data['timeend'] <= $data['timestart']) {
            $errors['timeend'] = get_string('timestartenderror', 'peerforum');
        }
        if (empty($data['message']['text'])) {
            $errors['message'] = get_string('erroremptymessage', 'peerforum');
        }
        if (empty($data['subject'])) {
            $errors['subject'] = get_string('erroremptysubject', 'peerforum');
        }

        return $errors;
    }
}
