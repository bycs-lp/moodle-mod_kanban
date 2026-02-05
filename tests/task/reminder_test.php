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

namespace mod_kanban\task;

use mod_kanban\boardmanager;

/**
 * Unit test for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanban\task\reminder
 */
final class reminder_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private $course;
    /** @var \stdClass The kanban used for testing */
    private $kanban;
    /** @var array The users used for testing */
    private $users;

    /**
     * Prepare testing environment
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->course = $this->getDataGenerator()->create_course();
        $this->kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course]);

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user(
                [
                    'email' => $i . 'user@example.com',
                    'username' => 'userid' . $i,
                ]
            );
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);
    }

    /**
     * Test for sending the reminder after duedate.
     *
     * @return void
     */
    public function test_reminder_duedate(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = explode(',', $boardmanager->get_board()->sequence);
        $boardmanager->add_card($columnids[0], 0, ['title' => 'Testcard', 'duedate' => time() - 10000]);

        $reminder = new \mod_kanban\task\reminder();
        $reminder->execute();

        $noreply = \core_user::get_noreply_user();

        // No one is assigned to the card, so there should be no notifications.

        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals(
                0,
                $DB->count_records('notifications', ['useridfrom' => $noreply->id, 'useridto' => $this->users[$i]->id])
            );
        }

        $cardid = $boardmanager->add_card($columnids[0], 0, ['title' => 'Testcard 2', 'duedate' => time() - 10000]);
        $boardmanager->assign_user($cardid, $this->users[0]->id);

        $notificationcount = [];

        for ($i = 0; $i < 3; $i++) {
            $notificationcount[$i] = $DB->count_records(
                'notifications',
                ['useridfrom' => $noreply->id, 'useridto' => $this->users[$i]->id]
            );
        }

        $reminder->execute();

        // User 0 should have one additional notification.

        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals(
                $notificationcount[$i] + (int)($i == 0),
                $DB->count_records('notifications', ['useridfrom' => $noreply->id, 'useridto' => $this->users[$i]->id])
            );
        }

        $cardid = $boardmanager->add_card(
            $columnids[0],
            0,
            ['title' => 'Testcard 3', 'duedate' => time() + 100000000, 'reminderdate' => time() - 1000]
        );
        $boardmanager->assign_user($cardid, $this->users[0]->id);
        $boardmanager->assign_user($cardid, $this->users[2]->id);

        for ($i = 0; $i < 3; $i++) {
            $notificationcount[$i] = $DB->count_records(
                'notifications',
                ['useridfrom' => $noreply->id, 'useridto' => $this->users[$i]->id]
            );
        }

        $reminder->execute();

        // Everybody except user 1 should have one additional notification.

        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals(
                $notificationcount[$i] + (int)($i != 1),
                $DB->count_records('notifications', ['useridfrom' => $noreply->id, 'useridto' => $this->users[$i]->id])
            );
        }
    }
}
