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

/**
 * Unit tests for hook_callbacks.
 *
 * @package     mod_kanban
 * @copyright   2026 ISB Bayern
 * @author      Thomas Schönlein
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanban\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private \stdClass $course;
    /** @var \stdClass The kanban instance used for testing */
    private \stdClass $kanban;
    /** @var array The users used for testing */
    private array $users;
    /** @var int The board id */
    private int $boardid;
    /** @var array The column ids */
    private array $columnids;
    /** @var boardmanager The board manager */
    private boardmanager $boardmanager;

    /**
     * Prepare testing environment.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course]);

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user(
                [
                    'email' => 'hooktestuser' . $i . '@example.com',
                    'username' => 'hooktestuser' . $i,
                ]
            );
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);

        $this->setUser($this->users[2]);
        $this->boardmanager = new boardmanager($this->kanban->cmid);
        $this->boardid = $this->boardmanager->create_board();
        $this->boardmanager->load_board($this->boardid);
        $this->columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $this->boardid]);
    }

    /**
     * Test that unenrolling a user with a single enrolment removes all kanban assignments.
     *
     * @covers \mod_kanban\hook_callbacks::handle_user_unenrolment
     */
    public function test_unenrolment_removes_assignments(): void {
        global $DB;

        $cardid = $this->boardmanager->add_card($this->columnids[0]);
        $this->boardmanager->assign_user($cardid, $this->users[0]->id);

        $this->assertTrue(
            $DB->record_exists('kanban_assignee', ['kanban_card' => $cardid, 'userid' => $this->users[0]->id])
        );

        $manplugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $manplugin->unenrol_user($instance, $this->users[0]->id);

        $this->assertFalse(
            $DB->record_exists('kanban_assignee', ['kanban_card' => $cardid, 'userid' => $this->users[0]->id])
        );
    }

    /**
     * Test that unenrolling a user with multiple enrolments keeps kanban assignments.
     *
     * @covers \mod_kanban\hook_callbacks::handle_user_unenrolment
     */
    public function test_unenrolment_with_multiple_enrolments_keeps_assignments(): void {
        global $DB;

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);

        $selfplugin = enrol_get_plugin('self');
        $selfplugin->add_instance($this->course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'roleid' => $studentrole->id,
        ]);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id, 'self');

        $cardid = $this->boardmanager->add_card($this->columnids[0]);
        $this->boardmanager->assign_user($cardid, $this->users[0]->id);

        $manplugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $manplugin->unenrol_user($instance, $this->users[0]->id);

        $this->assertTrue(
            $DB->record_exists('kanban_assignee', ['kanban_card' => $cardid, 'userid' => $this->users[0]->id])
        );
    }

    /**
     * Test that unenrolling a user without any kanban assignments does not cause errors.
     *
     * @covers \mod_kanban\hook_callbacks::handle_user_unenrolment
     */
    public function test_unenrolment_without_assignments_no_error(): void {
        global $DB;

        $manplugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $manplugin->unenrol_user($instance, $this->users[1]->id);

        $this->assertFalse(
            $DB->record_exists('user_enrolments', ['userid' => $this->users[1]->id, 'enrolid' => $instance->id])
        );
    }

    /**
     * Test that unenrolling removes assignments across multiple boards and cards.
     *
     * @covers \mod_kanban\hook_callbacks::handle_user_unenrolment
     */
    public function test_unenrolment_removes_assignments_across_multiple_cards(): void {
        global $DB;

        $card1 = $this->boardmanager->add_card($this->columnids[0]);
        $card2 = $this->boardmanager->add_card($this->columnids[1]);
        $this->boardmanager->assign_user($card1, $this->users[0]->id);
        $this->boardmanager->assign_user($card2, $this->users[0]->id);

        $this->boardmanager->assign_user($card1, $this->users[1]->id);

        $this->assertEquals(
            2,
            $DB->count_records('kanban_assignee', ['userid' => $this->users[0]->id])
        );

        $manplugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $manplugin->unenrol_user($instance, $this->users[0]->id);

        $this->assertEquals(
            0,
            $DB->count_records('kanban_assignee', ['userid' => $this->users[0]->id])
        );

        $this->assertTrue(
            $DB->record_exists('kanban_assignee', ['kanban_card' => $card1, 'userid' => $this->users[1]->id])
        );
    }

    /**
     * Test that unenrolling removes calendar events for assigned cards with due dates.
     *
     * @covers \mod_kanban\hook_callbacks::handle_user_unenrolment
     */
    public function test_unenrolment_removes_calendar_events(): void {
        global $DB;

        $cardid = $this->boardmanager->add_card($this->columnids[0]);
        $duedate = time() + DAYSECS;
        $this->boardmanager->update_card($cardid, ['duedate' => $duedate]);
        $this->boardmanager->assign_user($cardid, $this->users[0]->id);

        $this->assertTrue(
            $DB->record_exists('event', ['uuid' => $cardid, 'instance' => $this->kanban->id, 'userid' => $this->users[0]->id])
        );

        $manplugin = enrol_get_plugin('manual');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'manual']);
        $manplugin->unenrol_user($instance, $this->users[0]->id);

        $this->assertFalse(
            $DB->record_exists('event', ['uuid' => $cardid, 'instance' => $this->kanban->id, 'userid' => $this->users[0]->id])
        );
    }
}
