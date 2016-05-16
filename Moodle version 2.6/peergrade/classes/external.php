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
 * peergrade external API
 *
 * @package    core_peergrade
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/peergrade/lib.php");

/**
 * peergrade external functions
 *
 * @package    core_peergrade
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_peergrade_external extends external_api {

    /**
     * Returns description of get_item_peergrades parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_item_peergrades_parameters() {
        return new external_function_parameters (
            array(
                'contextlevel'  => new external_value(PARAM_ALPHA, 'context level: course, module, user, etc...'),
                'instanceid'    => new external_value(PARAM_INT, 'the instance id of item associated with the context level'),
                'component'     => new external_value(PARAM_COMPONENT, 'component'),
                'peergradearea'    => new external_value(PARAM_AREA, 'peergrade area'),
                'itemid'        => new external_value(PARAM_INT, 'associated id'),
                'scaleid'       => new external_value(PARAM_INT, 'scale id'),
                'peergradescaleid'       => new external_value(PARAM_INT, 'peergradescale id'),
                'sort'          => new external_value(PARAM_ALPHA, 'sort order (firstname, peergrade or timemodified)')
            )
        );
    }

    /**
     * Retrieve a list of peergrades for a given item (forum post etc)
     *
     * @param string $contextlevel course, module, user...
     * @param int $instanceid the instance if for the context element
     * @param string $component the name of the component
     * @param string $peergradearea peergrade area
     * @param int $itemid the item id
     * @param int $scaleid the scale id
     * @param string $sort sql order (firstname, peergrade or timemodified)
     * @return array Result and possible warnings
     * @throws moodle_exception
     * @since Moodle 2.9
     */
    public static function get_item_peergrades($contextlevel, $instanceid, $component, $peergradearea, $itemid, $scaleid, $sort) {
        global $USER, $PAGE;

        $warnings = array();

        $arrayparams = array(
            'contextlevel' => $contextlevel,
            'instanceid'   => $instanceid,
            'component'    => $component,
            'peergradearea'   => $peergradearea,
            'itemid'       => $itemid,
            'scaleid'      => $scaleid,
            'peergradescaleid'      => $peergradescaleid,
            'sort'         => $sort
        );

        // Validate and normalize parameters.
        $params = self::validate_parameters(self::get_item_peergrades_parameters(), $arrayparams);

        $context = self::get_context_from_params($params);
        self::validate_context($context);

        // Minimal capability required.
        $callbackparams = array('contextid' => $context->id,
                        'component' => $component,
                        'peergradearea' => $peergradearea,
                        'itemid' => $itemid,
                        'scaleid' => $scaleid,
                        'peergradescaleid' => $peergradescaleid);
        if (!has_capability('moodle/peergrade:view', $context) ||
                !component_callback($component, 'peergrade_can_see_item_peergrades', array($callbackparams), true)) {
            throw new moodle_exception('noviewrating', 'peergrade');
        }

        list($context, $course, $cm) = get_context_info_array($context->id);

        // Can we see all peergrades?
        $canviewallpeergrades = has_capability('moodle/peergrade:viewall', $context);

        // Create the Sql sort order string.
        switch ($params['sort']) {
            case 'firstname':
                $sqlsort = "u.firstname ASC";
                break;
            case 'peergrade':
                $sqlsort = "r.peergrade ASC";
                break;
            default:
                $sqlsort = "r.timemodified ASC";
        }

        $peergradeoptions = new stdClass;
        $peergradeoptions->context = $context;
        $peergradeoptions->component = $params['component'];
        $peergradeoptions->peergradearea = $params['peergradearea'];
        $peergradeoptions->itemid = $params['itemid'];
        $peergradeoptions->sort = $sqlsort;

        $pm = new peergrade_manager();
        $peergrades = $pm->get_all_peergrades_for_item($peergradeoptions);
        $scalemenu = make_grades_menu($params['peergradescaleid']);

        // If the scale was changed after peergrades were submitted some peergrades may have a value above the current maximum.
        // We can't just do count($scalemenu) - 1 as custom scales start at index 1, not 0.
        $maxpeergrade = max(array_keys($scalemenu));

        $results = array();

        foreach ($peergrades as $peergrade) {
            if ($canviewallpeergrades || $USER->id == $peergrade->userid) {
                if ($peergrade->peergrade > $maxpeergrade) {
                    $peergrade->peergrade = $maxpeergrade;
                }

                // The peergrade object has all the required fields for genepeergrade the picture url.
                $userpicture = new user_picture($peergrade);
                $userpicture->size = 1; // Size f1.
                $profileimageurl = $userpicture->get_url($PAGE)->out(false);

                $result = array();
                $result['id'] = $peergrade->id;
                $result['userid'] = $peergrade->userid;
                $result['feedback'] = $peergrade->feedback;
                $result['userpictureurl'] = $profileimageurl;
                $result['userfullname'] = fullname($peergrade);
                $result['peergrade'] = $scalemenu[$peergrade->peergrade];
                $result['timemodified'] = $peergrade->timemodified;
                $results[] = $result;
            }
        }

        return array(
            'peergrades' => $results,
            'warnings' => $warnings
        );
    }

    /**
     * Returns description of get_item_peergrades result values.
     *
     * @return external_single_structure
     * @since Moodle 2.9
     */
    public static function get_item_peergrades_returns() {

        return new external_single_structure(
            array(
                'peergrades'    => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'              => new external_value(PARAM_INT,  'peergrade id'),
                            'userid'          => new external_value(PARAM_INT,  'user id'),
                            'userpictureurl'  => new external_value(PARAM_URL,  'URL user picture'),
                            'userfullname'    => new external_value(PARAM_NOTAGS, 'user fullname'),
                            'peergrade'          => new external_value(PARAM_NOTAGS, 'peergrade on scale'),
                            'feedback'          => new external_value(PARAM_TEXT, 'peergrader feedback'),
                            'timemodified'    => new external_value(PARAM_INT,  'time modified (timestamp)')
                        ), 'peergrade'
                    ), 'list of peergrades'
                ),
                'warnings'  => new external_warnings(),
            )
        );
    }

}
