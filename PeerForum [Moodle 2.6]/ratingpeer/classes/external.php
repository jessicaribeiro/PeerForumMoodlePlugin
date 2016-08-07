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
 * ratingpeer external API
 *
 * @package    core_ratingpeer
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/ratingpeer/lib.php");

/**
 * ratingpeer external functions
 *
 * @package    core_ratingpeer
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_ratingpeer_external extends external_api {

    /**
     * Returns description of get_item_ratingpeers parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_item_ratingpeers_parameters() {
        return new external_function_parameters (
            array(
                'contextlevel'  => new external_value(PARAM_ALPHA, 'context level: course, module, user, etc...'),
                'instanceid'    => new external_value(PARAM_INT, 'the instance id of item associated with the context level'),
                'component'     => new external_value(PARAM_COMPONENT, 'component'),
                'ratingpeerarea'    => new external_value(PARAM_AREA, 'ratingpeer area'),
                'itemid'        => new external_value(PARAM_INT, 'associated id'),
                'scaleid'       => new external_value(PARAM_INT, 'scale id'),
                'ratingpeerscaleid'       => new external_value(PARAM_INT, 'ratingpeerscale id'),
                'sort'          => new external_value(PARAM_ALPHA, 'sort order (firstname, ratingpeer or timemodified)')
            )
        );
    }

    /**
     * Retrieve a list of ratingpeers for a given item (forum post etc)
     *
     * @param string $contextlevel course, module, user...
     * @param int $instanceid the instance if for the context element
     * @param string $component the name of the component
     * @param string $ratingpeerarea ratingpeer area
     * @param int $itemid the item id
     * @param int $scaleid the scale id
     * @param string $sort sql order (firstname, ratingpeer or timemodified)
     * @return array Result and possible warnings
     * @throws moodle_exception
     * @since Moodle 2.9
     */
    public static function get_item_ratingpeers($contextlevel, $instanceid, $component, $ratingpeerarea, $itemid, $scaleid, $sort) {
        global $USER, $PAGE;

        $warnings = array();

        $arrayparams = array(
            'contextlevel' => $contextlevel,
            'instanceid'   => $instanceid,
            'component'    => $component,
            'ratingpeerarea'   => $ratingpeerarea,
            'itemid'       => $itemid,
            'scaleid'      => $scaleid,
            'ratingpeerscaleid'      => $ratingpeerscaleid,
            'sort'         => $sort
        );

        // Validate and normalize parameters.
        $params = self::validate_parameters(self::get_item_ratingpeers_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        // Minimal capability required.
        $callbackparams = array('contextid' => $context->id,
                        'component' => $component,
                        'ratingpeerarea' => $ratingpeerarea,
                        'itemid' => $itemid,
                        'scaleid' => $scaleid,
                        'ratingpeerscaleid' => $ratingpeerscaleid);
        if (!has_capability('mod/peerforum:view', $context) ||
                !component_callback($component, 'ratingpeer_can_see_item_ratingpeers', array($callbackparams), true)) {
            throw new moodle_exception('noviewratingpeerpeer', 'ratingpeer');
        }

        list($context, $course, $cm) = get_context_info_array($context->id);

        // Can we see all ratingpeers?
        $canviewallratingpeers = has_capability('mod/peerforum:viewall', $context);

        // Create the Sql sort order string.
        switch ($params['sort']) {
            case 'firstname':
                $sqlsort = "u.firstname ASC";
                break;
            case 'ratingpeer':
                $sqlsort = "r.ratingpeer ASC";
                break;
            default:
                $sqlsort = "r.timemodified ASC";
        }

        $ratingpeeroptions = new stdClass;
        $ratingpeeroptions->context = $context;
        $ratingpeeroptions->component = $params['component'];
        $ratingpeeroptions->ratingpeerarea = $params['ratingpeerarea'];
        $ratingpeeroptions->itemid = $params['itemid'];
        $ratingpeeroptions->sort = $sqlsort;

        $pm = new ratingpeer_manager();
        $ratingpeers = $pm->get_all_ratingpeers_for_item($ratingpeeroptions);
        $scalemenu = make_grades_menu($params['ratingpeerscaleid']);

        // If the scale was changed after ratingpeers were submitted some ratingpeers may have a value above the current maximum.
        // We can't just do count($scalemenu) - 1 as custom scales start at index 1, not 0.
        $maxratingpeer = max(array_keys($scalemenu));

        $results = array();

        foreach ($ratingpeers as $ratingpeer) {
            if ($canviewallratingpeers || $USER->id == $ratingpeer->userid) {
                if ($ratingpeer->ratingpeer > $maxratingpeer) {
                    $ratingpeer->ratingpeer = $maxratingpeer;
                }

                // The ratingpeer object has all the required fields for generate the picture url.
                $userpicture = new user_picture($ratingpeer);
                $userpicture->size = 1; // Size f1.
                $profileimageurl = $userpicture->get_url($PAGE)->out(false);

                $result = array();
                $result['id'] = $ratingpeer->id;
                $result['userid'] = $ratingpeer->userid;
                $result['feedback'] = $ratingpeer->feedback;
                $result['userpictureurl'] = $profileimageurl;
                $result['userfullname'] = fullname($ratingpeer);
                $result['ratingpeer'] = $scalemenu[$ratingpeer->ratingpeer];
                $result['timemodified'] = $ratingpeer->timemodified;
                $results[] = $result;
            }
        }

        return array(
            'ratingpeers' => $results,
            'warnings' => $warnings
        );
    }

    /**
     * Returns description of get_item_ratingpeers result values.
     *
     * @return external_single_structure
     * @since Moodle 2.9
     */
    public static function get_item_ratingpeers_returns() {

        return new external_single_structure(
            array(
                'ratingpeers'    => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_INT,  'ratingpeer id'),
                            'userid'          => new external_value(PARAM_INT,  'user id'),
                            'userpictureurl'  => new external_value(PARAM_URL,  'URL user picture'),
                            'userfullname'    => new external_value(PARAM_NOTAGS, 'user fullname'),
                            'ratingpeer'          => new external_value(PARAM_NOTAGS, 'ratingpeer on scale'),
                            'feedback'          => new external_value(PARAM_TEXT, 'ratingpeerr feedback'),
                            'timemodified'    => new external_value(PARAM_INT,  'time modified (timestamp)')
                        ), 'ratingpeer'
                    ), 'list of ratingpeers'
                ),
                'warnings'  => new external_warnings(),
            )
        );
    }

}
