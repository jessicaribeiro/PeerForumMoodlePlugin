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
 * The module peerforums external functions unit tests
 *
 * @package    mod_peerforum
 * @category   external
 * @copyright  2013 Andrew Nicols
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

class mod_peerforum_maildigest_testcase extends advanced_testcase {

    /**
     * Keep track of the message and mail sinks that we set up for each
     * test.
     *
     * @var stdClass $helper
     */
    protected $helper;

    /**
     * Set up message and mail sinks, and set up other requirements for the
     * cron to be tested here.
     */
    public function setUp() {
        global $CFG;

        $this->helper = new stdClass();

        // Messaging is not compatible with transactions...
        $this->preventResetByRollback();

        // Catch all messages
        $this->helper->messagesink = $this->redirectMessages();
        $this->helper->mailsink = $this->redirectEmails();

        // Confirm that we have an empty message sink so far.
        $messages = $this->helper->messagesink->get_messages();
        $this->assertEquals(0, count($messages));

        $messages = $this->helper->mailsink->get_messages();
        $this->assertEquals(0, count($messages));

        // Tell Moodle that we've not sent any digest messages out recently.
        $CFG->digestmailtimelast = 0;

        // And set the digest sending time to a negative number - this has
        // the effect of making it 11pm the previous day.
        $CFG->digestmailtime = -1;

        // Forcibly reduce the maxeditingtime to a one second to ensure that
        // messages are sent out.
        $CFG->maxeditingtime = 1;

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_peerforum\subscriptions::reset_peerforum_cache();
        \mod_peerforum\subscriptions::reset_discussion_cache();
    }

    /**
     * Clear the message sinks set up in this test.
     */
    public function tearDown() {
        $this->helper->messagesink->clear();
        $this->helper->messagesink->close();

        $this->helper->mailsink->clear();
        $this->helper->mailsink->close();
    }

    /**
     * Setup a user, course, and peerforums.
     *
     * @return stdClass containing the list of peerforums, courses, peerforumids,
     * and the user enrolled in them.
     */
    protected function helper_setup_user_in_course() {
        global $DB;

        $return = new stdClass();
        $return->courses = new stdClass();
        $return->peerforums = new stdClass();
        $return->peerforumids = array();

        // Create a user.
        $user = $this->getDataGenerator()->create_user();
        $return->user = $user;

        // Create courses to add the modules.
        $return->courses->course1 = $this->getDataGenerator()->create_course();

        // Create peerforums.
        $record = new stdClass();
        $record->course = $return->courses->course1->id;
        $record->forcesubscribe = 1;

        $return->peerforums->peerforum1 = $this->getDataGenerator()->create_module('peerforum', $record);
        $return->peerforumsids[] = $return->peerforums->peerforum1->id;

        $return->peerforums->peerforum2 = $this->getDataGenerator()->create_module('peerforum', $record);
        $return->peerforumsids[] = $return->peerforums->peerforum2->id;

        // Check the peerforum was correctly created.
        list ($test, $params) = $DB->get_in_or_equal($return->peerforumsids);

        // Enrol the user in the courses.
        // DataGenerator->enrol_user automatically sets a role for the user
        $this->getDataGenerator()->enrol_user($return->user->id, $return->courses->course1->id);

        return $return;
    }

    /**
     * Helper to falsify all peerforum post records for a digest run.
     */
    protected function helper_force_digest_mail_times() {
        global $CFG, $DB;
        // Fake all of the post editing times because digests aren't sent until
        // the start of an hour where the modification time on the message is before
        // the start of that hour
        $sitetimezone = core_date::get_server_timezone();
        $digesttime = usergetmidnight(time(), $sitetimezone) + ($CFG->digestmailtime * 3600) - (60 * 60);
        $DB->set_field('peerforum_posts', 'modified', $digesttime, array('mailed' => 0));
        $DB->set_field('peerforum_posts', 'created', $digesttime, array('mailed' => 0));
    }

    /**
     * Run the peerforum cron, and check that the specified post was sent the
     * specified number of times.
     *
     * @param integer $expected The number of times that the post should have been sent
     * @return array An array of the messages caught by the message sink
     */
    protected function helper_run_cron_check_count($expected, $messagecount, $mailcount) {
        if ($expected === 0) {
            $this->expectOutputRegex('/(Email digests successfully sent to .* users.){0}/');
        } else {
            $this->expectOutputRegex("/Email digests successfully sent to {$expected} users/");
        }
        peerforum_cron();

        // Now check the results in the message sink.
        $messages = $this->helper->messagesink->get_messages();
        // There should be the expected number of messages.
        $this->assertEquals($messagecount, count($messages));

        // Now check the results in the mail sink.
        $messages = $this->helper->mailsink->get_messages();
        // There should be the expected number of messages.
        $this->assertEquals($mailcount, count($messages));

        return $messages;
    }

    public function test_set_maildigest() {
        global $DB;

        $this->resetAfterTest(true);

        $helper = $this->helper_setup_user_in_course();
        $user = $helper->user;
        $course1 = $helper->courses->course1;
        $peerforum1 = $helper->peerforums->peerforum1;

        // Set to the user.
        self::setUser($helper->user);

        // Confirm that there is no current value.
        $currentsetting = $DB->get_record('peerforum_digests', array(
            'peerforum' => $peerforum1->id,
            'userid' => $user->id,
        ));
        $this->assertFalse($currentsetting);

        // Test with each of the valid values:
        // 0, 1, and 2 are valid values.
        peerforum_set_user_maildigest($peerforum1, 0, $user);
        $currentsetting = $DB->get_record('peerforum_digests', array(
            'peerforum' => $peerforum1->id,
            'userid' => $user->id,
        ));
        $this->assertEquals($currentsetting->maildigest, 0);

        peerforum_set_user_maildigest($peerforum1, 1, $user);
        $currentsetting = $DB->get_record('peerforum_digests', array(
            'peerforum' => $peerforum1->id,
            'userid' => $user->id,
        ));
        $this->assertEquals($currentsetting->maildigest, 1);

        peerforum_set_user_maildigest($peerforum1, 2, $user);
        $currentsetting = $DB->get_record('peerforum_digests', array(
            'peerforum' => $peerforum1->id,
            'userid' => $user->id,
        ));
        $this->assertEquals($currentsetting->maildigest, 2);

        // And the default value - this should delete the record again
        peerforum_set_user_maildigest($peerforum1, -1, $user);
        $currentsetting = $DB->get_record('peerforum_digests', array(
            'peerforum' => $peerforum1->id,
            'userid' => $user->id,
        ));
        $this->assertFalse($currentsetting);

        // Try with an invalid value.
        $this->setExpectedException('moodle_exception');
        peerforum_set_user_maildigest($peerforum1, 42, $user);
    }

    public function test_get_user_digest_options_default() {
        global $USER, $DB;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $helper = $this->helper_setup_user_in_course();
        $user = $helper->user;
        $course1 = $helper->courses->course1;
        $peerforum1 = $helper->peerforums->peerforum1;

        // Set to the user.
        self::setUser($helper->user);

        // We test against these options.
        $digestoptions = array(
            '0' => get_string('emaildigestoffshort', 'mod_peerforum'),
            '1' => get_string('emaildigestcompleteshort', 'mod_peerforum'),
            '2' => get_string('emaildigestsubjectsshort', 'mod_peerforum'),
        );

        // The default settings is 0.
        $this->assertEquals(0, $user->maildigest);
        $options = peerforum_get_user_digest_options();
        $this->assertEquals($options[-1], get_string('emaildigestdefault', 'mod_peerforum', $digestoptions[0]));

        // Update the setting to 1.
        $USER->maildigest = 1;
        $this->assertEquals(1, $USER->maildigest);
        $options = peerforum_get_user_digest_options();
        $this->assertEquals($options[-1], get_string('emaildigestdefault', 'mod_peerforum', $digestoptions[1]));

        // Update the setting to 2.
        $USER->maildigest = 2;
        $this->assertEquals(2, $USER->maildigest);
        $options = peerforum_get_user_digest_options();
        $this->assertEquals($options[-1], get_string('emaildigestdefault', 'mod_peerforum', $digestoptions[2]));
    }

    public function test_get_user_digest_options_sorting() {
        global $USER, $DB;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $helper = $this->helper_setup_user_in_course();
        $user = $helper->user;
        $course1 = $helper->courses->course1;
        $peerforum1 = $helper->peerforums->peerforum1;

        // Set to the user.
        self::setUser($helper->user);

        // Retrieve the list of applicable options.
        $options = peerforum_get_user_digest_options();

        // The default option must always be at the top of the list.
        $lastoption = -2;
        foreach ($options as $value => $description) {
            $this->assertGreaterThan($lastoption, $value);
            $lastoption = $value;
        }
    }

    public function test_cron_no_posts() {
        global $DB;

        $this->resetAfterTest(true);

        $this->helper_force_digest_mail_times();

        // Initially the peerforum cron should generate no messages as we've made no posts.
        $this->helper_run_cron_check_count(0, 0, 0);
    }

    /**
     * Sends several notifications to one user as:
     * * single messages based on a user profile setting.
     */
    public function test_cron_profile_single_mails() {
        global $DB;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $userhelper = $this->helper_setup_user_in_course();
        $user = $userhelper->user;
        $course1 = $userhelper->courses->course1;
        $peerforum1 = $userhelper->peerforums->peerforum1;
        $peerforum2 = $userhelper->peerforums->peerforum2;

        // Add some discussions to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->mailnow = 1;

        // Add 5 discussions to peerforum 1.
        $record->peerforum = $peerforum1->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Add 5 discussions to peerforum 2.
        $record->peerforum = $peerforum2->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Ensure that the creation times mean that the messages will be sent.
        $this->helper_force_digest_mail_times();

        // Set the tested user's default maildigest setting.
        $DB->set_field('user', 'maildigest', 0, array('id' => $user->id));

        // Set the maildigest preference for peerforum1 to default.
        peerforum_set_user_maildigest($peerforum1, -1, $user);

        // Set the maildigest preference for peerforum2 to default.
        peerforum_set_user_maildigest($peerforum2, -1, $user);

        // No digests mails should be sent, but 10 peerforum mails will be sent.
        $this->helper_run_cron_check_count(0, 10, 0);
    }

    /**
     * Sends several notifications to one user as:
     * * daily digests coming from the user profile setting.
     */
    public function test_cron_profile_digest_email() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $userhelper = $this->helper_setup_user_in_course();
        $user = $userhelper->user;
        $course1 = $userhelper->courses->course1;
        $peerforum1 = $userhelper->peerforums->peerforum1;
        $peerforum2 = $userhelper->peerforums->peerforum2;

        // Add a discussion to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->mailnow = 1;

        // Add 5 discussions to peerforum 1.
        $record->peerforum = $peerforum1->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Add 5 discussions to peerforum 2.
        $record->peerforum = $peerforum2->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Ensure that the creation times mean that the messages will be sent.
        $this->helper_force_digest_mail_times();

        // Set the tested user's default maildigest setting.
        $DB->set_field('user', 'maildigest', 1, array('id' => $user->id));

        // Set the maildigest preference for peerforum1 to default.
        peerforum_set_user_maildigest($peerforum1, -1, $user);

        // Set the maildigest preference for peerforum2 to default.
        peerforum_set_user_maildigest($peerforum2, -1, $user);

        // One digest mail should be sent, with no notifications, and one e-mail.
        $this->helper_run_cron_check_count(1, 0, 1);
    }

    /**
     * Sends several notifications to one user as:
     * * daily digests coming from the per-peerforum setting; and
     * * single e-mails from the profile setting.
     */
    public function test_cron_mixed_email_1() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $userhelper = $this->helper_setup_user_in_course();
        $user = $userhelper->user;
        $course1 = $userhelper->courses->course1;
        $peerforum1 = $userhelper->peerforums->peerforum1;
        $peerforum2 = $userhelper->peerforums->peerforum2;

        // Add a discussion to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->mailnow = 1;

        // Add 5 discussions to peerforum 1.
        $record->peerforum = $peerforum1->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Add 5 discussions to peerforum 2.
        $record->peerforum = $peerforum2->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Ensure that the creation times mean that the messages will be sent.
        $this->helper_force_digest_mail_times();

        // Set the tested user's default maildigest setting.
        $DB->set_field('user', 'maildigest', 0, array('id' => $user->id));

        // Set the maildigest preference for peerforum1 to digest.
        peerforum_set_user_maildigest($peerforum1, 1, $user);

        // Set the maildigest preference for peerforum2 to default (single).
        peerforum_set_user_maildigest($peerforum2, -1, $user);

        // One digest e-mail should be sent, and five individual notifications.
        $this->helper_run_cron_check_count(1, 5, 1);
    }

    /**
     * Sends several notifications to one user as:
     * * single e-mails from the per-peerforum setting; and
     * * daily digests coming from the per-user setting.
     */
    public function test_cron_mixed_email_2() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $userhelper = $this->helper_setup_user_in_course();
        $user = $userhelper->user;
        $course1 = $userhelper->courses->course1;
        $peerforum1 = $userhelper->peerforums->peerforum1;
        $peerforum2 = $userhelper->peerforums->peerforum2;

        // Add a discussion to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->mailnow = 1;

        // Add 5 discussions to peerforum 1.
        $record->peerforum = $peerforum1->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Add 5 discussions to peerforum 2.
        $record->peerforum = $peerforum2->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Ensure that the creation times mean that the messages will be sent.
        $this->helper_force_digest_mail_times();

        // Set the tested user's default maildigest setting.
        $DB->set_field('user', 'maildigest', 1, array('id' => $user->id));

        // Set the maildigest preference for peerforum1 to digest.
        peerforum_set_user_maildigest($peerforum1, -1, $user);

        // Set the maildigest preference for peerforum2 to single.
        peerforum_set_user_maildigest($peerforum2, 0, $user);

        // One digest e-mail should be sent, and five individual notifications.
        $this->helper_run_cron_check_count(1, 5, 1);
    }

    /**
     * Sends several notifications to one user as:
     * * daily digests coming from the per-peerforum setting.
     */
    public function test_cron_peerforum_digest_email() {
        global $DB, $CFG;

        $this->resetAfterTest(true);

        // Set up a basic user enrolled in a course.
        $userhelper = $this->helper_setup_user_in_course();
        $user = $userhelper->user;
        $course1 = $userhelper->courses->course1;
        $peerforum1 = $userhelper->peerforums->peerforum1;
        $peerforum2 = $userhelper->peerforums->peerforum2;

        // Add a discussion to the peerforums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->mailnow = 1;

        // Add 5 discussions to peerforum 1.
        $record->peerforum = $peerforum1->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Add 5 discussions to peerforum 2.
        $record->peerforum = $peerforum2->id;
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->get_plugin_generator('mod_peerforum')->create_discussion($record);
        }

        // Ensure that the creation times mean that the messages will be sent.
        $this->helper_force_digest_mail_times();

        // Set the tested user's default maildigest setting.
        $DB->set_field('user', 'maildigest', 0, array('id' => $user->id));

        // Set the maildigest preference for peerforum1 to digest (complete).
        peerforum_set_user_maildigest($peerforum1, 1, $user);

        // Set the maildigest preference for peerforum2 to digest (short).
        peerforum_set_user_maildigest($peerforum2, 2, $user);

        // One digest e-mail should be sent, and no individual notifications.
        $this->helper_run_cron_check_count(1, 0, 1);
    }

}
