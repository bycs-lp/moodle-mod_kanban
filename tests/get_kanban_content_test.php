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

use mod_kanban\external\get_kanban_content;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the user list in the payload of get_kanban_content
 *
 * @package     mod_kanban
 * @copyright   2026 ISB Bayern
 * @author      Thomas Schönlein
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 * @covers      \mod_kanban\external\get_kanban_content
 */
final class get_kanban_content_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private \stdClass $course;
    /** @var \stdClass The kanban used for testing */
    private \stdClass $kanban;
    /** @var \stdClass The student who loads the board */
    private \stdClass $student;
    /** @var \stdClass A second student who is assigned to a card */
    private \stdClass $otherstudent;
    /** @var \stdClass A third student who is never assigned to a card */
    private \stdClass $unassignedstudent;
    /** @var \stdClass The teacher */
    private \stdClass $teacher;

    /**
     * Prepare testing environment
     *
     * @return void
     */
    public function setUp(): void {
        global $DB, $SCRIPT;

        parent::setUp();

        $this->course = $this->getDataGenerator()->create_course();
        $this->kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course]);

        $this->student = $this->getDataGenerator()->create_user();
        $this->otherstudent = $this->getDataGenerator()->create_user();
        $this->unassignedstudent = $this->getDataGenerator()->create_user();
        $this->teacher = $this->getDataGenerator()->create_user();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->otherstudent->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->unassignedstudent->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole->id);
        // This is just for the tests of auth_saml2 not to fail.
        $SCRIPT = '/mod/kanban/view.php';
    }

    /**
     * Creates a board with one card and optionally assigns a user to it.
     * The teacher is the current user afterwards, tests have to set the user they need.
     *
     * @param int $assigneeid Id of the user who is assigned to the card, 0 for no assignment
     * @return int The id of the board
     */
    private function create_board_with_card(int $assigneeid): int {
        global $DB;

        $this->setUser($this->teacher);
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cardid = $boardmanager->add_card($columnids[0], 0, ['title' => 'Testcard']);
        if ($assigneeid > 0) {
            $boardmanager->assign_user($cardid, $assigneeid);
        }
        return $boardid;
    }

    /**
     * Loads the board as the current user and returns the ids of the users in the payload.
     *
     * @param int $boardid The id of the board
     * @return array Ids of the users in the payload
     */
    private function get_userids_of_payload(int $boardid): array {
        global $CFG;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $result = get_kanban_content::get_kanban_content_init($this->kanban->cmid, $boardid, 0);
        $result = \external_api::clean_returnvalue(get_kanban_content::get_kanban_content_init_returns(), $result);
        $userids = [];
        foreach ($result['users'] as $user) {
            $userids[] = (int) $user['id'];
        }
        sort($userids);
        return $userids;
    }

    /**
     * A student gets no users if no card of the board is assigned to anybody.
     *
     * @covers \mod_kanban\external\get_kanban_content::execute
     * @return void
     */
    #[Group('baseline')]
    public function test_student_gets_no_users_without_assignments(): void {
        $this->resetAfterTest();
        $boardid = $this->create_board_with_card(0);

        $this->setUser($this->student);
        $userids = $this->get_userids_of_payload($boardid);

        $this->assertSame([], $userids);
    }

    /**
     * A student only gets the users who are assigned to a card, not all enrolled users.
     *
     * @covers \mod_kanban\external\get_kanban_content::execute
     * @return void
     */
    #[Group('baseline')]
    public function test_student_gets_only_assigned_users(): void {
        $this->resetAfterTest();
        $boardid = $this->create_board_with_card((int) $this->otherstudent->id);

        $this->setUser($this->student);
        $userids = $this->get_userids_of_payload($boardid);

        $this->assertSame([(int) $this->otherstudent->id], $userids);
    }

    /**
     * A teacher with the capability to view all boards still gets all enrolled users.
     *
     * @covers \mod_kanban\external\get_kanban_content::execute
     * @return void
     */
    #[Group('baseline')]
    public function test_teacher_gets_all_enrolled_users(): void {
        $this->resetAfterTest();
        $boardid = $this->create_board_with_card((int) $this->otherstudent->id);

        $this->setUser($this->teacher);
        $userids = $this->get_userids_of_payload($boardid);

        $expected = [
            (int) $this->student->id,
            (int) $this->otherstudent->id,
            (int) $this->unassignedstudent->id,
            (int) $this->teacher->id,
        ];
        sort($expected);
        $this->assertSame($expected, $userids);
    }
}
