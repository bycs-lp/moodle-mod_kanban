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

namespace mod_kanban;

use context_module;
use mod_kanban\external\get_kanban_content;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Unit test for mod_kanban get content webservice.
 *
 * @package     mod_kanban
 * @copyright   2026 Jochen Hanisch
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(get_kanban_content::class)]
final class get_kanban_content_test extends \advanced_testcase {
    /**
     * Test that assigned-card management is exposed independently.
     */
    public function test_manageassignedcards_capability_is_reported_independently(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $course]);

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        $context = context_module::instance($kanban->cmid);
        assign_capability('mod/kanban:view', CAP_ALLOW, $studentrole->id, $context);
        assign_capability('mod/kanban:manageassignedcards', CAP_ALLOW, $studentrole->id, $context);
        assign_capability('mod/kanban:manageallcards', CAP_PREVENT, $studentrole->id, $context);
        accesslib_clear_all_caches_for_unit_testing();

        $boardmanager = new boardmanager($kanban->cmid);
        $boardid = $boardmanager->create_board();

        $this->setUser($user);
        $returnvalue = get_kanban_content::get_kanban_content_init($kanban->cmid, $boardid);
        $returnvalue = \external_api::clean_returnvalue(
            get_kanban_content::get_kanban_content_init_returns(),
            $returnvalue
        );

        $capabilities = array_column($returnvalue['capabilities'], 'value', 'id');

        $this->assertFalse($capabilities['manageallcards']);
        $this->assertTrue($capabilities['manageassignedcards']);
    }
}
