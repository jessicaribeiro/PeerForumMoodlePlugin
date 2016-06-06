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
 * A class representing a single ratingpeer and containing some static methods for manipulating ratingpeers
 *
 * @package    core_ratingpeer
 * @subpackage ratingpeer
 * @copyright  2010 Andrew Davis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('RATINGPEER_UNSET_RATINGPEER', -999);

define ('RATINGPEER_AGGREGATE_NONE', 0); //no ratingpeers
define ('RATINGPEER_AGGREGATE_AVERAGE', 1);
define ('RATINGPEER_AGGREGATE_COUNT', 2);
define ('RATINGPEER_AGGREGATE_MAXIMUM', 3);
define ('RATINGPEER_AGGREGATE_MINIMUM', 4);
define ('RATINGPEER_AGGREGATE_SUM', 5);

define ('RATINGPEER_DEFAULT_SCALE', 5);

/**
 * The ratingpeer class represents a single ratingpeer by a single user
 *
 * @package   core_ratingpeer
 * @category  ratingpeer
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class ratingpeer implements renderable {

    /**
     * @var stdClass The context in which this ratingpeer exists
     */
    public $context;

    /**
     * @var string The component using ratingpeers. For example "mod_forum"
     */
    public $component;

    /**
     * @var string The ratingpeer area to associate this ratingpeer with
     *             This allows a plugin to ratepeer more than one thing by specifying different ratingpeer areas
     */
    public $ratingpeerarea = null;

    /**
     * @var int The id of the item (forum post, glossary item etc) being ratedpeer
     */
    public $itemid;

    /**
     * @var int The id scale (1-5, 0-100) that was in use when the ratingpeer was submitted
     */
    public $scaleid;

    /**
     * @var int The id of the user who submitted the ratingpeer
     */
    public $userid;

    /**
     * @var stdclass settings for this ratingpeer. Necessary to render the ratingpeer.
     */
    public $settings;

    /**
     * @var int The Id of this ratingpeer within the ratingpeer table. This is only set if the ratingpeer already exists
     */
    public $id = null;

    /**
     * @var int The aggregate of the combined ratingpeers for the associated item. This is only set if the ratingpeer already exists
     */
    public $aggregate = null;

    /**
     * @var int The total number of ratingpeers for the associated item. This is only set if the ratingpeer already exists
     */
    public $count = 0;

    /**
     * @var int The ratingpeer the associated user gave the associated item. This is only set if the ratingpeer already exists
     */
    public $ratingpeer = null;

    /**
     * @var int The time the associated item was created
     */
    public $itemtimecreated = null;

    /**
     * @var int The id of the user who submitted the ratingpeer
     */
    public $itemuserid = null;

    /**
     * Constructor.
     *
     * @param stdClass $options {
     *            context => context context to use for the ratingpeer [required]
     *            component => component using ratingpeers ie mod_forum [required]
     *            ratingpeerarea => ratingpeerarea to associate this ratingpeer with [required]
     *            itemid  => int the id of the associated item (forum post, glossary item etc) [required]
     *            scaleid => int The scale in use when the ratingpeer was submitted [required]
     *            userid  => int The id of the user who submitted the ratingpeer [required]
     *            settings => Settings for the ratingpeer object [optional]
     *            id => The id of this ratingpeer (if the ratingpeer is from the db) [optional]
     *            aggregate => The aggregate for the ratingpeer [optional]
     *            count => The number of ratingpeers [optional]
     *            ratingpeer => The ratingpeer given by the user [optional]
     * }
     */
    public function __construct($options) {
        $this->context =    $options->context;
        $this->component =  $options->component;
        $this->ratingpeerarea = $options->ratingpeerarea;
        $this->itemid =     $options->itemid;
        $this->scaleid =    $options->scaleid;
        $this->userid =     $options->userid;

        if (isset($options->settings)) {
            $this->settings = $options->settings;
        }
        if (isset($options->id)) {
            $this->id = $options->id;
        }
        if (isset($options->aggregate)) {
            $this->aggregate = $options->aggregate;
        }
        if (isset($options->count)) {
            $this->count = $options->count;
        }
        if (isset($options->ratingpeer)) {
            $this->ratingpeer = $options->ratingpeer;
        }
    }

    /**
     * Update this ratingpeer in the database
     *
     * @param int $ratingpeer the integer value of this ratingpeer
     */
    public function update_ratingpeer($ratingpeer) {
        global $DB;

        $time = time();

        $data = new stdClass;
        $data->ratingpeer       = $ratingpeer;
        $data->timemodified = $time;

        $item = new stdclass();
        $item->id = $this->itemid;
        $items = array($item);

        $ratingpeeroptions = new stdClass;
        $ratingpeeroptions->context = $this->context;
        $ratingpeeroptions->component = $this->component;
        $ratingpeeroptions->ratingpeerarea = $this->ratingpeerarea;
        $ratingpeeroptions->items = $items;
        $ratingpeeroptions->aggregate = RATINGPEER_AGGREGATE_AVERAGE;//we dont actually care what aggregation method is applied
        $ratingpeeroptions->scaleid = $this->scaleid;
        $ratingpeeroptions->userid = $this->userid;

        $rm = new ratingpeer_manager();
        $items = $rm->get_ratingpeers($ratingpeeroptions);
        $firstitem = $items[0]->ratingpeer;

        if (empty($firstitem->id)) {
            // Insert a new ratingpeer
            $data->contextid    = $this->context->id;
            $data->component    = $this->component;
            $data->ratingpeerarea   = $this->ratingpeerarea;
            $data->ratingpeer       = $ratingpeer;
            $data->scaleid      = $this->scaleid;
            $data->userid       = $this->userid;
            $data->itemid       = $this->itemid;
            $data->timecreated  = $time;
            $data->timemodified = $time;
            $DB->insert_record('peerforum_ratingpeer', $data);
        } else {
            // Update the ratingpeer
            $data->id           = $firstitem->id;
            $DB->update_record('peerforum_ratingpeer', $data);
        }
    }

    /**
     * Retreive the integer value of this ratingpeer
     *
     * @return int the integer value of this ratingpeer object
     */
    public function get_ratingpeer() {
        return $this->ratingpeer;
    }

    /**
     * Returns this ratingpeers aggregate value as a string.
     *
     * @return string ratingpeers aggregate value
     */
    public function get_aggregate_string() {

        $aggregate = $this->aggregate;
        $method = $this->settings->aggregationmethod;

        // only display aggregate if aggregation method isn't COUNT
        $aggregatestr = '';
        if ($aggregate && $method != RATINGPEER_AGGREGATE_COUNT) {
            if ($method != RATINGPEER_AGGREGATE_SUM && !$this->settings->scale->isnumeric) {
                $aggregatestr .= $this->settings->scale->scaleitems[round($aggregate)]; //round aggregate as we're using it as an index
            } else { // aggregation is SUM or the scale is numeric
                $aggregatestr .= round($aggregate, 1);
            }
        }

        return $aggregatestr;
    }

    /**
     * Returns true if the user is able to ratepeer this ratingpeer object
     *
     * @param int $userid Current user assumed if left empty
     * @return bool true if the user is able to ratepeer this ratingpeer object
     */
    public function user_can_ratepeer($userid = null) {
        if (empty($userid)) {
            global $USER;
            $userid = $USER->id;
        }
        // You can't ratepeer your item
        if ($this->itemuserid == $userid) {
            return false;
        }
        // You can't ratepeer if you don't have the system cap
        if (!$this->settings->permissions->ratepeer) {
            return false;
        }
        // You can't ratepeer if you don't have the plugin cap
        if (!$this->settings->pluginpermissions->ratepeer) {
            return false;
        }

        // You can't ratepeer if the item was outside of the assessment times
        $timestart = $this->settings->assesstimestart;
        $timefinish = $this->settings->assesstimefinish;
        $timecreated = $this->itemtimecreated;
        if (!empty($timestart) && !empty($timefinish) && ($timecreated < $timestart || $timecreated > $timefinish)) {
            return false;
        }
        return true;
    }

    /**
     * Returns true if the user is able to view the aggregate for this ratingpeer object.
     *
     * @param int|null $userid If left empty the current user is assumed.
     * @return bool true if the user is able to view the aggregate for this ratingpeer object
     */
    public function user_can_view_aggregate($userid = null) {
        if (empty($userid)) {
            global $USER;
            $userid = $USER->id;
        }

        // if the item doesnt belong to anyone or its another user's items and they can see the aggregate on items they don't own
        // Note that viewany doesnt mean you can see the aggregate or ratingpeers of your own items
        if ((empty($this->itemuserid) or $this->itemuserid != $userid) && $this->settings->permissions->viewany && $this->settings->pluginpermissions->viewany ) {
            return true;
        }

        // if its the current user's item and they have permission to view the aggregate on their own items
        if ($this->itemuserid == $userid && $this->settings->permissions->view && $this->settings->pluginpermissions->view) {
            return true;
        }

        return false;
    }

    /**
     * Returns a URL to view all of the ratingpeers for the item this ratingpeer is for.
     *
     * If this is a ratingpeer of a post then this URL will take the user to a page that shows all of the ratingpeers for the post
     * (this one included).
     *
     * @param bool $popup whether of not the URL should be loaded in a popup
     * @return moodle_url URL to view all of the ratingpeers for the item this ratingpeer is for.
     */
    public function get_view_ratingpeers_url($popup = false) {
        $attributes = array(
            'contextid'  => $this->context->id,
            'component'  => $this->component,
            'ratingpeerarea' => $this->ratingpeerarea,
            'itemid'     => $this->itemid,
            'scaleid'    => $this->settings->scale->id
        );
        if ($popup) {
            $attributes['popup'] = 1;
        }
        return new moodle_url('/ratingpeer/index.php', $attributes);
    }

    /**
     * Returns a URL that can be used to ratepeer the associated item.
     *
     * @param int|null          $ratingpeer    The ratingpeer to give the item, if null then no ratingpeer param is added.
     * @param moodle_url|string $returnurl The URL to return to.
     * @return moodle_url can be used to ratepeer the associated item.
     */
    public function get_ratepeer_url($ratingpeer = null, $returnurl = null) {
        if (empty($returnurl)) {
            if (!empty($this->settings->returnurl)) {
                $returnurl = $this->settings->returnurl;
            } else {
                global $PAGE;
                $returnurl = $PAGE->url;
            }
        }
        $args = array(
            'contextid'   => $this->context->id,
            'component'   => $this->component,
            'ratingpeerarea'  => $this->ratingpeerarea,
            'itemid'      => $this->itemid,
            'scaleid'     => $this->settings->scale->id,
            'returnurl'   => $returnurl,
            'ratedpeeruserid' => $this->itemuserid,
            'aggregation' => $this->settings->aggregationmethod,
            'sesskey'     => sesskey()
        );
        if (!empty($ratingpeer)) {
            $args['ratingpeer'] = $ratingpeer;
        }
        $url = new moodle_url('/ratingpeer/ratepeer.php', $args);
        return $url;
    }

    /**
    * Remove this ratingpeer from the database
    * @return void
    */
    //public function delete_ratingpeer() {
        //todo implement this if its actually needed
    //}
} //end ratingpeer class definition

/**
 * The ratingpeer_manager class provides the ability to retrieve sets of ratingpeers from the database
 *
 * @package   core_ratingpeer
 * @category  ratingpeer
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class ratingpeer_manager {

    /**
     * @var array An array of calculated scale options to save us generatingpeer them for each request.
     */
    protected $scales = array();

    /**
     * Delete one or more ratingpeers. Specify either a ratingpeer id, an item id or just the context id.
     *
     * @global moodle_database $DB
     * @param stdClass $options {
     *            contextid => int the context in which the ratingpeers exist [required]
     *            ratingpeerid => int the id of an individual ratingpeer to delete [optional]
     *            userid => int delete the ratingpeers submitted by this user. May be used in conjuction with itemid [optional]
     *            itemid => int delete all ratingpeers attached to this item [optional]
     *            component => string The component to delete ratingpeers from [optional]
     *            ratingpeerarea => string The ratingpeerarea to delete ratingpeers from [optional]
     * }
     */
    public function delete_ratingpeers($options) {
        global $DB;

        if (empty($options->contextid)) {
            throw new coding_exception('The context option is a required option when deleting ratingpeers.');
        }

        $conditions = array('contextid' => $options->contextid);
        $possibleconditions = array(
            'ratingpeerid'   => 'id',
            'userid'     => 'userid',
            'itemid'     => 'itemid',
            'component'  => 'component',
            'ratingpeerarea' => 'ratingpeerarea'
        );
        foreach ($possibleconditions as $option => $field) {
            if (isset($options->{$option})) {
                $conditions[$field] = $options->{$option};
            }
        }
        $DB->delete_records('peerforum_ratingpeer', $conditions);
    }

    /**
     * Returns an array of ratingpeers for a given item (forum post, glossary entry etc). This returns all users ratingpeers for a single item
     *
     * @param stdClass $options {
     *            context => context the context in which the ratingpeers exists [required]
     *            component => component using ratingpeers ie mod_forum [required]
     *            ratingpeerarea => ratingpeerarea to associate this ratingpeer with [required]
     *            itemid  =>  int the id of the associated item (forum post, glossary item etc) [required]
     *            sort    => string SQL sort by clause [optional]
     * }
     * @return array an array of ratingpeers
     */
    public function get_all_ratingpeers_for_item($options) {
        global $DB;

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when getting ratingpeers for an item.');
        }
        if (!isset($options->itemid)) {
            throw new coding_exception('The itemid option is a required option when getting ratingpeers for an item.');
        }
        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when getting ratingpeers for an item.');
        }
        if (!isset($options->ratingpeerarea)) {
            throw new coding_exception('The ratingpeerarea option is now a required option when getting ratingpeers for an item.');
        }

        $sortclause = '';
        if( !empty($options->sort) ) {
            $sortclause = "ORDER BY $options->sort";
        }

        $params = array(
            'contextid'  => $options->context->id,
            'itemid'     => $options->itemid,
            'component'  => $options->component,
            'ratingpeerarea' => $options->ratingpeerarea,
        );
        $userfields = user_picture::fields('u', null, 'userid');
        $sql = "SELECT r.id, r.ratingpeer, r.itemid, r.userid, r.timemodified, r.component, r.ratingpeerarea, $userfields
                  FROM {peerforum_ratingpeer} r
             LEFT JOIN {user} u ON r.userid = u.id
                 WHERE r.contextid = :contextid AND
                       r.itemid  = :itemid AND
                       r.component = :component AND
                       r.ratingpeerarea = :ratingpeerarea
                       {$sortclause}";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Adds ratingpeer objects to an array of items (forum posts, glossary entries etc). Rating objects are available at $item->ratingpeer
     *
     * @param stdClass $options {
     *            context          => context the context in which the ratingpeers exists [required]
     *            component        => the component name ie mod_forum [required]
     *            ratingpeerarea       => the ratingpeerarea we are interested in [required]
     *            items            => array an array of items such as forum posts or glossary items. They must have an 'id' member ie $items[0]->id[required]
     *            aggregate        => int what aggregation method should be applied. RATINGPEER_AGGREGATE_AVERAGE, RATINGPEER_AGGREGATE_MAXIMUM etc [required]
     *            scaleid          => int the scale from which the user can select a ratingpeer [required]
     *            userid           => int the id of the current user [optional]
     *            returnurl        => string the url to return the user to after submitting a ratingpeer. Can be left null for ajax requests [optional]
     *            assesstimestart  => int only allow ratingpeer of items created after this timestamp [optional]
     *            assesstimefinish => int only allow ratingpeer of items created before this timestamp [optional]
     * @return array the array of items with their ratingpeers attached at $items[0]->ratingpeer
     */
    public function get_ratingpeers($options) {
        global $DB, $USER;

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when getting ratingpeers.');
        }

        if (!isset($options->component)) {
            throw new coding_exception('The component option is a required option when getting ratingpeers.');
        }

        if (!isset($options->ratingpeerarea)) {
            throw new coding_exception('The ratingpeerarea option is a required option when getting ratingpeers.');
        }

        if (!isset($options->scaleid)) {
            throw new coding_exception('The scaleid option is a required option when getting ratingpeers.');
        }

        if (!isset($options->items)) {
            throw new coding_exception('The items option is a required option when getting ratingpeers.');
        } else if (empty($options->items)) {
            return array();
        }

        if (!isset($options->aggregate)) {
            throw new coding_exception('The aggregate option is a required option when getting ratingpeers.');
        } else if ($options->aggregate == RATINGPEER_AGGREGATE_NONE) {
            // Ratings arn't enabled.
            return $options->items;
        }
        $aggregatestr = $this->get_aggregation_method($options->aggregate);

        // Default the userid to the current user if it is not set
        if (empty($options->userid)) {
            $userid = $USER->id;
        } else {
            $userid = $options->userid;
        }

        // Get the item table name, the item id field, and the item user field for the given ratingpeer item
        // from the related component.
        list($type, $name) = core_component::normalize_component($options->component);
        $default = array(null, 'id', 'userid');
        list($itemtablename, $itemidcol, $itemuseridcol) = plugin_callback($type, $name, 'ratingpeer', 'get_item_fields', array($options), $default);

        // Create an array of item ids
        $itemids = array();
        foreach ($options->items as $item) {
            $itemids[] = $item->{$itemidcol};
        }

        // get the items from the database
        list($itemidtest, $params) = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $params['contextid'] = $options->context->id;
        $params['userid']    = $userid;
        $params['component']    = $options->component;
        $params['ratingpeerarea'] = $options->ratingpeerarea;

        $sql = "SELECT r.id, r.itemid, r.userid, r.scaleid, r.ratingpeer AS usersratingpeer
                  FROM {peerforum_ratingpeer} r
                 WHERE r.userid = :userid AND
                       r.contextid = :contextid AND
                       r.itemid {$itemidtest} AND
                       r.component = :component AND
                       r.ratingpeerarea = :ratingpeerarea
              ORDER BY r.itemid";
        $userratingpeers = $DB->get_records_sql($sql, $params);

        $sql = "SELECT r.itemid, $aggregatestr(r.ratingpeer) AS aggrratingpeer, COUNT(r.ratingpeer) AS numratingpeers
                  FROM {peerforum_ratingpeer} r
                 WHERE r.contextid = :contextid AND
                       r.itemid {$itemidtest} AND
                       r.component = :component AND
                       r.ratingpeerarea = :ratingpeerarea
              GROUP BY r.itemid, r.component, r.ratingpeerarea, r.contextid
              ORDER BY r.itemid";
        $aggregateratingpeers = $DB->get_records_sql($sql, $params);

        $ratingpeeroptions = new stdClass;
        $ratingpeeroptions->context = $options->context;
        $ratingpeeroptions->component = $options->component;
        $ratingpeeroptions->ratingpeerarea = $options->ratingpeerarea;
        $ratingpeeroptions->settings = $this->generatepeer_ratingpeer_settings_object($options);
        foreach ($options->items as $item) {
            $founduserratingpeer = false;
            foreach($userratingpeers as $userratingpeer) {
                //look for an existing ratingpeer from this user of this item
                if ($item->{$itemidcol} == $userratingpeer->itemid) {
                    // Note: rec->scaleid = the id of scale at the time the ratingpeer was submitted
                    // may be different from the current scale id
                    $ratingpeeroptions->scaleid = $userratingpeer->scaleid;
                    $ratingpeeroptions->userid = $userratingpeer->userid;
                    $ratingpeeroptions->id = $userratingpeer->id;
                    $ratingpeeroptions->ratingpeer = min($userratingpeer->usersratingpeer, $ratingpeeroptions->settings->scale->max);

                    $founduserratingpeer = true;
                    break;
                }
            }
            if (!$founduserratingpeer) {
                $ratingpeeroptions->scaleid = null;
                $ratingpeeroptions->userid = null;
                $ratingpeeroptions->id = null;
                $ratingpeeroptions->ratingpeer =  null;
            }

            if (array_key_exists($item->{$itemidcol}, $aggregateratingpeers)) {
                $rec = $aggregateratingpeers[$item->{$itemidcol}];
                $ratingpeeroptions->itemid = $item->{$itemidcol};
                $ratingpeeroptions->aggregate = min($rec->aggrratingpeer, $ratingpeeroptions->settings->scale->max);
                $ratingpeeroptions->count = $rec->numratingpeers;
            } else {
                $ratingpeeroptions->itemid = $item->{$itemidcol};
                $ratingpeeroptions->aggregate = null;
                $ratingpeeroptions->count = 0;
            }

            $ratingpeer = new ratingpeer($ratingpeeroptions);
            $ratingpeer->itemtimecreated = $this->get_item_time_created($item);
            if (!empty($item->{$itemuseridcol})) {
                $ratingpeer->itemuserid = $item->{$itemuseridcol};
            }
            $item->ratingpeer = $ratingpeer;
        }

        return $options->items;
    }

    /**
     * Generatepeers a ratingpeer settings object based upon the options it is provided.
     *
     * @param stdClass $options {
     *      context           => context the context in which the ratingpeers exists [required]
     *      component         => string The component the items belong to [required]
     *      ratingpeerarea        => string The ratingpeerarea the items belong to [required]
     *      aggregate         => int what aggregation method should be applied. RATINGPEER_AGGREGATE_AVERAGE, RATINGPEER_AGGREGATE_MAXIMUM etc [required]
     *      scaleid           => int the scale from which the user can select a ratingpeer [required]
     *      returnurl         => string the url to return the user to after submitting a ratingpeer. Can be left null for ajax requests [optional]
     *      assesstimestart   => int only allow ratingpeer of items created after this timestamp [optional]
     *      assesstimefinish  => int only allow ratingpeer of items created before this timestamp [optional]
     *      plugintype        => string plugin type ie 'mod' Used to find the permissions callback [optional]
     *      pluginname        => string plugin name ie 'forum' Used to find the permissions callback [optional]
     * }
     * @return stdClass ratingpeer settings object
     */
    protected function generatepeer_ratingpeer_settings_object($options) {

        if (!isset($options->context)) {
            throw new coding_exception('The context option is a required option when generatingpeer a ratingpeer settings object.');
        }
        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when generatingpeer a ratingpeer settings object.');
        }
        if (!isset($options->ratingpeerarea)) {
            throw new coding_exception('The ratingpeerarea option is now a required option when generatingpeer a ratingpeer settings object.');
        }
        if (!isset($options->aggregate)) {
            throw new coding_exception('The aggregate option is now a required option when generatingpeer a ratingpeer settings object.');
        }
        if (!isset($options->scaleid)) {
            throw new coding_exception('The scaleid option is now a required option when generatingpeer a ratingpeer settings object.');
        }

        // settings that are common to all ratingpeers objects in this context
        $settings = new stdClass;
        $settings->scale             = $this->generatepeer_ratingpeer_scale_object($options->scaleid); // the scale to use now
        $settings->aggregationmethod = $options->aggregate;
        $settings->assesstimestart   = null;
        $settings->assesstimefinish  = null;

        // Collect options into the settings object
        if (!empty($options->assesstimestart)) {
            $settings->assesstimestart = $options->assesstimestart;
        }
        if (!empty($options->assesstimefinish)) {
            $settings->assesstimefinish = $options->assesstimefinish;
        }
        if (!empty($options->returnurl)) {
            $settings->returnurl = $options->returnurl;
        }

        // check site capabilities
        $settings->permissions = new stdClass;
        $settings->permissions->view    = has_capability('mod/peerforum:viewratingpeer', $options->context); // can view the aggregate of ratingpeers of their own items
        $settings->permissions->viewany = has_capability('mod/peerforum:viewanyratingpeer', $options->context); // can view the aggregate of ratingpeers of other people's items
        $settings->permissions->viewall = has_capability('mod/peerforum:viewallratingpeer', $options->context); // can view individual ratingpeers
        $settings->permissions->ratepeer    = has_capability('mod/peerforum:rateratingpeer', $options->context); // can submit ratingpeers

        // check module capabilities (mostly for backwards compatability with old modules that previously implemented their own ratingpeers)
        $pluginpermissionsarray = $this->get_plugin_permissions_array($options->context->id, $options->component, $options->ratingpeerarea);
        $settings->pluginpermissions = new stdClass;
        $settings->pluginpermissions->view    = $pluginpermissionsarray['view'];
        $settings->pluginpermissions->viewany = $pluginpermissionsarray['viewany'];
        $settings->pluginpermissions->viewall = $pluginpermissionsarray['viewall'];
        $settings->pluginpermissions->ratepeer    = $pluginpermissionsarray['ratepeer'];

        return $settings;
    }

    /**
     * Generatepeers a scale object that can be returned
     *
     * @global moodle_database $DB moodle database object
     * @param int $scaleid scale-type identifier
     * @return stdClass scale for ratingpeers
     */
    protected function generatepeer_ratingpeer_scale_object($scaleid) {
        global $DB;
        if (!array_key_exists('s'.$scaleid, $this->scales)) {
            $scale = new stdClass;
            $scale->id = $scaleid;
            $scale->name = null;
            $scale->courseid = null;
            $scale->scaleitems = array();
            $scale->isnumeric = true;
            $scale->max = $scaleid;

            if ($scaleid < 0) {
                // It is a proper scale (not numeric)
                $scalerecord = $DB->get_record('scale', array('id' => abs($scaleid)));
                if ($scalerecord) {
                    // We need to generatepeer an array with string keys starting at 1
                    $scalearray = explode(',', $scalerecord->scale);
                    $c = count($scalearray);
                    for ($i = 0; $i < $c; $i++) {
                        // treat index as a string to allow sorting without changing the value
                        $scale->scaleitems[(string)($i + 1)] = $scalearray[$i];
                    }
                    krsort($scale->scaleitems); // have the highest grade scale item appear first
                    $scale->isnumeric = false;
                    $scale->name = $scalerecord->name;
                    $scale->courseid = $scalerecord->courseid;
                    $scale->max = count($scale->scaleitems);
                }
            } else {
                //generatepeer an array of values for numeric scales
                for($i = 0; $i <= (int)$scaleid; $i++) {
                    $scale->scaleitems[(string)$i] = $i;
                }
            }
            $this->scales['s'.$scaleid] = $scale;
        }
        return $this->scales['s'.$scaleid];
    }

    /**
     * Gets the time the given item was created
     *
     * TODO: MDL-31511 - Find a better solution for this, its not ideal to test for fields really we should be
     * asking the component the item belongs to what field to look for or even the value we
     * are looking for.
     *
     * @param stdClass $item
     * @return int|null return null if the created time is unavailable, otherwise return a timestamp
     */
    protected function get_item_time_created($item) {
        if( !empty($item->created) ) {
            return $item->created;//the forum_posts table has created instead of timecreated
        }
        else if(!empty($item->timecreated)) {
            return $item->timecreated;
        }
        else {
            return null;
        }
    }

    /**
     * Returns an array of grades calculated by aggregating item ratingpeers.
     *
     * @param stdClass $options {
     *            userid => int the id of the user whose items have been ratedpeer. NOT the user who submitted the ratingpeers. 0 to update all. [required]
     *            aggregationmethod => int the aggregation method to apply when calculating grades ie RATINGPEER_AGGREGATE_AVERAGE [required]
     *            scaleid => int the scale from which the user can select a ratingpeer. Used for bounds checking. [required]
     *            itemtable => int the table containing the items [required]
     *            itemtableusercolum => int the column of the user table containing the item owner's user id [required]
     *            component => The component for the ratingpeers [required]
     *            ratingpeerarea => The ratingpeerarea for the ratingpeers [required]
     *            contextid => int the context in which the ratedpeer items exist [optional]
     *            modulename => string the name of the module [optional]
     *            moduleid => int the id of the module instance [optional]
     * }
     * @return array the array of the user's grades
     */
    public function get_user_grades($options) {
        global $DB;

        $contextid = null;

        if (!isset($options->component)) {
            throw new coding_exception('The component option is now a required option when getting user grades from ratingpeers.');
        }
        if (!isset($options->ratingpeerarea)) {
            throw new coding_exception('The ratingpeerarea option is now a required option when getting user grades from ratingpeers.');
        }

        //if the calling code doesn't supply a context id we'll have to figure it out
        if( !empty($options->contextid) ) {
            $contextid = $options->contextid;
        }
        else if( !empty($options->cmid) ) {
            //not implemented as not currently used although cmid is potentially available (the forum supplies it)
            //Is there a convenient way to get a context id from a cm id?
            //$cmidnumber = $options->cmidnumber;
        }
        else if ( !empty($options->modulename) && !empty($options->moduleid) ) {
            $modulename = $options->modulename;
            $moduleid   = intval($options->moduleid);

            // Going direct to the db for the context id seems wrong.
            $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
            $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel)";
            $sql = "SELECT cm.* $ctxselect
                      FROM {course_modules} cm
                 LEFT JOIN {modules} mo ON mo.id = cm.module
                 LEFT JOIN {{$modulename}} m ON m.id = cm.instance $ctxjoin
                     WHERE mo.name=:modulename AND
                           m.id=:moduleid";
            $params = array('modulename' => $modulename, 'moduleid' => $moduleid, 'contextlevel' => CONTEXT_MODULE);
            $contextrecord = $DB->get_record_sql($sql, $params, '*', MUST_EXIST);
            $contextid = $contextrecord->ctxid;
        }

        $params = array();
        $params['contextid']  = $contextid;
        $params['component']  = $options->component;
        $params['ratingpeerarea'] = $options->ratingpeerarea;
        $itemtable            = $options->itemtable;
        $itemtableusercolumn  = $options->itemtableusercolumn;
        $scaleid              = $options->scaleid;
        $aggregationstring    = $this->get_aggregation_method($options->aggregationmethod);

        //if userid is not 0 we only want the grade for a single user
        $singleuserwhere = '';
        if ($options->userid != 0) {
            $params['userid1'] = intval($options->userid);
            $singleuserwhere = "AND i.{$itemtableusercolumn} = :userid1";
        }

        //MDL-24648 The where line used to be "WHERE (r.contextid is null or r.contextid=:contextid)"
        //r.contextid will be null for users who haven't been ratedpeer yet
        //no longer including users who haven't been ratedpeer to reduce memory requirements
        $sql = "SELECT u.id as id, u.id AS userid, $aggregationstring(r.ratingpeer) AS rawgrade
                  FROM {user} u
             LEFT JOIN {{$itemtable}} i ON u.id=i.{$itemtableusercolumn}
             LEFT JOIN {peerforum_ratingpeer} r ON r.itemid=i.id
                 WHERE r.contextid = :contextid AND
                       r.component = :component AND
                       r.ratingpeerarea = :ratingpeerarea
                       $singleuserwhere
              GROUP BY u.id";
        $results = $DB->get_records_sql($sql, $params);

        if ($results) {

            $scale = null;
            $max = 0;
            if ($options->scaleid >= 0) {
                //numeric
                $max = $options->scaleid;
            } else {
                //custom scales
                $scale = $DB->get_record('scale', array('id' => -$options->scaleid));
                if ($scale) {
                    $scale = explode(',', $scale->scale);
                    $max = count($scale);
                } else {
                    debugging('ratingpeer_manager::get_user_grades() received a scale ID that doesnt exist');
                }
            }

            // it could throw off the grading if count and sum returned a rawgrade higher than scale
            // so to prevent it we review the results and ensure that rawgrade does not exceed the scale, if it does we set rawgrade = scale (i.e. full credit)
            foreach ($results as $rid=>$result) {
                if ($options->scaleid >= 0) {
                    //numeric
                    if ($result->rawgrade > $options->scaleid) {
                        $results[$rid]->rawgrade = $options->scaleid;
                    }
                } else {
                    //scales
                    if (!empty($scale) && $result->rawgrade > $max) {
                        $results[$rid]->rawgrade = $max;
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Returns array of aggregate types. Used by ratingpeers.
     *
     * @return array aggregate types
     */
    public function get_aggregate_types() {
        return array (RATINGPEER_AGGREGATE_NONE     => get_string('aggregatenonerate', 'peerforum'),
                      RATINGPEER_AGGREGATE_AVERAGE  => get_string('aggregateavgrate', 'peerforum'),
                      RATINGPEER_AGGREGATE_COUNT    => get_string('aggregatecountrate', 'peerforum'),
                      RATINGPEER_AGGREGATE_MAXIMUM  => get_string('aggregatemaxrate', 'peerforum'),
                      RATINGPEER_AGGREGATE_MINIMUM  => get_string('aggregateminrate', 'peerforum'),
                      RATINGPEER_AGGREGATE_SUM      => get_string('aggregatesumrate', 'peerforum'));
    }

    /**
     * Converts an aggregation method constant into something that can be included in SQL
     *
     * @param int $aggregate An aggregation constant. For example, RATINGPEER_AGGREGATE_AVERAGE.
     * @return string an SQL aggregation method
     */
    public function get_aggregation_method($aggregate) {
        $aggregatestr = null;
        switch($aggregate){
            case RATINGPEER_AGGREGATE_AVERAGE:
                $aggregatestr = 'AVG';
                break;
            case RATINGPEER_AGGREGATE_COUNT:
                $aggregatestr = 'COUNT';
                break;
            case RATINGPEER_AGGREGATE_MAXIMUM:
                $aggregatestr = 'MAX';
                break;
            case RATINGPEER_AGGREGATE_MINIMUM:
                $aggregatestr = 'MIN';
                break;
            case RATINGPEER_AGGREGATE_SUM:
                $aggregatestr = 'SUM';
                break;
            default:
                $aggregatestr = 'AVG'; // Default to this to avoid real breakage - MDL-22270
                debugging('Incorrect call to get_aggregation_method(), was called with incorrect aggregate method ' . $aggregate, DEBUG_DEVELOPER);
        }
        return $aggregatestr;
    }

    /**
     * Looks for a callback like forum_ratingpeer_permissions() to retrieve permissions from the plugin whose items are being ratedpeer
     *
     * @param int $contextid The current context id
     * @param string $component the name of the component that is using ratingpeers ie 'mod_forum'
     * @param string $ratingpeerarea The area the ratingpeer is associated with
     * @return array ratingpeer related permissions
     */
    public function get_plugin_permissions_array($contextid, $component, $ratingpeerarea) {
        $pluginpermissionsarray = null;
        $defaultpluginpermissions = array('ratepeer'=>false,'view'=>false,'viewany'=>false,'viewall'=>false);//deny by default
        if (!empty($component)) {
            list($type, $name) = core_component::normalize_component($component);
            $pluginpermissionsarray = plugin_callback($type, $name, 'ratingpeer', 'permissions', array($contextid, $component, $ratingpeerarea), $defaultpluginpermissions);
        } else {
            $pluginpermissionsarray = $defaultpluginpermissions;
        }
        return $pluginpermissionsarray;
    }

    /**
     * Validates a submitted ratingpeer
     *
     * @param array $params submitted data
     *            context => object the context in which the ratedpeer items exists [required]
     *            component => The component the ratingpeer belongs to [required]
     *            ratingpeerarea => The ratingpeerarea the ratingpeer is associated with [required]
     *            itemid => int the ID of the object being ratedpeer [required]
     *            scaleid => int the scale from which the user can select a ratingpeer. Used for bounds checking. [required]
     *            ratingpeer => int the submitted ratingpeer
     *            ratedpeeruserid => int the id of the user whose items have been ratedpeer. NOT the user who submitted the ratingpeers. 0 to update all. [required]
     *            aggregation => int the aggregation method to apply when calculating grades ie RATINGPEER_AGGREGATE_AVERAGE [optional]
     * @return boolean true if the ratingpeer is valid. False if callback wasnt found and will throw ratingpeer_exception if ratingpeer is invalid
     */
    public function check_ratingpeer_is_valid($params) {

        if (!isset($params['context'])) {
            throw new coding_exception('The context option is a required option when checking ratingpeer validity.');
        }
        if (!isset($params['component'])) {
            throw new coding_exception('The component option is now a required option when checking ratingpeer validity');
        }
        if (!isset($params['ratingpeerarea'])) {
            throw new coding_exception('The ratingpeerarea option is now a required option when checking ratingpeer validity');
        }
        if (!isset($params['itemid'])) {
            throw new coding_exception('The itemid option is now a required option when checking ratingpeer validity');
        }
        if (!isset($params['scaleid'])) {
            throw new coding_exception('The scaleid option is now a required option when checking ratingpeer validity');
        }
        if (!isset($params['ratedpeeruserid'])) {
            throw new coding_exception('The ratedpeeruserid option is now a required option when checking ratingpeer validity');
        }

        list($plugintype, $pluginname) = core_component::normalize_component($params['component']);

        //this looks for a function like forum_ratingpeer_validate() in mod_forum lib.php
        //wrapping the params array in another array as call_user_func_array() expands arrays into multiple arguments
        $isvalid = plugin_callback($plugintype, $pluginname, 'ratingpeer', 'validate', array($params), null);

        //if null then the callback doesn't exist
        if ($isvalid === null) {
            $isvalid = false;
            debugging('ratingpeer validation callback not found for component '.  clean_param($component, PARAM_ALPHANUMEXT));
        }
        return $isvalid;
    }

    /**
     * Initialises JavaScript to enable AJAX ratingpeers on the provided page
     *
     * @param moodle_page $page
     * @return true always returns true
     */
    public function initialise_ratingpeer_javascript(moodle_page $page) {
        global $CFG;

        //only needs to be initialized once
        static $done = false;
        if ($done) {
            return true;
        }

        if (!empty($CFG->enableajax)) {
            $page->requires->js_init_call('M.core_ratingpeer.init');
        }
        $done = true;

        return true;
    }

    /**
     * Returns a string that describes the aggregation method that was provided.
     *
     * @param string $aggregationmethod
     * @return string describes the aggregation method that was provided
     */
    public function get_aggregate_label($aggregationmethod) {
        $aggregatelabel = '';
        switch ($aggregationmethod) {
            case RATINGPEER_AGGREGATE_AVERAGE :
                $aggregatelabel .= get_string("aggregateavgrate", "peerforum");
                break;
            case RATINGPEER_AGGREGATE_COUNT :
                $aggregatelabel .= get_string("aggregatecountrate", "peerforum");
                break;
            case RATINGPEER_AGGREGATE_MAXIMUM :
                $aggregatelabel .= get_string("aggregatemaxrate", "peerforum");
                break;
            case RATINGPEER_AGGREGATE_MINIMUM :
                $aggregatelabel .= get_string("aggregateminrate", "peerforum");
                break;
            case RATINGPEER_AGGREGATE_SUM :
                $aggregatelabel .= get_string("aggregatesumrate", "peerforum");
                break;
        }
        $aggregatelabel .= get_string('labelsep', 'langconfig');
        return $aggregatelabel;
    }

}//end ratingpeer_manager class definition

/**
 * The ratingpeer_exception class provides the ability to generatepeer exceptions that can be easily identified as coming from the ratingpeers system
 *
 * @package   core_ratingpeer
 * @category  ratingpeer
 * @copyright 2010 Andrew Davis
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class ratingpeer_exception extends moodle_exception {
    /**
     * @var string The message to accompany the thrown exception
     */
    public $message;
    /**
     * Generatepeer exceptions that can be easily identified as coming from the ratingpeers system
     *
     * @param string $errorcode the error code to generatepeer
     */
    function __construct($errorcode) {
        $this->errorcode = $errorcode;
        $this->message = get_string($errorcode, 'error');
    }
}
