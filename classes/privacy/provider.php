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
 * Privacy provider for mod_kanban.
 *
 * @package    mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kanban\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\local\metadata\collection;
use stdClass;

/**
 * Privacy provider for mod_kanban.
 *
 * @package    mod_kanban
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('kanban', $context->instanceid)) {
            return;
        }

        $userids = $userlist->get_userids();

        foreach ($userids as $userid) {
            // Delete calendar events.
            $DB->delete_records('event', ['modulename' => 'kanban', 'instance' => $cm->instance, 'userid' => $userid]);

            $boardids = $DB->get_fieldset_select(
                'kanban_board',
                'id',
                'kanban_instance = :instance',
                ['instance' => $cm->instance]
            );

            if (!empty($boardids)) {
                [$insql, $params] = $DB->get_in_or_equal($boardids, SQL_PARAMS_NAMED);

                // Delete history.
                $params['userid'] = $userid;
                $DB->delete_records_select('kanban_history', 'userid = :userid AND kanban_board ' . $insql, $params);
                $DB->execute(
                    'UPDATE {kanban_history} SET affected_userid = 0 WHERE affected_userid = :userid AND kanban_board ' . $insql,
                    $params
                );

                // Remove card author.
                $DB->execute(
                    'UPDATE {kanban_card} SET createdby = 0 WHERE createdby = :userid AND kanban_board ' . $insql,
                    $params
                );

                $sql = 'SELECT id FROM {kanban_card} WHERE kanban_board ' . $insql;
                $cardids = $DB->get_fieldset_sql($sql, $params);

                if (!empty($cardids)) {
                    [$insql, $params] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
                    $sql = 'userid = :userid AND kanban_card ' . $insql;
                    $params['userid'] = $userid;
                    // Unassign user.
                    $DB->delete_records_select('kanban_assignee', $sql, $params);
                    // Delete discussion.
                    $DB->delete_records_select('kanban_discussion_comment', 'kanban_card ' . $insql, $params);
                }
            }

            // Get all personal boards.
            $boardid = $DB->get_field_select(
                'kanban_board',
                'id',
                'kanban_instance = :instance AND userid = :userid',
                ['instance' => $cm->instance, 'userid' => $userid]
            );
            $cardids = $DB->get_fieldset_select('kanban_card', 'id', 'kanban_board = :board', ['board' => $boardid]);

            if (!empty($cardids)) {
                // Unassign all users from private board.
                [$insql, $params] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('kanban_assignee', 'kanban_card ' . $insql, $params);
                // Delete all discussions.
                $DB->delete_records_select('kanban_discussion_comment', 'kanban_card ' . $insql, $params);
            }

            $DB->delete_records('kanban_card', ['kanban_board' => $boardid]);
            $DB->delete_records('kanban_column', ['kanban_board' => $boardid]);
            $DB->delete_records('kanban_board', ['id' => $boardid]);
            $DB->delete_records('kanban_history', ['kanban_board' => $boardid]);
        }
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'cmid' => $context->instanceid,
            'modname' => 'kanban',
        ];

        // Personal boards.
        $sql = "SELECT DISTINCT b.userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        // Created cards.
        $sql = "SELECT DISTINCT c.createdby as userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
            INNER JOIN {kanban_card} c ON c.kanban_board = b.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        // Assigned cards.
        $sql = "SELECT DISTINCT a.userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
            INNER JOIN {kanban_card} c ON c.kanban_board = b.id
            INNER JOIN {kanban_assignee} a ON a.kanban_card = c.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        // Discussion comments.
        $sql = "SELECT DISTINCT d.userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
            INNER JOIN {kanban_card} c ON c.kanban_board = b.id
            INNER JOIN {kanban_discussion_comment} d ON d.kanban_card = c.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        // History items.
        $sql = "SELECT DISTINCT h.userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
            INNER JOIN {kanban_history} h ON h.kanban_board = b.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);

        // History items - affected user.
        $sql = "SELECT DISTINCT h.affected_userid as userid
                  FROM {course_modules} cm
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON k.id = b.kanban_instance
            INNER JOIN {kanban_history} h ON h.kanban_board = b.id
                 WHERE cm.id = :cmid";

        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return  contextlist   $contextlist  The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $params = [
            'modname' => 'kanban',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];

        // Get contexts with assigned cards.
        $sql = "SELECT c.id
                  FROM {context} c
                  INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  INNER JOIN {kanban_board} b ON b.kanban_instance = cm.instance
                  INNER JOIN {kanban_card} ca ON ca.kanban_board = b.id
                  INNER JOIN {kanban_assignee} a ON a.kanban_card = ca.id
                 WHERE a.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        // Get contexts with private boards. This feature is not implemented yet.
        $sql = "SELECT c.id
                  FROM {context} c
                  INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  INNER JOIN {kanban_board} b ON b.kanban_instance = cm.instance
                 WHERE b.userid = :userid
        ";
        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        // Array to store all the data to be exported, this is needed to export the data sorted by course module.
        // Structure of the array:
        // $cms[cmid][boardid][columnid] =
        // ['cards' => [cardid] => ['carddata' => ... , 'historydata' => ... , 'discussiondata' => ...]
        // 'columndata' => ['columntitle']].
        $cms = [];

        $user = $contextlist->get_user();
        $userid = $user->id;

        [$contextsql, $contextparams] = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Get all cards the user is assigned to without private board of the user.
        $sql = "SELECT ca.id AS cardid,
                       cm.id AS cmid,
                       b.id AS boardid,
                       ca.kanban_column AS columnid,
                       co.title AS columntitle,
                       ca.title AS cardtitle,
                       ca.timemodified AS timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON b.kanban_instance = k.id AND b.userid = 0
            INNER JOIN {kanban_column} co ON co.kanban_board = b.id
            INNER JOIN {kanban_card} ca ON ca.kanban_column = co.id
            INNER JOIN {kanban_assignee} a ON a.kanban_card = ca.id
                 WHERE c.id {$contextsql} AND a.userid = :userid
              ORDER BY cm.id";

        $params = [
            'modname' => 'kanban',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ] + $contextparams;

        $entries = $DB->get_records_sql($sql, $params);
        foreach ($entries as $entry) {
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid]['cards'][(int)$entry->cardid] = [
                'carddata' => [
                    'title' => $entry->cardtitle,
                    'timemodified' => \core_privacy\local\request\transform::datetime($entry->timemodified),
                ],
            ];
        }

        // Get all cards the user has created without private board of the user.
        $sql = "SELECT ca.id AS cardid,
                       cm.id AS cmid,
                       b.id AS boardid,
                       co.id AS columnid,
                       co.title AS columntitle,
                       ca.title AS cardtitle,
                       ca.timemodified AS timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON b.kanban_instance = k.id AND b.userid = 0
            INNER JOIN {kanban_column} co ON co.kanban_board = b.id
            INNER JOIN {kanban_card} ca ON ca.kanban_column = co.id
                 WHERE c.id {$contextsql} AND ca.createdby = :userid
              ORDER BY cm.id";

        $params = [
            'modname' => 'kanban',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ] + $contextparams;

        $entries = $DB->get_records_sql($sql, $params);

        foreach ($entries as $entry) {
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid][(int)$entry->cardid] = [
                'carddata' => [
                    'title' => $entry->cardtitle,
                    'timemodified' => \core_privacy\local\request\transform::datetime($entry->timemodified),
                ],
            ];
        }

        // Get all history items the user is part of.
        $sql = "SELECT cm.id AS cmid,
                       b.id AS boardid,
                       ca.kanban_column AS columnid,
                       h.action AS historyaction,
                       ca.id AS cardid,
                       h.timestamp AS timemodified,
                       h.userid AS historyuserid,
                       h.affected_userid AS historyaffecteduserid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON c.id {$contextsql} AND cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON b.kanban_instance = k.id
            INNER JOIN {kanban_history} h ON h.kanban_board = b.id AND (h.userid = :userid OR h.affected_userid = :userid2)
            INNER JOIN {kanban_card} ca ON ca.id = h.kanban_card
              ORDER BY h.timestamp";

        $params = [
            'modname' => 'kanban',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'userid2' => $userid,
        ] + $contextparams;

        $entries = $DB->get_records_sql($sql, $params);

        // Add history data to the corresponding card data. If the user is not the one who triggered the history
        // action or the affected user, set the userid to 0 to avoid exporting other users' ids.
        foreach ($entries as $entry) {
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid]['cards'][(int)$entry->cardid]['historydata'][] = [
                'action' => $entry->historyaction,
                'timemodified' => \core_privacy\local\request\transform::datetime($entry->timemodified),
                'userid' => $entry->historyuserid == $userid ? $entry->historyuserid : 0,
                'affected_userid' => $entry->historyaffecteduserid == $userid ? $entry->historyaffecteduserid : 0,
            ];
        }

        // Get all discussion messages created by the user.

        $sql = "SELECT d.id AS discussionid,
                       cm.id AS cmid,
                       d.content AS content,
                       ca.id AS cardid,
                       ca.kanban_column AS columnid,
                       b.id AS boardid,
                       d.timecreated AS timemodified
                  FROM {context} c
            INNER JOIN {course_modules} cm ON c.id {$contextsql} AND cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON b.kanban_instance = k.id
            INNER JOIN {kanban_column} co ON co.kanban_board = b.id
            INNER JOIN {kanban_card} ca ON ca.kanban_column = co.id
            INNER JOIN {kanban_discussion_comment} d ON d.kanban_card = ca.id AND d.userid = :userid
              ORDER BY d.timecreated";

        $params = ['modname' => 'kanban', 'contextlevel' => CONTEXT_MODULE, 'userid' => $userid] + $contextparams;

        $entries = $DB->get_records_sql($sql, $params);

        foreach ($entries as $entry) {
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid]['cards'][(int)$entry->cardid] = [
                'discussiondata' => [
                    'content' => $entry->content,
                    'timemodified' => \core_privacy\local\request\transform::datetime($entry->timemodified),
                ],
            ];
        }

        // Get all data from personal boards.
        $sql = "SELECT DISTINCT ca.id AS cardid,
                       cm.id AS cmid,
                       ca.kanban_column AS columnid,
                       b.id AS boardid,
                       co.title AS columntitle,
                       ca.title AS cardtitle,
                       ca.timemodified AS timemodified,
                       a.userid AS assigneeuserid
                  FROM {context} c
            INNER JOIN {course_modules} cm ON c.id {$contextsql} AND cm.id = c.instanceid AND c.contextlevel = :contextlevel
            INNER JOIN {modules} m ON m.id = cm.module AND m.name = :modname
            INNER JOIN {kanban} k ON k.id = cm.instance
            INNER JOIN {kanban_board} b ON b.kanban_instance = k.id AND b.userid = :userid
            INNER JOIN {kanban_column} co ON co.kanban_board = b.id
            INNER JOIN {kanban_card} ca ON ca.kanban_column = co.id
             LEFT JOIN {kanban_assignee} a ON a.kanban_card = ca.id AND a.userid = :userid2
              ORDER BY cm.id";

        $params = [
            'modname' => 'kanban',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
            'userid2' => $userid,
        ] + $contextparams;

        $entries = $DB->get_records_sql($sql, $params);
        foreach ($entries as $entry) {
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid]['cards'][(int)$entry->cardid] = [
                'carddata' => [
                    'title' => $entry->cardtitle,
                    'timemodified' => \core_privacy\local\request\transform::datetime($entry->timemodified),
                    'assignee' => $entry->assigneeuserid ?? 0,
                ],
            ];
            $cms[(int)$entry->cmid][(int)$entry->boardid][(int)$entry->columnid]['columndata'] = [
                'columntitle' => $entry->columntitle,
            ];
        }

        self::export_kanban_data($cms, $user);
    }

    /**
     * Write kanban data.
     *
     * @param array $cms The data to be exported sorted by course module id.
     * @param stdClass $user
     * @return void
     */
    public static function export_kanban_data(array $cms, stdClass $user): void {
        foreach ($cms as $cmid => $boards) {
            $context = \context_module::instance($cmid);
            $contextdata = helper::get_context_data($context, $user);
            $contextdata = (object) array_merge((array) $contextdata, ['boards' => $boards]);
            writer::with_context($context)->export_data([], $contextdata);
            helper::export_context_files($context, $user);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('kanban', $context->instanceid)) {
            return;
        }

        $boardids = $DB->get_fieldset_select('kanban_board', 'id', 'kanban_instance = :instance', ['instance' => $cm->instance]);
        if (!empty($boardids)) {
            [$insql, $params] = $DB->get_in_or_equal($boardids, SQL_PARAMS_NAMED);
            $sql = 'SELECT id FROM {kanban_card} WHERE kanban_board ' . $insql;
            $cardids = $DB->get_fieldset_sql($sql, $params);

            // Delete all assignees (this needs to be done also for template boards).
            [$insql, $params] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('kanban_assignee', 'kanban_card ' . $insql, $params);

            // Delete discussion.
            $DB->delete_records_select('kanban_discussion_comment', 'kanban_card ' . $insql, $params);

            // Delete all columns from boards that are no template boards.
            $boardids = $DB->get_fieldset_select(
                'kanban_board',
                'id',
                'kanban_instance = :instance AND template = 0',
                ['instance' => $cm->instance]
            );
            [$insql, $params] = $DB->get_in_or_equal($boardids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('kanban_column', 'kanban_board ' . $insql, $params);

            // Delete history.
            $DB->delete_records_select('kanban_history', 'kanban_board ' . $insql, $params);
        }
        // Delete all boards that are no template boards.
        $DB->delete_records('kanban_board', ['instance' => $cm->instance, 'template' => 0]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist as $context) {
            if (!$context instanceof \context_module) {
                return;
            }

            if (!$cm = get_coursemodule_from_id('kanban', $context->instanceid)) {
                return;
            }

            // Delete calendar events.
            $DB->delete_records('event', ['modulename' => 'kanban', 'instance' => $cm->instance, 'userid' => $userid]);

            $boardids = $DB->get_fieldset_select(
                'kanban_board',
                'id',
                'kanban_instance = :instance',
                ['instance' => $cm->instance]
            );
            if (!empty($boardids)) {
                [$insql, $params] = $DB->get_in_or_equal($boardids, SQL_PARAMS_NAMED);

                // Delete history.
                $params['userid'] = $userid;
                $DB->delete_records_select('kanban_history', 'userid = :userid AND kanban_board ' . $insql, $params);
                $DB->execute(
                    'UPDATE {kanban_history} SET affected_userid = 0 WHERE affected_userid = :userid AND kanban_board ' . $insql,
                    $params
                );

                // Remove card author.
                $DB->execute(
                    'UPDATE {kanban_card} SET createdby = 0 WHERE createdby = :userid AND kanban_board ' . $insql,
                    $params
                );

                $sql = 'SELECT id FROM {kanban_card} WHERE kanban_board ' . $insql;
                $cardids = $DB->get_fieldset_sql($sql, $params);

                if (!empty($cardids)) {
                    [$insql, $params] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
                    // Delete discussion.
                    $DB->delete_records_select('kanban_discussion_comment', 'kanban_card ' . $insql, $params);
                    $sql = 'userid = :userid AND kanban_card ' . $insql;
                    $params['userid'] = $userid;
                    // Unassign user.
                    $DB->delete_records_select('kanban_assignee', $sql, $params);
                }
            }

            // Get all personal boards.
            $boardid = $DB->get_field_select(
                'kanban_board',
                'id',
                'kanban_instance = :instance AND userid = :userid',
                ['instance' => $cm->instance, 'userid' => $userid]
            );
            $cardids = $DB->get_fieldset_select('kanban_card', 'id', 'kanban_board = :board', ['board' => $boardid]);

            if (!empty($cardids)) {
                // Unassign all users from private board.
                [$insql, $params] = $DB->get_in_or_equal($cardids, SQL_PARAMS_NAMED);
                $DB->delete_records_select('kanban_assignee', 'kanban_card ' . $insql, $params);
                // Delete all discussions.
                $DB->delete_records_select('kanban_discussion_comment', 'kanban_card ' . $insql, $params);
            }

            $DB->delete_records('kanban_card', ['kanban_board' => $boardid]);
            $DB->delete_records('kanban_column', ['kanban_board' => $boardid]);
            $DB->delete_records('kanban_board', ['id' => $boardid]);
            $DB->delete_records('kanban_history', ['kanban_board' => $boardid]);
        }
    }

    /**
     * Returns meta data about this system.
     *
     * @param   collection     $collection The initialised collection to add items to.
     * @return  collection     A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('kanban_board', [
            'userid' => 'privacy:metadata:userid',
            'groupid' => 'privacy:metadata:groupid',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:kanban_board');

        $collection->add_database_table('kanban_column', [
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
        ], 'privacy:metadata:kanban_column');

        $collection->add_database_table('kanban_card', [
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
            'createdby' => 'privacy:metadata:createdby',
        ], 'privacy:metadata:kanban_card');

        $collection->add_database_table('kanban_assignee', [
            'userid' => 'privacy:metadata:userid',
            'kanban_card' => 'privacy:metadata:kanban_card',
        ], 'privacy:metadata:kanban_assignee');

        $collection->add_database_table('kanban_discussion_comment', [
            'userid' => 'privacy:metadata:userid',
            'kanban_card' => 'privacy:metadata:kanban_card',
            'content' => 'privacy:metadata:content',
            'timecreated' => 'privacy:metadata:timecreated',
        ], 'privacy:metadata:kanban_discussion_comment');

        $collection->add_database_table('kanban_history', [
            'userid' => 'privacy:metadata:userid',
            'kanban_board' => 'privacy:metadata:kanban_board',
            'kanban_column' => 'privacy:metadata:kanban_column',
            'kanban_card' => 'privacy:metadata:kanban_card',
            'parameters' => 'privacy:metadata:parameters',
            'action' => 'privacy:metadata:action',
            'affected_userid' => 'privacy:metadata:affected_userid',
            'timestamp' => 'privacy:metadata:timestamp',
        ], 'privacy:metadata:kanban_history');

        return $collection;
    }
}
