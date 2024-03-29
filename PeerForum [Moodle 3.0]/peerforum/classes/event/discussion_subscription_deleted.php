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
 * The mod_peerforum discussion_subscription deleted event.
 *
 * @package    mod_peerforum
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerforum\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_peerforum discussion_subscription deleted event class.
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int peerforumid: The id of the peerforum which the discussion is in.
 *      - int discussion: The id of the discussion which has been unsubscribed from.
 * }
 *
 * @package    mod_peerforum
 * @since      Moodle 2.8
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_subscription_deleted extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'peerforum_discussion_subs';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' unsubscribed the user with id '$this->relateduserid' from the discussion " .
            " with id '{$this->other['discussion']}' in the peerforum with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdiscussionsubscriptiondeleted', 'mod_peerforum');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/peerforum/subscribe.php', array(
            'id' => $this->other['peerforumid'],
            'd' => $this->other['discussion'],
        ));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['peerforumid'])) {
            throw new \coding_exception('The \'peerforumid\' value must be set in other.');
        }

        if (!isset($this->other['discussion'])) {
            throw new \coding_exception('The \'discussion\' value must be set in other.');
        }

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'peerforum_discussion_subs', 'restore' => 'peerforum_discussion_sub');
    }

    public static function get_other_mapping() {
        $othermapped = array();
        $othermapped['peerforumid'] = array('db' => 'peerforum', 'restore' => 'peerforum');
        $othermapped['discussion'] = array('db' => 'peerforum_discussions', 'restore' => 'peerforum_discussion');

        return $othermapped;
    }
}
