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
 * External peergrade functions unit tests
 *
 * @package    core_peergrade
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/peergrade/lib.php');

/**
 * External peergrade functions unit tests
 *
 * @package    core_peergrade
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_peergrade_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Test get_item_peergrades
     */
    public function test_get_item_peergrades() {

        global $DB, $USER;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $teacher3 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        unassign_capability('moodle/site:accessallgroups', $teacherrole->id);

        $this->getDataGenerator()->enrol_user($student->id,  $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher1->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher2->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher3->id, $course->id, $teacherrole->id);

        // Create the forum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course->id;
        // Set Aggregate type = Average of peergrades.
        $record->peergradeassessed = PEERGRADE_AGGREGATE_AVERAGE;
        $forum = self::getDataGenerator()->create_module('forum', $record);

        $contextid = context_module::instance($forum->cmid)->id;

        // Add discussion to the forums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $student->id;
        $record->forum = $forum->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);
        // Retrieve the first post.
        $post = $DB->get_record('forum_posts', array('discussion' => $discussion->id));

        // Rete the discussion as teacher1.
        $peergrade1 = new stdClass();
        $peergrade1->contextid = $contextid;
        $peergrade1->component = 'mod_forum';
        $peergrade1->peergradearea = 'post';
        $peergrade1->itemid = $post->id;
        $peergrade1->peergrade = 90;
        $peergrade1->scaleid = 100;
        $peergrade1->userid = $teacher1->id;
        $peergrade1->timecreated = time();
        $peergrade1->timemodified = time();
        $peergrade1->id = $DB->insert_record('peerforum_peergrade', $peergrade1);

        // Rete the discussion as teacher2.
        $peergrade2 = new stdClass();
        $peergrade2->contextid = $contextid;
        $peergrade2->component = 'mod_forum';
        $peergrade2->peergradearea = 'post';
        $peergrade2->itemid = $post->id;
        $peergrade2->peergrade = 95;
        $peergrade2->scaleid = 100;
        $peergrade2->userid = $teacher2->id;
        $peergrade2->timecreated = time() + 1;
        $peergrade2->timemodified = time() + 1;
        $peergrade2->id = $DB->insert_record('peerforum_peergrade', $peergrade2);

        // Delete teacher2, we must still receive the peergrades.
        delete_user($teacher2);

        // Teachers can see all the peergrades.
        $this->setUser($teacher1);

        $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'post', $post->id, 100, '');
        // We need to execute the return values cleaning process to simulate the web service server.
        $peergrades = external_api::clean_returnvalue(core_peergrade_external::get_item_peergrades_returns(), $peergrades);
        $this->assertCount(2, $peergrades['peergrades']);

        $indexedpeergrades = array();
        foreach ($peergrades['peergrades'] as $peergrade) {
            $indexedpeergrades[$peergrade['id']] = $peergrade;
        }
        $this->assertEquals($peergrade1->peergrade.' / '.$peergrade1->scaleid, $indexedpeergrades[$peergrade1->id]['peergrade']);
        $this->assertEquals($peergrade2->peergrade.' / '.$peergrade2->scaleid, $indexedpeergrades[$peergrade2->id]['peergrade']);

        $this->assertEquals($peergrade1->userid, $indexedpeergrades[$peergrade1->id]['userid']);
        $this->assertEquals($peergrade2->userid, $indexedpeergrades[$peergrade2->id]['userid']);

        // Student can see peergrades.
        $this->setUser($student);

        $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'post', $post->id, 100, '');
        // We need to execute the return values cleaning process to simulate the web service server.
        $peergrades = external_api::clean_returnvalue(core_peergrade_external::get_item_peergrades_returns(), $peergrades);
        $this->assertCount(2, $peergrades['peergrades']);

        // Invalid item.
        try {
            $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'post', 0, 100, '');
            $this->fail('Exception expected due invalid itemid.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Invalid area.
        try {
            $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'xyz', $post->id, 100, '');
            $this->fail('Exception expected due invalid peergrade area.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidpeergradearea', $e->errorcode);
        }

        // Invalid context. invalid_parameter_exception.
        try {
            $peergrades = core_peergrade_external::get_item_peergrades('module', 0, 'mod_forum', 'post', $post->id, 100, '');
            $this->fail('Exception expected due invalid context.');
        } catch (invalid_parameter_exception $e) {
            $this->assertEquals('invalidparameter', $e->errorcode);
        }

        // Test for groupmode.
        set_coursemodule_groupmode($forum->cmid, SEPARATEGROUPS);
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group, $teacher1);

        $discussion->groupid = $group->id;
        $DB->update_record('forum_discussions', $discussion);

        // Error for teacher3 and 2 peergrades for teacher1 should be returned.
        $this->setUser($teacher1);
        $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'post', $post->id, 100, '');
        // We need to execute the return values cleaning process to simulate the web service server.
        $peergrades = external_api::clean_returnvalue(core_peergrade_external::get_item_peergrades_returns(), $peergrades);
        $this->assertCount(2, $peergrades['peergrades']);

        $this->setUser($teacher3);
        try {
            $peergrades = core_peergrade_external::get_item_peergrades('module', $forum->cmid, 'mod_forum', 'post', $post->id, 100, '');
            $this->fail('Exception expected due invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noviewpeergrade', $e->errorcode);
        }

    }
}
