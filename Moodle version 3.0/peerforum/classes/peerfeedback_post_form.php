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
 * File containing the form definition to peer grade a post in the peerforum.
 *
 * @package   mod_peerforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



/*
public function get_view_ratings_url($popup = false) {
    $attributes = array(
        'contextid'  => $this->context->id,
        'component'  => $this->component,
        'ratingarea' => $this->ratingarea,
        'itemid'     => $this->itemid,
        'scaleid'    => $this->settings->scale->id
    );
    if ($popup) {
        $attributes['popup'] = 1;
    }
    return new moodle_url('/rating/index.php', $attributes);
}
*/


defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/repository/lib.php');

/**
 * Class to grade a post in a peerforum.
 *
 * @package   mod_peerforum
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_peerfeedback_post_form extends moodleform {
    /**
     * Returns the options array to use in filemanager for peerforum attachments
     *
     * @param stdClass $peerforum
     * @return array
     */

     /*$mform_peerfeedback = new mod_peerfeedback_post_form('lib.ph',array('course' => $course,
                                                                 'cm' => $cm,
                                                                 'modcontext' => $modcontext,
                                                                 'peerforum' => $peerforum,
                                                                 'post' => $post));


     $output .= html_writer::tag('div', array('action' => $mform_peerfeedback->display()));*/

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

        global $CFG, $OUTPUT, $DB;

        $mform =& $this->_form;

        $course = $this->_customdata['course'];
        $cm = $this->_customdata['cm'];
        $modcontext = $this->_customdata['modcontext'];
        $peerforum = $this->_customdata['peerforum'];
        $post = $this->_customdata['post'];

        $mform->addElement('editor', 'message', get_string('givefeedback', 'peerforum'));
        $mform->setType('message', PARAM_RAW);


        $mform->addElement('hidden', 'peerforum');
        $mform->setType('peerforum', PARAM_INT);


}
function validation($data, $files) {
        return array();
    }
}
