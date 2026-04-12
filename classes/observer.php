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
 * Event observer.
 *
 * @package    mod_kanban
 * @copyright  2026 ISB Bayern
 * @author     Thomas Schönlein
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Event observer.
 * Removes all assignments for a specific user if a user is unenrolled completely from the course.
 *
 * @package    mod_kanban
 * @copyright  2026 ISB Bayern
 * @author     Thomas Schönlein
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kanban_observer {
    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return void
     */
    public static function remove_assignments(\core\event\user_enrolment_deleted $event): void {
        global $DB;

        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];

        // Check if the user is enrolled in more than one way (e.g. self, manual and/or group enrolment).
        if (!$cp->lastenrol) {
            return;
        }
        $userid = $event->objectid;
        $params = ['userid' => $userid, 'courseid' => $cp->courseid];

        // Delete all assignments for the user.
        $DB->delete_records_select(
            'kanban_assignee',
            "kanban_card IN (SELECT id FROM {kanban_card} c
            JOIN {kanban_board} b ON b.id = c.kanban_board
            JOIN {kanban} k ON k.id = b.kanban_instance
            WHERE k.course = :courseid)
            AND userid = :userid",
            $params
        );

        // Delete all calendar events for the user.
        $DB->delete_records_select(
            'event',
            "instance IN (SELECT id FROM {kanban} k
            WHERE k.course = :courseid)
            AND userid = :userid
            AND modulename = 'kanban'",
            $params
        );
    }
}
