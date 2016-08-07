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
 * PeerForum subscription manager.
 *
 * @package    mod_peerforum
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerforum;

defined('MOODLE_INTERNAL') || die();

/**
 * PeerForum subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const PEERFORUM_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for peerforums.
     *
     * The first level key is the user ID
     * The second level is the peerforum ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $peerforumcache = array();

    /**
     * The list of peerforums which have been wholly retrieved for the peerforum subscription cache.
     *
     * This allows for prior caching of an entire peerforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedpeerforums = array();

    /**
     * The subscription cache for peerforum discussions.
     *
     * The first level key is the user ID
     * The second level is the peerforum ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $peerforumdiscussioncache = array();

    /**
     * The list of peerforums which have been wholly retrieved for the peerforum discussion subscription cache.
     *
     * This allows for prior caching of an entire peerforum to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedpeerforums = array();

    /**
     * Whether a user is subscribed to this peerforum, or a discussion within
     * the peerforum.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the peerforum preference.
     *
     * If it is not specified then only the peerforum preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $peerforum The record of the peerforum to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $peerforum, $discussionid = null, $cm = null) {
        // If peerforum is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($peerforum)) {
            if (!$cm) {
                $cm = get_fast_modinfo($peerforum->course)->instances['peerforum'][$peerforum->id];
            }
            if (has_capability('mod/peerforum:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_peerforum($userid, $peerforum);
        }

        $subscriptions = self::fetch_discussion_subscription($peerforum->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::PEERFORUM_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_peerforum($userid, $peerforum);
    }

    /**
     * Whether a user is subscribed to this peerforum.
     *
     * @param int $userid The user ID
     * @param \stdClass $peerforum The record of the peerforum to test
     * @return boolean
     */
    protected static function is_subscribed_to_peerforum($userid, $peerforum) {
        return self::fetch_subscription_cache($peerforum->id, $userid);
    }

    /**
     * Helper to determine whether a peerforum has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $peerforum The record of the peerforum to test
     * @return bool
     */
    public static function is_forcesubscribed($peerforum) {
        return ($peerforum->forcesubscribe == PEERFORUM_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a peerforum has it's subscription mode set to disabled.
     *
     * @param \stdClass $peerforum The record of the peerforum to test
     * @return bool
     */
    public static function subscription_disabled($peerforum) {
        return ($peerforum->forcesubscribe == PEERFORUM_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified peerforum can be subscribed to.
     *
     * @param \stdClass $peerforum The record of the peerforum to test
     * @return bool
     */
    public static function is_subscribable($peerforum) {
        return (!\mod_peerforum\subscriptions::is_forcesubscribed($peerforum) &&
                !\mod_peerforum\subscriptions::subscription_disabled($peerforum));
    }

    /**
     * Set the peerforum subscription mode.
     *
     * By default when called without options, this is set to PEERFORUM_FORCESUBSCRIBE.
     *
     * @param \stdClass $peerforum The record of the peerforum to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($peerforumid, $status = 1) {
        global $DB;
        return $DB->set_field("peerforum", "forcesubscribe", $status, array("id" => $peerforumid));
    }

    /**
     * Returns the current subscription mode for the peerforum.
     *
     * @param \stdClass $peerforum The record of the peerforum to set
     * @return int The peerforum subscription mode
     */
    public static function get_subscription_mode($peerforum) {
        return $peerforum->forcesubscribe;
    }

    /**
     * Returns an array of peerforums that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable peerforums
     */
    public static function get_unsubscribable_peerforums() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all peerforums from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a peerforum in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {peerforum} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {peerforum_subscriptions} fs ON (fs.peerforum = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'peerforum',
            'userid' => $USER->id,
            'forcesubscribe' => PEERFORUM_FORCESUBSCRIBE,
        ));
        $peerforums = $DB->get_recordset_sql($sql, $params);

        $unsubscribablepeerforums = array();
        foreach($peerforums as $peerforum) {
            if (empty($peerforum->visible)) {
                // The peerforum is hidden - check if the user can view the peerforum.
                $context = \context_module::instance($peerforum->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden peerforum to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribablepeerforums[] = $peerforum;
        }
        $peerforums->close();

        return $unsubscribablepeerforums;
    }

    /**
     * Get the list of potential subscribers to a peerforum.
     *
     * @param context_module $context the peerforum context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/peerforum:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the peerforum subscription data for the specified userid and peerforum.
     *
     * @param int $peerforumid The peerforum to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($peerforumid, $userid) {
        if (isset(self::$peerforumcache[$userid]) && isset(self::$peerforumcache[$userid][$peerforumid])) {
            return self::$peerforumcache[$userid][$peerforumid];
        }
        self::fill_subscription_cache($peerforumid, $userid);

        if (!isset(self::$peerforumcache[$userid]) || !isset(self::$peerforumcache[$userid][$peerforumid])) {
            return false;
        }

        return self::$peerforumcache[$userid][$peerforumid];
    }

    /**
     * Fill the peerforum subscription data for the specified userid and peerforum.
     *
     * If the userid is not specified, then all subscription data for that peerforum is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $peerforumid The peerforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($peerforumid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedpeerforums[$peerforumid])) {
            // This peerforum has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$peerforumcache[$userid])) {
                    self::$peerforumcache[$userid] = array();
                }

                if (!isset(self::$peerforumcache[$userid][$peerforumid])) {
                    if ($DB->record_exists('peerforum_subscriptions', array(
                        'userid' => $userid,
                        'peerforum' => $peerforumid,
                    ))) {
                        self::$peerforumcache[$userid][$peerforumid] = true;
                    } else {
                        self::$peerforumcache[$userid][$peerforumid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('peerforum_subscriptions', array(
                    'peerforum' => $peerforumid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$peerforumcache[$data->userid])) {
                        self::$peerforumcache[$data->userid] = array();
                    }
                    self::$peerforumcache[$data->userid][$peerforumid] = true;
                }
                self::$fetchedpeerforums[$peerforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the peerforum subscription data for all peerforums that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$peerforumcache[$userid])) {
            self::$peerforumcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS peerforumid,
                    s.id AS subscriptionid
                FROM {peerforum} f
                LEFT JOIN {peerforum_subscriptions} s ON (s.peerforum = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => PEERFORUM_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$peerforumcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this peerforum.
     *
     * @param stdClass $peerforum The peerforum record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the peerforum context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($peerforum, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforums,
                      u.mnethostid";
        }

        // Retrieve the peerforum context if it wasn't specified.
        $context = peerforum_get_context($peerforum->id, $context);

        if (self::is_forcesubscribed($peerforum)) {
            $results = \mod_peerforum\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['peerforumid'] = $peerforum->id;

            if ($includediscussionsubscriptions) {
                $params['speerforumid'] = $peerforum->id;
                $params['dspeerforumid'] = $peerforum->id;
                $params['unsubscribed'] = self::PEERFORUM_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {peerforum_subscriptions} s
                            WHERE
                                s.peerforum = :speerforumid
                                UNION
                            SELECT userid FROM {peerforum_discussion_subs} ds
                            WHERE
                                ds.peerforum = :dspeerforumid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {peerforum_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.peerforum = :peerforumid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a peerforum.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('peerforum', $peerforum->id, $peerforum->course);
        $modinfo = get_fast_modinfo($peerforum->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and peerforum.
     *
     * This is returned as an array of discussions for that peerforum which contain the preference in a stdClass.
     *
     * @param int $peerforumid The peerforum to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the peerforum.
     */
    public static function fetch_discussion_subscription($peerforumid, $userid = null) {
        self::fill_discussion_subscription_cache($peerforumid, $userid);

        if (!isset(self::$peerforumdiscussioncache[$userid]) || !isset(self::$peerforumdiscussioncache[$userid][$peerforumid])) {
            return array();
        }

        return self::$peerforumdiscussioncache[$userid][$peerforumid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and peerforum.
     *
     * If the userid is not specified, then all discussion subscription data for that peerforum is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $peerforumid The peerforum to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($peerforumid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedpeerforums[$peerforumid])) {
            // This peerforum hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$peerforumdiscussioncache[$userid])) {
                    self::$peerforumdiscussioncache[$userid] = array();
                }

                if (!isset(self::$peerforumdiscussioncache[$userid][$peerforumid])) {
                    $subscriptions = $DB->get_recordset('peerforum_discussion_subs', array(
                        'userid' => $userid,
                        'peerforum' => $peerforumid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($peerforumid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('peerforum_discussion_subs', array(
                    'peerforum' => $peerforumid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($peerforumid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedpeerforums[$peerforumid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $peerforumid The ID of the peerforum that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($peerforumid, $userid, $discussion, $preference) {
        if (!isset(self::$peerforumdiscussioncache[$userid])) {
            self::$peerforumdiscussioncache[$userid] = array();
        }

        if (!isset(self::$peerforumdiscussioncache[$userid][$peerforumid])) {
            self::$peerforumdiscussioncache[$userid][$peerforumid] = array();
        }

        self::$peerforumdiscussioncache[$userid][$peerforumid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking peerforum discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$peerforumdiscussioncache = array();
        self::$discussionfetchedpeerforums = array();
    }

    /**
     * Reset the peerforum cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking peerforum subscription states.
     */
    public static function reset_peerforum_cache() {
        self::$peerforumcache = array();
        self::$fetchedpeerforums = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $peerforum The peerforum record for this peerforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the peerforum_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $peerforum, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $peerforum)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->peerforum = $peerforum->id;

        $result = $DB->insert_record("peerforum_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('peerforum_discussion_subs', array('userid' => $userid, 'peerforum' => $peerforum->id));
            $DB->delete_records_select('peerforum_discussion_subs',
                    'userid = :userid AND peerforum = :peerforumid AND preference <> :preference', array(
                        'userid' => $userid,
                        'peerforumid' => $peerforum->id,
                        'preference' => self::PEERFORUM_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this peerforum.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$peerforumdiscussioncache[$userid]) && isset(self::$peerforumdiscussioncache[$userid][$peerforum->id])) {
                foreach (self::$peerforumdiscussioncache[$userid][$peerforum->id] as $discussionid => $preference) {
                    if ($preference != self::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$peerforumdiscussioncache[$userid][$peerforum->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this peerforum.
        self::$peerforumcache[$userid][$peerforum->id] = true;

        $context = peerforum_get_context($peerforum->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('peerforumid' => $peerforum->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('peerforum_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $peerforum The peerforum record for this peerforum.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $peerforum, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'peerforum' => $peerforum->id,
        );
        $DB->delete_records('peerforum_digests', $sqlparams);

        if ($peerforumsubscription = $DB->get_record('peerforum_subscriptions', $sqlparams)) {
            $DB->delete_records('peerforum_subscriptions', array('id' => $peerforumsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('peerforum_discussion_subs', $sqlparams);
                $DB->delete_records('peerforum_discussion_subs',
                        array('userid' => $userid, 'peerforum' => $peerforum->id, 'preference' => self::PEERFORUM_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$peerforumdiscussioncache[$userid]) && isset(self::$peerforumdiscussioncache[$userid][$peerforum->id])) {
                    self::$peerforumdiscussioncache[$userid][$peerforum->id] = array();
                }
            }

            // Reset the cache for this peerforum.
            self::$peerforumcache[$userid][$peerforum->id] = false;

            $context = peerforum_get_context($peerforum->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $peerforumsubscription->id,
                'relateduserid' => $userid,
                'other' => array('peerforumid' => $peerforum->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('peerforum_subscriptions', $peerforumsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('peerforum_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('peerforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a peerforum level subscription.
        if ($DB->record_exists('peerforum_subscriptions', array('userid' => $userid, 'peerforum' => $discussion->peerforum))) {
            if ($subscription && $subscription->preference == self::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the peerforum, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('peerforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$peerforumdiscussioncache[$userid][$discussion->peerforum][$discussion->id]);
            } else {
                // The user is already subscribed to the peerforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('peerforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->peerforum = $discussion->peerforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('peerforum_discussion_subs', $subscription);
                self::$peerforumdiscussioncache[$userid][$discussion->peerforum][$discussion->id] = $subscription->preference;
            }
        }

        $context = peerforum_get_context($discussion->peerforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'peerforumid' => $discussion->peerforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('peerforum_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a peerforum level subscription.
        if (!$DB->record_exists('peerforum_subscriptions', array('userid' => $userid, 'peerforum' => $discussion->peerforum))) {
            if ($subscription && $subscription->preference != self::PEERFORUM_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the peerforum, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('peerforum_discussion_subs', array('id' => $subscription->id));
                unset(self::$peerforumdiscussioncache[$userid][$discussion->peerforum][$discussion->id]);
            } else {
                // The user is not subscribed from the peerforum. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::PEERFORUM_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('peerforum_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->peerforum = $discussion->peerforum;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::PEERFORUM_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('peerforum_discussion_subs', $subscription);
            }
            self::$peerforumdiscussioncache[$userid][$discussion->peerforum][$discussion->id] = $subscription->preference;
        }

        $context = peerforum_get_context($discussion->peerforum, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'peerforumid' => $discussion->peerforum,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}
