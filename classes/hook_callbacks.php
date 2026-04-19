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
 * Class hook_callbacks.
 *
 * @package   mod_kanban
 * @copyright 2026 ISB Bayern
 * @author    Thomas Schönlein
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Removes all assignments for a specific user if a user is unenrolled completely from the course.
     *
     * @param \core_enrol\hook\before_user_enrolment_removed $hook the user_unenrolment hook object
     */
    public static function handle_user_unenrolment(\core_enrol\hook\before_user_enrolment_removed $hook) {
        global $DB;

        $userid = $hook->get_userid();
        $courseid = $hook->enrolinstance->courseid;
        $ueid = $hook->userenrolmentinstance->id;

        $params = ['userid' => $userid, 'courseid' => $courseid, 'ueid' => $ueid];
        $sql = "SELECT COUNT(ue.id)
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE e.courseid = :courseid
                    AND ue.userid = :userid
                    AND ue.id <> :ueid";

        $lastenrol = $DB->count_records_sql($sql, $params);

        if ($lastenrol > 0) {
            return;
        }

        $cardparams = ['userid' => $userid, 'courseid' => $courseid];
        $sql = "SELECT c.id AS cardid, b.id AS boardid
                FROM {kanban_assignee} a
                JOIN {kanban_card} c ON c.id = a.kanban_card
                JOIN {kanban_board} b ON b.id = c.kanban_board
                JOIN {kanban} k ON k.id = b.kanban_instance
                WHERE k.course = :courseid
                AND a.userid = :userid";
        $assignments = $DB->get_records_sql($sql, $cardparams);

        if (empty($assignments)) {
            return;
        }

        $boardcards = [];
        foreach ($assignments as $assignment) {
            $boardcards[$assignment->boardid][] = $assignment->cardid;
        }

        foreach ($boardcards as $board => $cardids) {
            $bm = new boardmanager(0, $board);
            foreach ($cardids as $cardid) {
                $bm->unassign_user($cardid, $userid);
            }
        }
    }
}
