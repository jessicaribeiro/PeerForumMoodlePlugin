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
 * PeerForum post renderable.
 *
 * @package    mod_peerforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_peerforum\output\emaildigestbasic;

defined('MOODLE_INTERNAL') || die();

/**
 * PeerForum post renderable.
 *
 * @since      Moodle 3.0
 * @package    mod_peerforum
 * @copyright  2015 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \mod_peerforum\output\email\renderer {

    /**
     * The template name for this renderer.
     *
     * @return string
     */
    public function peerforum_post_template() {
        return 'peerforum_post_emaildigestbasic_htmlemail';
    }
}
