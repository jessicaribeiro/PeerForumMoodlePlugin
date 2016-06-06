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
 * Unit tests for ratingpeer/lib.php
 *
 * @package    core_ratingpeers
 * @category   phpunit
 * @copyright  2011 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Include all the needed stuff
global $CFG;
require_once($CFG->dirroot . '/ratingpeer/lib.php');


/**
 * Unit test case for all the ratingpeer/lib.php requiring DB mockup & manipulation
 */
class core_ratingpeer_testcase extends advanced_testcase {

    protected $syscontext;
    protected $neededcaps = array('view', 'viewall', 'viewany', 'ratepeer');
    protected $originaldefaultfrontpageroleid;

    public function setUp() {
        global $CFG;
        parent::setUp();

        $this->resetAfterTest(true);

        $CFG->defaultfrontpageroleid = null;
    }

    /**
     * Test the current get_ratingpeers method main sql
     */
    function test_get_ratingpeers_sql() {
        global $DB;

        // We load 3 items. Each is ratedpeer twice. For simplicity itemid == user id of the item owner
        $ctxid = context_system::instance()->id;
        $ratingpeers = array(
            //user 1's items. Average == 2
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>1 ,'scaleid'=>10 ,'ratingpeer'=>1 ,'userid'=>2 ,'timecreated'=>1 ,'timemodified'=>1),
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>1 ,'scaleid'=>10 ,'ratingpeer'=>3 ,'userid'=>3 ,'timecreated'=>1 ,'timemodified'=>1),
            //user 2's items. Average == 3
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>2 ,'scaleid'=>10 ,'ratingpeer'=>1 ,'userid'=>1 ,'timecreated'=>1 ,'timemodified'=>1),
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>2 ,'scaleid'=>10 ,'ratingpeer'=>5 ,'userid'=>3 ,'timecreated'=>1 ,'timemodified'=>1),
            //user 3's items. Average == 4
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>3 ,'scaleid'=>10 ,'ratingpeer'=>3 ,'userid'=>1 ,'timecreated'=>1 ,'timemodified'=>1),
            array('contextid'=>$ctxid ,'component'=>'mod_forum','ratingpeerarea'=>'post','itemid'=>3 ,'scaleid'=>10 ,'ratingpeer'=>5 ,'userid'=>2 ,'timecreated'=>1 ,'timemodified'=>1)
        );
        foreach ($ratingpeers as $ratingpeer) {
            $DB->insert_record('peerforum_ratingpeer', $ratingpeer);
        }


        // a post (item) by user 1 (ratedpeer above by user 2 and 3 with average = 2)
        $user1posts = array(
            (object)array('id' => 1, 'userid' => 1, 'message' => 'hello'));
        // a post (item) by user 2 (ratedpeer above by user 1 and 3 with average = 3)
        $user2posts = array(
            (object)array('id' => 2, 'userid' => 2, 'message' => 'world'));
        // a post (item) by user 3 (ratedpeer above by user 1 and 2 with average = 4)
        $user3posts = array(
            (object)array('id' => 3, 'userid' => 3, 'message' => 'moodle'));

        // Prepare the default options
        $defaultoptions = array (
            'context'    => context_system::instance(),
            'component'  => 'mod_forum',
            'ratingpeerarea' => 'post',
            'scaleid'    => 10,
            'aggregate'  => RATINGPEER_AGGREGATE_AVERAGE);

        $rm = new mockup_ratingpeer_manager();

        // STEP 1: Retreive ratingpeers using the current user

        // Get results for user 1's item (expected average 1 + 3 / 2 = 2)
        $toptions = (object)array_merge($defaultoptions, array('items' => $user1posts));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($user1posts));
        $this->assertEquals($result[0]->id, $user1posts[0]->id);
        $this->assertEquals($result[0]->userid, $user1posts[0]->userid);
        $this->assertEquals($result[0]->message, $user1posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);
        // Note that $result[0]->ratingpeer->ratingpeer is somewhat random
        // We didn't supply a user ID so $USER was used which will vary depending on who runs the tests

        // Get results for items of user 2 (expected average 1 + 5 / 2 = 3)
        $toptions = (object)array_merge($defaultoptions, array('items' => $user2posts));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($user2posts));
        $this->assertEquals($result[0]->id, $user2posts[0]->id);
        $this->assertEquals($result[0]->userid, $user2posts[0]->userid);
        $this->assertEquals($result[0]->message, $user2posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 3);
        // Note that $result[0]->ratingpeer->ratingpeer is somewhat random
        // We didn't supply a user ID so $USER was used which will vary depending on who runs the tests

        // Get results for items of user 3 (expected average 3 + 5 / 2 = 4)
        $toptions = (object)array_merge($defaultoptions, array('items' => $user3posts));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($user3posts));
        $this->assertEquals($result[0]->id, $user3posts[0]->id);
        $this->assertEquals($result[0]->userid, $user3posts[0]->userid);
        $this->assertEquals($result[0]->message, $user3posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 4);
        // Note that $result[0]->ratingpeer->ratingpeer is somewhat random
        // We didn't supply a user ID so $USER was used which will vary depending on who runs the tests

        // Get results for items of user 1 & 2 together (expected averages are 2 and 3, as tested above)
        $posts = array_merge($user1posts, $user2posts);
        $toptions = (object)array_merge($defaultoptions, array('items' => $posts));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($posts));
        $this->assertEquals($result[0]->id, $posts[0]->id);
        $this->assertEquals($result[0]->userid, $posts[0]->userid);
        $this->assertEquals($result[0]->message, $posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);
        // Note that $result[0]->ratingpeer->ratingpeer is somewhat random
        // We didn't supply a user ID so $USER was used which will vary depending on who runs the tests

        $this->assertEquals($result[1]->id, $posts[1]->id);
        $this->assertEquals($result[1]->userid, $posts[1]->userid);
        $this->assertEquals($result[1]->message, $posts[1]->message);
        $this->assertEquals($result[1]->ratingpeer->count, 2);
        $this->assertEquals($result[1]->ratingpeer->aggregate, 3);
        // Note that $result[0]->ratingpeer->ratingpeer is somewhat random
        // We didn't supply a user ID so $USER was used which will vary depending on who runs the tests

        // STEP 2: Retrieve ratingpeers by a specified user
        //         We still expect complete aggregations and counts

        // Get results for items of user 1 ratedpeer by user 2 (avg 2, ratingpeer 1)
        $toptions = (object)array_merge($defaultoptions, array('items' => $user1posts, 'userid' => 2));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($user1posts));
        $this->assertEquals($result[0]->id, $user1posts[0]->id);
        $this->assertEquals($result[0]->userid, $user1posts[0]->userid);
        $this->assertEquals($result[0]->message, $user1posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);
        $this->assertEquals($result[0]->ratingpeer->ratingpeer, 1); //user 2 ratedpeer user 1 "1"
        $this->assertEquals($result[0]->ratingpeer->userid, $toptions->userid); // Must be the passed userid

        // Get results for items of user 1 ratedpeer by user 3
        $toptions = (object)array_merge($defaultoptions, array('items' => $user1posts, 'userid' => 3));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($user1posts));
        $this->assertEquals($result[0]->id, $user1posts[0]->id);
        $this->assertEquals($result[0]->userid, $user1posts[0]->userid);
        $this->assertEquals($result[0]->message, $user1posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);
        $this->assertEquals($result[0]->ratingpeer->ratingpeer, 3); //user 3 ratedpeer user 1 "3"
        $this->assertEquals($result[0]->ratingpeer->userid, $toptions->userid); // Must be the passed userid

        // Get results for items of user 1 & 2 together ratedpeer by user 3
        $posts = array_merge($user1posts, $user2posts);
        $toptions = (object)array_merge($defaultoptions, array('items' => $posts, 'userid' => 3));
        $result = $rm->get_ratingpeers($toptions);
        $this->assertEquals(count($result), count($posts));
        $this->assertEquals($result[0]->id, $posts[0]->id);
        $this->assertEquals($result[0]->userid, $posts[0]->userid);
        $this->assertEquals($result[0]->message, $posts[0]->message);
        $this->assertEquals($result[0]->ratingpeer->count, 2);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);
        $this->assertEquals($result[0]->ratingpeer->ratingpeer, 3); //user 3 ratedpeer user 1 "3"
        $this->assertEquals($result[0]->ratingpeer->userid, $toptions->userid); // Must be the passed userid

        $this->assertEquals($result[1]->id, $posts[1]->id);
        $this->assertEquals($result[1]->userid, $posts[1]->userid);
        $this->assertEquals($result[1]->message, $posts[1]->message);
        $this->assertEquals($result[1]->ratingpeer->count, 2);
        $this->assertEquals($result[1]->ratingpeer->aggregate, 3);
        $this->assertEquals($result[0]->ratingpeer->ratingpeer, 3); //user 3 ratedpeer user 2 "5"
        $this->assertEquals($result[1]->ratingpeer->userid, $toptions->userid); // Must be the passed userid

        // STEP 3: Some special cases

        // Get results for user 1's items (expected average 1 + 3 / 2 = 2)
        // supplying a non-existent user id so no ratingpeer from that user should be found
        $toptions = (object)array_merge($defaultoptions, array('items' => $user1posts));
        $toptions->userid = 123456; //non-existent user
        $result = $rm->get_ratingpeers($toptions);
        $this->assertNull($result[0]->ratingpeer->userid);
        $this->assertNull($result[0]->ratingpeer->ratingpeer);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 2);//should still get the aggregate

        // Get results for items of user 2 (expected average 1 + 5 / 2 = 3)
        // Supplying the user id of the user who owns the items so no ratingpeer should be found
        $toptions = (object)array_merge($defaultoptions, array('items' => $user2posts));
        $toptions->userid = 2; //user 2 viewing the ratingpeers of their own item
        $result = $rm->get_ratingpeers($toptions);
        //these should be null as the user is viewing their own item and thus cannot ratepeer
        $this->assertNull($result[0]->ratingpeer->userid);
        $this->assertNull($result[0]->ratingpeer->ratingpeer);
        $this->assertEquals($result[0]->ratingpeer->aggregate, 3);//should still get the aggregate
    }
}

/**
 * ratingpeer_manager subclass for unit testing without requiring capabilities to be loaded
 */
class mockup_ratingpeer_manager extends ratingpeer_manager {

    /**
     * Overwrite get_plugin_permissions_array() so it always return granted perms for unit testing
     */
    public function get_plugin_permissions_array($contextid, $component, $ratingpeerarea) {
        return array(
            'ratepeer' => true,
            'view' => true,
            'viewany' => true,
            'viewall' => true);
    }

}
