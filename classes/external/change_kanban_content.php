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
 * Class for modifying kanban content
 *
 * @package    mod_kanban
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kanban\external;

// Compatibility with Moodle < 4.2.
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/lib/externallib.php');

use coding_exception;
use context_module;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kanban\boardmanager;
use mod_kanban\helper;
use moodle_exception;
use required_capability_exception;
use restricted_context_exception;

/**
 * Class for modifying kanban content.
 *
 * @copyright   2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class change_kanban_content extends external_api {
    /**
     * Returns description of method parameters for the add_column function.
     *
     * @return external_function_parameters
     */
    public static function add_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'title' => new external_value(
                    PARAM_TEXT,
                    'title of the new column',
                    VALUE_OPTIONAL,
                    get_string('newcolumn', 'mod_kanban')
                ),
                'aftercol' => new external_value(PARAM_INT, 'insert column after this id', VALUE_OPTIONAL, 0),
            ]),
        ]);
    }

    /**
     * Definition of return values of the get_kanban_content webservice function.
     *
     * @return external_single_structure
     */
    public static function add_column_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method adds a new column to the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'aftercol' the column to insert after and 'title'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function add_column(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::add_column_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $data = [];
        if (!empty($params['data']['title'])) {
            // Additional cleaning most likely not needed, because title is PARAM_TEXT, but let's be extra sure.
            $data['title'] = clean_param($params['data']['title'], PARAM_TEXT);
        }
        $aftercol = $params['data']['aftercol'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:managecolumns', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->add_column($aftercol, $data);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the add_card function.
     *
     * @return external_function_parameters
     */
    public static function add_card_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'title' => new external_value(
                    PARAM_TEXT,
                    'title of the new card',
                    VALUE_OPTIONAL,
                    get_string('newcard', 'mod_kanban')
                ),
                'columnid' => new external_value(PARAM_INT, 'column id', VALUE_REQUIRED),
                'aftercard' => new external_value(PARAM_INT, 'insert card after this id', VALUE_OPTIONAL, 0),
            ]),
        ]);
    }

    /**
     * Definition of return values of the get_kanban_content webservice function.
     *
     * @return external_single_structure
     */
    public static function add_card_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method adds a new card to the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'aftercard' the card to insert after, 'title' and the id of the column 'columnid'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function add_card(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::add_card_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $columnid = $params['data']['columnid'];
        $data = [];
        if (!empty($params['data']['title'])) {
            // Additional cleaning most likely not needed, because title is PARAM_TEXT, but let's be extra sure.
            $data['title'] = clean_param($params['data']['title'], PARAM_TEXT);
        }
        $aftercard = $params['data']['aftercard'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:addcard', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->add_card($columnid, $aftercard, $data);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the move_column function.
     *
     * @return external_function_parameters
     */
    public static function move_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'columnid' => new external_value(PARAM_INT, 'id of the moved column', VALUE_REQUIRED),
                'aftercol' => new external_value(PARAM_INT, 'move column after this id', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the move_column function.
     *
     * @return external_single_structure
     */
    public static function move_column_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method moves a column within the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'columnid' and 'aftercol' the column to move after
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function move_column(int $cmid, int $boardid, array $data): array {
        global $DB;
        $params = self::validate_parameters(self::move_column_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $aftercol = $params['data']['aftercol'];
        $columnid = $params['data']['columnid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:managecolumns', $context);

        $boardmanager = new boardmanager($cmid, $boardid);

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->move_column($columnid, $aftercol);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the move_card function.
     *
     * @return external_function_parameters
     */
    public static function move_card_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the moved card', VALUE_REQUIRED),
                'columnid' => new external_value(PARAM_INT, 'id of the target column', VALUE_REQUIRED),
                'aftercard' => new external_value(PARAM_INT, 'move card after this card', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the move_card function.
     *
     * @return external_single_structure
     */
    public static function move_card_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method moves a card within the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid', 'columnid' and 'aftercard' the column/card to move after
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function move_card(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::move_card_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $aftercard = $params['data']['aftercard'];
        $columnid = $params['data']['columnid'];
        $cardid = $params['data']['cardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        $boardmanager = new boardmanager($cmid, $boardid);

        if (!$boardmanager->can_user_manage_specific_card($cardid)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanban');
        }

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->move_card($cardid, $aftercard, $columnid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the delete_card function.
     *
     * @return external_function_parameters
     */
    public static function delete_card_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the moved card', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the delete_card function.
     *
     * @return external_single_structure
     */
    public static function delete_card_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method deletes a card from the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function delete_card(int $cmid, int $boardid, array $data): array {
        global $USER;
        $params = self::validate_parameters(self::delete_card_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        $boardmanager = new boardmanager($cmid, $boardid);

        if (!$boardmanager->can_user_manage_specific_card($cardid)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanban');
        }

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->delete_card($cardid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the delete_column function.
     *
     * @return external_function_parameters
     */
    public static function delete_column_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'columnid' => new external_value(PARAM_INT, 'id of the column', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the delete_column function.
     *
     * @return external_single_structure
     */
    public static function delete_column_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method deletes a column from the board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'columnid'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function delete_column(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::delete_column_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $columnid = $params['data']['columnid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:managecolumns', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->delete_column($columnid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the assign_user function.
     *
     * @return external_function_parameters
     */
    public static function assign_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the column', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL),
            ]),
        ]);
    }

    /**
     * Definition of return values of the assign_user function.
     *
     * @return external_single_structure
     */
    public static function assign_user_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method assigns a user to a card.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid' and 'userid'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function assign_user(int $cmid, int $boardid, array $data): array {
        global $USER;
        $params = self::validate_parameters(self::assign_user_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        $userid = $params['data']['userid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        if (empty($userid)) {
            require_capability('mod/kanban:assignself', $context);
            $userid = $USER->id;
        } else {
            require_capability('mod/kanban:assignothers', $context);
        }

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->assign_user($cardid, $userid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the unassign_user function.
     *
     * @return external_function_parameters
     */
    public static function unassign_user_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the column', VALUE_REQUIRED),
                'userid' => new external_value(PARAM_INT, 'user id', VALUE_OPTIONAL, 0),
            ]),
        ]);
    }

    /**
     * Definition of return values of the assign_user function.
     *
     * @return external_single_structure
     */
    public static function unassign_user_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method unassigns a user from a card.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid' and 'userid'
     * @return bool Whether the request was successful
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function unassign_user(int $cmid, int $boardid, array $data): array {
        global $USER;
        $params = self::validate_parameters(self::unassign_user_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        $userid = $params['data']['userid'];
        if (empty($userid)) {
            $userid = $USER->id;
        }
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        if ($userid == $USER->id) {
            require_capability('mod/kanban:assignself', $context);
        } else {
            require_capability('mod/kanban:assignothers', $context);
        }

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->unassign_user($cardid, $userid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the set_card_complete function.
     *
     * @return external_function_parameters
     */
    public static function set_card_complete_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the moved card', VALUE_REQUIRED),
                'state' => new external_value(PARAM_INT, 'completion state', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the set_card_complete function.
     *
     * @return external_single_structure
     */
    public static function set_card_complete_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method sets the completion state of a card.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid' and 'state'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function set_card_complete(int $cmid, int $boardid, array $data): array {
        global $USER;
        $params = self::validate_parameters(self::set_card_complete_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $state = $params['data']['state'];
        $cardid = $params['data']['cardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        $boardmanager = new boardmanager($cmid, $boardid);

        if (!$boardmanager->can_user_manage_specific_card($cardid)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanban');
        }

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->set_card_complete($cardid, $state);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the set_column_locked function.
     *
     * @return external_function_parameters
     */
    public static function set_column_locked_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'columnid' => new external_value(PARAM_INT, 'id of the column', VALUE_REQUIRED),
                'state' => new external_value(PARAM_INT, 'lock state', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the set_column_locked function.
     *
     * @return external_single_structure
     */
    public static function set_column_locked_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method sets the lock state of a column.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'columnid' and 'state'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function set_column_locked(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::set_column_locked_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $state = $params['data']['state'];
        $columnid = $params['data']['columnid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:managecolumns', $context);

        $boardmanager = new boardmanager($cmid, $boardid);

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->set_column_locked($columnid, $state);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the set_board_columns_locked function.
     *
     * @return external_function_parameters
     */
    public static function set_board_columns_locked_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'state' => new external_value(PARAM_INT, 'lock state', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the set_board_columns_locked function.
     *
     * @return external_single_structure
     */
    public static function set_board_columns_locked_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method sets the lock state of a board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'state'
     * @return bool Whether the request was successful
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function set_board_columns_locked(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::set_board_columns_locked_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $state = $params['data']['state'];

        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:manageboard', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->set_board_columns_locked($state);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the add_discussion_message function.
     *
     * @return external_function_parameters
     */
    public static function add_discussion_message_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'card id', VALUE_REQUIRED),
                'message' => new external_value(PARAM_TEXT, 'message', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the add_discussion_message function.
     *
     * @return external_single_structure
     */
    public static function add_discussion_message_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This adds a message to a discussion.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardId' and 'message'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function add_discussion_message(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::add_discussion_message_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        // Additional cleaning most likely not needed, because message is PARAM_TEXT, but let's be extra sure.
        $message = clean_param($params['data']['message'], PARAM_TEXT);

        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:view', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->add_discussion_message($cardid, $message);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the delete_discussion_message function.
     *
     * @return external_function_parameters
     */
    public static function delete_discussion_message_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'messageid' => new external_value(PARAM_INT, 'message id', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the delete_discussion_message function.
     *
     * @return external_single_structure
     */
    public static function delete_discussion_message_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method deletes a message from a discussion.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'messageId'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function delete_discussion_message(int $cmid, int $boardid, array $data): array {
        global $USER;
        $params = self::validate_parameters(self::delete_discussion_message_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $messageid = $params['data']['messageid'];

        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:view', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $message = $boardmanager->get_discussion_message($messageid);

        if ($message->userid != $USER->id) {
            require_capability('mod/kanban:manageboard', $context);
        }

        $boardmanager->delete_discussion_message($messageid, $message->kanban_card);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the save_as_template function.
     *
     * @return external_function_parameters
     */
    public static function save_as_template_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Definition of return values of the save_as_template function.
     *
     * @return external_single_structure
     */
    public static function save_as_template_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * This method saves the current board as template for the whole kanban activity.
     * This does _not_ affect existing sub-boards (e.g. personal boards or group boards).
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function save_as_template(int $cmid, int $boardid): array {
        $params = self::validate_parameters(self::save_as_template_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:manageboard', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);
        $boardmanager->create_template();

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the delete_board function.
     *
     * @return external_function_parameters
     */
    public static function delete_board_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Definition of return values of the delete_board function.
     *
     * @return external_single_structure
     */
    public static function delete_board_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * Delete this board.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function delete_board(int $cmid, int $boardid): array {
        global $USER;
        $params = self::validate_parameters(self::delete_board_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        $boardmanager = new boardmanager($cmid, $boardid);

        if ($boardmanager->get_board()->userid != $USER->id) {
            require_capability('mod/kanban:manageboard', $context);
        }

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->delete_board($boardid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the push_card_copy function.
     *
     * @return external_function_parameters
     */
    public static function push_card_copy_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'card id', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the push_card_copy function.
     *
     * @return external_single_structure
     */
    public static function push_card_copy_returns(): external_single_structure {
        return self::default_returns();
    }

    /**
     * Push a copy of a card to all boards.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param array $data array containing 'cardid'
     * @return array the updated data formatted as update message
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function push_card_copy(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::push_card_copy_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:manageboard', $context);

        $boardmanager = new boardmanager($cmid, $boardid);

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->push_card_copy($cardid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Definition of default return values for all functions.
     *
     * @return external_single_structure
     */
    public static function default_returns(): external_single_structure {
        return
            new external_single_structure(
                [
                    'success' => new external_value(PARAM_BOOL, 'success', VALUE_OPTIONAL, true),
                    'update' => new external_value(PARAM_RAW, 'Encoded course update JSON'),
                ]
            );
    }

    /**
     * Duplicate a card on the board.
     *
     * @param int $cmid The course module id of the kanban board.
     * @param int $boardid The id of the kanban board.
     * @param array $data containing 'cardid'
     * @return array The updated data formatted as update message.
     */
    public static function duplicate_card(int $cmid, int $boardid, array $data): array {
        $params = self::validate_parameters(self::duplicate_card_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'data' => $data,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $cardid = $params['data']['cardid'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);

        require_capability('mod/kanban:addcard', $context);

        $boardmanager = new boardmanager($cmid, $boardid);

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->duplicate_card($cardid);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Returns description of method parameters for the duplicate_card function.
     *
     * @return external_function_parameters
     */
    public static function duplicate_card_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'data' => new external_single_structure([
                'cardid' => new external_value(PARAM_INT, 'id of the card to duplicate', VALUE_REQUIRED),
            ]),
        ]);
    }

    /**
     * Definition of return values of the duplicate_card function.
     *
     * @return external_single_structure
     */
    public static function duplicate_card_returns(): external_single_structure {
        return self::default_returns();
    }
}
