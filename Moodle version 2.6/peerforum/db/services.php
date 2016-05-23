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
 * PeerForum external functions and service definitions.
 *
 * @package    mod_peerforum
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_peerforum_get_peerforums_by_courses' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'get_peerforums_by_courses',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Returns a list of peerforum instances in a provided set of courses, if
            no courses are provided then all the peerforum instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/peerforum:viewdiscussion'
    ),

    'mod_peerforum_get_peerforum_discussions' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'get_peerforum_discussions',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'DEPRECATED (use mod_peerforum_get_peerforum_discussions_paginated instead):
                            Returns a list of peerforum discussions contained within a given set of peerforums.',
        'type' => 'read',
        'capabilities' => 'mod/peerforum:viewdiscussion, mod/peerforum:viewqandawithoutposting'
    ),

    'mod_peerforum_get_peerforum_discussion_posts' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'get_peerforum_discussion_posts',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Returns a list of peerforum posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/peerforum:viewdiscussion, mod/peerforum:viewqandawithoutposting'
    ),

    'mod_peerforum_get_peerforum_discussions_paginated' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'get_peerforum_discussions_paginated',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Returns a list of peerforum discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/peerforum:viewdiscussion, mod/peerforum:viewqandawithoutposting'
    ),

    'mod_peerforum_view_peerforum' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'view_peerforum',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/peerforum:viewdiscussion'
    ),

    'mod_peerforum_view_peerforum_discussion' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'view_peerforum_discussion',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Trigger the peerforum discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/peerforum:viewdiscussion'
    ),

    'mod_peerforum_add_discussion_post' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/peerforum:replypost'
    ),

    'mod_peerforum_add_discussion' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Add a new discussion into an existing peerforum.',
        'type' => 'write',
        'capabilities' => 'mod/peerforum:startdiscussion'
    ),

    'mod_peerforum_can_add_discussion' => array(
        'classname' => 'mod_peerforum_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/peerforum/externallib.php',
        'description' => 'Check if the current user can add discussions in the given peerforum (and optionally for the given group).',
        'type' => 'read'
    ),
);
