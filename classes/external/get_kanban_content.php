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
 * Class for delivering kanban content
 *
 * @package    mod_kanban
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kanban\external;

// Compatibility with Moodle < 4.2.
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/lib/externallib.php');
require_once($CFG->dirroot . '/mod/kanban/lib.php');

use coding_exception;
use context_module;
use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;
use invalid_parameter_exception;
use mod_kanban\boardmanager;
use mod_kanban\constants;
use mod_kanban\helper;
use mod_kanban\numberfilter;
use mod_kanban\updateformatter;
use moodle_exception;
use required_capability_exception;
use restricted_context_exception;
use stdClass;

/**
 * Class for delivering kanban content
 *
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_kanban_content extends external_api {
    /**
     * Returns description of method parameters for the execute webservice function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'timestamp' => new external_value(PARAM_INT, 'only get values modified after this timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns description of method parameters for the get_kanban_content_init webservice function.
     *
     * @return external_function_parameters
     */
    public static function get_kanban_content_init_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'timestamp' => new external_value(PARAM_INT, 'only get values modified after this timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Returns description of method parameters for the get_kanban_content_update webservice function.
     *
     * @return external_function_parameters
     */
    public static function get_kanban_content_update_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'timestamp' => new external_value(PARAM_INT, 'only get values modified after this timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Definition of return values of the get_kanban_content webservice function.
     *
     * @return external_single_structure
     */
    public static function get_kanban_content_init_returns(): external_single_structure {
        return
            new external_single_structure(
                [
                    'common' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'cmid'),
                        'timestamp' => new external_value(PARAM_INT, 'timestamp'),
                        'userid' => new external_value(PARAM_INT, 'current user id'),
                        'lang' => new external_value(PARAM_TEXT, 'language for the ui'),
                        'liveupdate' => new external_value(PARAM_INT, 'seconds between two live updates'),
                        'template' => new external_value(PARAM_INT, 'boardid for template', VALUE_OPTIONAL, 0),
                        'groupmode' => new external_value(PARAM_INT, 'group mode'),
                        'groupselector' => new external_value(PARAM_RAW, 'group selector'),
                        'userboards' => new external_value(PARAM_INT, 'userboards'),
                        'history' => new external_value(PARAM_INT, 'history'),
                        'updatefails' => new external_value(PARAM_INT, 'updatefails', VALUE_OPTIONAL, 0),
                        'usenumbers' => new external_value(PARAM_INT, 'use numbers for the cards'),
                    ]),
                    'board' => new external_single_structure([
                        'id' => new external_value(PARAM_INT, 'board id'),
                        'sequence' => new external_value(PARAM_TEXT, 'order of the columns in the board'),
                        'timemodified' => new external_value(PARAM_INT, 'timemodified'),
                        'locked' => new external_value(PARAM_INT, 'lock state'),
                        'userid' => new external_value(PARAM_INT, 'userboard for userid', VALUE_OPTIONAL, 0),
                        'groupid' => new external_value(PARAM_INT, 'groupboard for groupid', VALUE_OPTIONAL, 0),
                        'template' => new external_value(PARAM_INT, 'board is a template', VALUE_OPTIONAL, 0),
                        'heading' => new external_value(PARAM_TEXT, 'heading of the board'),
                        'hasaddcards' => new external_value(
                            PARAM_BOOL,
                            'whether the board has at least one column with addcards option',
                            VALUE_OPTIONAL,
                            false
                        ),
                        'showauthors' => new external_value(
                            PARAM_BOOL,
                            'whether to show authors in the board',
                            VALUE_OPTIONAL,
                            false
                        ),
                    ]),
                    'columns' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'column id'),
                                'title' => new external_value(PARAM_TEXT, 'column title'),
                                'sequence' => new external_value(PARAM_TEXT, 'order of the cards in the column'),
                                'locked' => new external_value(PARAM_BOOL, 'lock state of the column'),
                                'options' => new external_value(PARAM_TEXT, 'options for the column'),
                            ],
                            '',
                            VALUE_OPTIONAL
                        )
                    ),
                    'cards' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'card id'),
                                'title' => new external_value(PARAM_TEXT, 'card title'),
                                'kanban_column' => new external_value(PARAM_INT, 'column'),
                                'duedate' => new external_value(PARAM_INT, 'due date'),
                                'options' => new external_value(PARAM_TEXT, 'options for the card'),
                                'assignees' => new external_multiple_structure(
                                    new external_value(PARAM_INT, 'user id'),
                                    VALUE_OPTIONAL
                                ),
                                'selfassigned' => new external_value(
                                    PARAM_BOOL,
                                    'is current user assigned to the card?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'completed' => new external_value(
                                    PARAM_BOOL,
                                    'is card completed?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'hasdescription' => new external_value(
                                    PARAM_BOOL,
                                    'has a description?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'description' => new external_value(
                                    PARAM_RAW,
                                    'description',
                                    VALUE_OPTIONAL,
                                    ''
                                ),
                                'hasattachment' => new external_value(
                                    PARAM_BOOL,
                                    'has an attachment?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'attachments' => new external_multiple_structure(
                                    new external_single_structure([
                                        'url' => new external_value(PARAM_URL, 'attachment url', VALUE_REQUIRED),
                                        'name' => new external_value(PARAM_TEXT, 'filename', VALUE_REQUIRED),
                                    ]),
                                    'attachments',
                                    VALUE_OPTIONAL,
                                    []
                                ),
                                'discussion' => new external_value(
                                    PARAM_BOOL,
                                    'has a discussion?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'createdby' => new external_value(
                                    PARAM_INT,
                                    'original creator of the card',
                                    VALUE_OPTIONAL,
                                    0
                                ),
                                'createdbyname' => new external_value(
                                    PARAM_TEXT,
                                    'name of the original creator of the card',
                                    VALUE_OPTIONAL,
                                    ''
                                ),
                                'canedit' => new external_value(
                                    PARAM_BOOL,
                                    'current user can edit this card?',
                                    VALUE_OPTIONAL,
                                    false
                                ),
                                'number' => new external_value(
                                    PARAM_INT,
                                    'number of the card',
                                ),
                            ],
                            '',
                            VALUE_OPTIONAL
                        )
                    ),
                    'users' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'user id'),
                                'fullname' => new external_value(PARAM_TEXT, 'user fullname'),
                                'userpicture' => new external_value(PARAM_RAW, 'user picture'),
                            ],
                            '',
                            VALUE_OPTIONAL
                        ),
                        '',
                        VALUE_OPTIONAL
                    ),
                    'capabilities' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_TEXT, 'capability name'),
                                'value' => new external_value(PARAM_BOOL, 'capability value'),
                            ],
                            '',
                            VALUE_OPTIONAL
                        ),
                    ),
                    'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'timecreated' => new external_value(PARAM_INT, 'timecreated'),
                                'userid' => new external_value(PARAM_INT, 'userid'),
                                'kanban_card' => new external_value(PARAM_INT, 'card id'),
                                'content' => new external_value(PARAM_TEXT, 'discussion message'),
                                'username' => new external_value(PARAM_TEXT, 'user name'),
                                'candelete' => new external_value(PARAM_BOOL, 'whether the current user can delete this message'),
                            ],
                            '',
                            VALUE_OPTIONAL
                        ),
                    ),
                    'history' => new external_multiple_structure(
                        new external_single_structure(
                            [
                                'id' => new external_value(PARAM_INT, 'id'),
                                'timestamp' => new external_value(PARAM_INT, 'timestamp'),
                                'userid' => new external_value(PARAM_INT, 'userid'),
                                'kanban_card' => new external_value(PARAM_INT, 'card id'),
                                'kanban_column' => new external_value(PARAM_INT, 'column'),
                                'content' => new external_value(PARAM_TEXT, 'discussion message'),
                                'affectedusername' => new external_value(PARAM_TEXT, 'user name'),
                            ],
                            '',
                            VALUE_OPTIONAL
                        ),
                    ),
                ]
            );
    }

    /**
     * This method returns the requested data.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param int $timestamp the timestamp of the state present in the frontend
     * @return array The requested content, divided into board, columns and cards
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function get_kanban_content_init(int $cmid, int $boardid, int $timestamp = 0): array {
        return self::execute($cmid, $boardid, $timestamp);
    }

    /**
     * This method returns the requested data.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param int $timestamp the timestamp of the state present in the frontend
     * @return array The requested content, divided into board, columns and cards
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function get_kanban_content_update(int $cmid, int $boardid, int $timestamp = 0): array {
        return self::execute($cmid, $boardid, $timestamp, true);
    }

    /**
     * Definition of return values of the get_kanban_content_update webservice function.
     *
     * @return external_single_structure
     */
    public static function get_kanban_content_update_returns(): external_single_structure {
        return new external_single_structure(
            [
                'update' => new external_value(PARAM_RAW, 'update JSON'),
            ]
        );
    }

    /**
     * Get kanban content from database.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param int $timestamp the timestamp of the state present in the frontend
     * @param bool $asupdate whether to format content as update for StateMananger
     * @return array The requested content, divided into board, columns and cards
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function execute(int $cmid, int $boardid, int $timestamp = 0, bool $asupdate = false): array {
        global $DB, $OUTPUT, $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'boardid' => $boardid,
            'timestamp' => $timestamp,
        ]);
        $cmid = $params['cmid'];
        $boardid = $params['boardid'];
        $timestamp = $params['timestamp'];
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:view', $context);

        // Get the values of some capabilities for output.
        $capabilities = [
            'addcard' => has_capability('mod/kanban:addcard', $context),
            'manageallcards' => has_capability('mod/kanban:manageallcards', $context),
            'manageassignedcards' => has_capability('mod/kanban:manageallcards', $context),
            'assignself' => has_capability('mod/kanban:assignself', $context),
            'assignothers' => has_capability('mod/kanban:assignothers', $context),
            'managecolumns' => has_capability('mod/kanban:managecolumns', $context),
            'editallboards' => has_capability('mod/kanban:editallboards', $context),
            'manageboard' => has_capability('mod/kanban:manageboard', $context),
            'viewhistory' => has_capability('mod/kanban:viewhistory', $context),
            'viewallboards' => has_capability('mod/kanban:viewallboards', $context),
        ];

        $params['board'] = $boardid;
        $params['timestamp'] = $timestamp;

        $boardmanager = new boardmanager($cmid, $boardid);

        $kanban = $DB->get_record('kanban', ['id' => $cminfo->instance]);

        $kanbanboard = helper::get_cached_board($boardid);
        $groupid = $kanbanboard->groupid;

        $kanbanboard->heading = get_string('courseboard', 'mod_kanban');
        $kanbanboard->showauthors = !empty($kanban->showauthors);
        $groupselector = null;
        $groupmode = groups_get_activity_groupmode($cminfo, $course);

        if (!$asupdate) {
            if (!empty($groupmode)) {
                $groupselector = groups_print_activity_menu(
                    $cminfo,
                    new \moodle_url('/mod/kanban/view.php', ['id' => $cminfo->id]),
                    true,
                    $kanban->userboards == constants::MOD_KANBAN_USERBOARDS_ONLY
                );
                $groupselector = preg_replace('/<?noscript>/i', '', $groupselector);
                $allowedgroups = groups_get_activity_allowed_groups($cminfo);
                if (!$allowedgroups) {
                    if ($kanban->userboards !== constants::MOD_KANBAN_NOUSERBOARDS) {
                        $groupselector = '';
                    } else {
                        throw new \moodle_exception('nogroupavailable', 'mod_kanban');
                    }
                } else if (count($allowedgroups) < 2) {
                    if (!empty($groupid)) {
                        $groupselector = '';
                    } else {
                        $group = array_pop($allowedgroups);
                        $groupselector = $OUTPUT->render_from_template(
                            'mod_kanban/groupbutton',
                            [
                                'cmid' => $cminfo->id,
                                'groupid' => $group->id,
                                'groupname' => $group->name,
                            ]
                        );
                    }
                }
            }

            if (!empty($kanbanboard->groupid)) {
                $kanbanboard->heading = get_string('groupboard', 'mod_kanban', groups_get_group_name($kanbanboard->groupid));
            }

            if (!empty($kanbanboard->userid)) {
                $boarduser = \core_user::get_user($kanbanboard->userid);
                $kanbanboard->heading = get_string('userboard', 'mod_kanban', fullname($boarduser));
            }

            if (!empty($kanbanboard->template)) {
                $kanbanboard->heading = get_string('template', 'mod_kanban');
            }
        }

        if (!(empty($kanbanboard->userid) && empty($kanbanboard->groupid))) {
            $restrictcaps = false;
            if (!empty($kanbanboard->userid) && $kanbanboard->userid != $USER->id) {
                require_capability('mod/kanban:viewallboards', $context);
                $restrictcaps = true;
            }
            if (!empty($kanbanboard->groupid)) {
                $members = groups_get_members($kanbanboard->groupid, 'u.id');
                $members = array_map(function ($v) {
                    return intval($v->id);
                }, $members);
                $ismember = in_array($USER->id, $members);
                if ($groupmode == SEPARATEGROUPS && !$ismember) {
                    require_capability('mod/kanban:viewallboards', $context);
                    $restrictcaps = true;
                }
                if ($groupmode == VISIBLEGROUPS && !$ismember) {
                    $restrictcaps = true;
                }
            }
            if ($restrictcaps) {
                $editcap = has_capability('mod/kanban:editallboards', $context);
                foreach ($capabilities as $cap => $value) {
                    $capabilities[$cap] &= $editcap;
                }
            }
        }

        $common = new stdClass();
        $common->timestamp = time();
        $common->id = $cmid;
        $common->userid = $USER->id;
        // Additional information in the locale (e.g. ".UTF-8") cannot be parsed by the browser.
        $common->lang = explode('.', get_string('locale', 'langconfig'))[0];
        $common->lang = str_replace('_', '-', $common->lang);
        $common->liveupdate = get_config('mod_kanban', 'liveupdatetime');
        $common->userboards = $kanban->userboards;
        $common->groupmode = $groupmode;
        $common->groupselector = $groupselector;
        $common->history = $kanban->history;
        $common->updatefails = 0;
        $common->usenumbers = $kanban->usenumbers;
        $common->linknumbers = $kanban->linknumbers;

        if (!$asupdate) {
            $common->template = $DB->get_field_sql(
                'SELECT id
                 FROM {kanban_board}
                 WHERE template = 1 AND kanban_instance = :instance
                 ORDER BY timemodified DESC',
                ['instance' => $kanbanboard->kanban_instance],
                IGNORE_MULTIPLE
            );
            if (empty($common->template)) {
                $common->template = 0;
            }
        }

        $kanbanusers = [];
        $kanbanuserids = [];

        $sql = 'kanban_board = :board AND timemodified > :timestamp';

        $timestampcolumns = helper::get_cached_timestamp($boardid, constants::MOD_KANBAN_COLUMN);
        $timestampcards = helper::get_cached_timestamp($boardid, constants::MOD_KANBAN_CARD);

        if ($timestamp <= $timestampcolumns) {
            $kanbancolumns = $DB->get_records_select('kanban_column', $sql, $params);
            $kanbanboard->hasaddcards = $boardmanager->has_addcards($kanbancolumns);
        } else {
            $kanbancolumns = [];
        }

        foreach ($kanbancolumns as $kanbancolumn) {
            $kanbancolumn->title = clean_param($kanbancolumn->title, PARAM_TEXT);
        }

        if (!$timestampcards || $timestamp <= $timestampcards) {
            $kanbancards = $DB->get_records_select('kanban_card', $sql, $params);
        } else {
            $kanbancards = [];
        }

        $kanbancardids = array_map(fn($card) => $card->id, $kanbancards);
        if (!empty($kanbancardids) || (!empty($kanban->userboards) && $capabilities['viewallboards'])) {
            $users = get_enrolled_users($context);
            foreach ($users as $user) {
                $kanbanusers[$user->id] = [
                    'id' => $user->id,
                    'fullname' => fullname($user),
                    'userpicture' => $OUTPUT->user_picture($user, ['link' => false]),
                ];
            }
        }
        if (!empty($kanbancardids)) {
            [$sql, $params] = $DB->get_in_or_equal($kanbancardids);
            $sql = 'kanban_card ' . $sql;
            $kanbanassigneesraw = $DB->get_records_select('kanban_assignee', $sql, $params);
            $kanbanassignees = [];
            $kanbanuserids = [];
            foreach ($kanbanassigneesraw as $assignee) {
                if (!empty($kanbanusers[$assignee->userid])) {
                    $kanbanassignees[$assignee->kanban_card][] = $assignee->userid;
                    $kanbanuserids[] = $assignee->userid;
                }
            }
            foreach ($kanbancards as $card) {
                if (empty($kanbanassignees[$card->id])) {
                    $kanbanassignees[$card->id] = [];
                }
                if (!empty($card->createdby) && !empty($kanbanusers[$card->createdby])) {
                    $card->createdbyname = $kanbanusers[$card->createdby]['fullname'];
                } else {
                    $card->createdbyname = '';
                }
                $card->title = clean_param($card->title, PARAM_TEXT);
                $card->assignees = $kanbanassignees[$card->id];
                $card->selfassigned = in_array($USER->id, $card->assignees);
                $card->canedit = $boardmanager->can_user_manage_specific_card($card->id);
                $card->hasdescription = !empty($card->description);
                $card->discussions = [];
                $card->description = file_rewrite_pluginfile_urls(
                    format_text($card->description),
                    'pluginfile.php',
                    $context->id,
                    'mod_kanban',
                    'attachments',
                    $card->id
                );
                if ($common->usenumbers && $common->linknumbers) {
                    $card->description = numberfilter::filter($card->description);
                }
                $card->attachments = helper::get_attachments($context->id, $card->id);
                $card->hasattachment = count($card->attachments) > 0;
            }
        }

        $caps = [];

        foreach ($capabilities as $k => $v) {
            $caps[] = ['id' => $k, 'value' => $v];
        }

        if ($asupdate) {
            $formatter = new updateformatter();
            $formatter->put('common', (array) $common);
            if (intval($kanbanboard->timemodified) > $timestamp) {
                $formatter->put('board', (array) $kanbanboard);
            }
            foreach ($kanbancolumns as $column) {
                $formatter->put('columns', (array) $column);
            }
            foreach ($kanbancards as $card) {
                $formatter->put('cards', (array) $card);
            }
            foreach ($kanbanuserids as $userid) {
                $formatter->put('users', (array) $kanbanusers[$userid]);
            }
            return [
                'update' => $formatter->get_formatted_updates(),
            ];
        }

        // This shouldn't be done for content updates as it would make it necessary to query all columns everytime.
        $columnids = array_map(fn($column) => $column->id, $kanbancolumns);
        $kanbanboard->sequence = helper::heal_missing_columns($kanbanboard->sequence, $columnids);

        return [
            'common' => $common,
            'board' => $kanbanboard,
            'columns' => $kanbancolumns,
            'cards' => $kanbancards,
            'users' => $kanbanusers,
            'capabilities' => $caps,
            'discussions' => [],
            'history' => [],
        ];
    }

    /**
     * Parameters for get_discussion_update().
     *
     * @return external_function_parameters
     */
    public static function get_discussion_update_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'cardid' => new external_value(PARAM_INT, 'card id', VALUE_REQUIRED),
            'timestamp' => new external_value(PARAM_INT, 'only get values modified after this timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Definition of return values of the get_discussion_update webservice function.
     *
     * @return external_single_structure
     */
    public static function get_discussion_update_returns(): external_single_structure {
        return new external_single_structure(
            [
                'update' => new external_value(PARAM_RAW, 'update JSON'),
            ]
        );
    }

    /**
     * Get card discussion from database.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param int $cardid the id of the card
     * @param int $timestamp the timestamp of the discussion present in the frontend
     * @return array The requested content
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function get_discussion_update(int $cmid, int $boardid, int $cardid, int $timestamp = 0): array {
        global $DB, $USER;
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:view', $context);

        $boardmanager = new boardmanager($cmid, $boardid);
        $kanbanboard = $boardmanager->get_board();

        helper::check_permissions_for_user_or_group($kanbanboard, $context, $cminfo, constants::MOD_KANBAN_VIEW);

        $sql = 'kanban_card = :cardid AND timecreated > :timestamp';
        $params['cardid'] = $cardid;
        $params['timestamp'] = $timestamp;

        $discussions = $DB->get_records_select('kanban_discussion_comment', $sql, $params);

        $formatter = new updateformatter();
        foreach ($discussions as $discussion) {
            $discussion->content = format_text($discussion->content, FORMAT_HTML);
            $discussion->candelete = $discussion->userid == $USER->id || has_capability('mod/kanban:manageboard', $context);
            $discussion->username = fullname(\core_user::get_user($discussion->userid));
            if (!empty($boardmanager->get_instance()->usenumbers) && !empty($boardmanager->get_instance()->linknumbers)) {
                $discussion->content = numberfilter::filter($discussion->content);
            }
            $formatter->put('discussions', (array) $discussion, false);
        }
        return [
            'update' => $formatter->get_formatted_updates(),
        ];
    }

    /**
     * Parameters for get_history_update().
     *
     * @return external_function_parameters
     */
    public static function get_history_update_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'boardid' => new external_value(PARAM_INT, 'board id', VALUE_REQUIRED),
            'cardid' => new external_value(PARAM_INT, 'card id', VALUE_REQUIRED),
            'timestamp' => new external_value(PARAM_INT, 'only get values modified after this timestamp', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Definition of return values of the get_history_update webservice function.
     *
     * @return external_single_structure
     */
    public static function get_history_update_returns(): external_single_structure {
        return new external_single_structure(
            [
                'update' => new external_value(PARAM_RAW, 'update JSON'),
            ]
        );
    }

    /**
     * Get card history from database.
     *
     * @param int $cmid the course module id of the kanban board
     * @param int $boardid the id of the kanban board
     * @param int $cardid the id of the card
     * @param int $timestamp the timestamp of the history present in the frontend
     * @return array The requested content
     * @throws coding_exception
     * @throws invalid_parameter_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     * @throws moodle_exception
     */
    public static function get_history_update(int $cmid, int $boardid, int $cardid, int $timestamp = 0): array {
        global $DB;
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid);
        $context = context_module::instance($cmid);
        self::validate_context($context);
        require_capability('mod/kanban:viewhistory', $context);

        $formatter = new updateformatter();
        $kanban = $DB->get_record('kanban', ['id' => $cminfo->instance]);
        if (!empty($kanban->history) && !empty(get_config('mod_kanban', 'enablehistory'))) {
            $kanbanboard = helper::get_cached_board($boardid);

            helper::check_permissions_for_user_or_group($kanbanboard, $context, $cminfo, constants::MOD_KANBAN_VIEW);

            $sql = 'kanban_card = :id AND timestamp > :time';
            $params = ['id' => $cardid, 'time' => $timestamp];
            $historyitems = $DB->get_records_select('kanban_history', $sql, $params);

            foreach ($historyitems as $item) {
                $item->affectedusername = get_string('unknownuser');
                $item->username = get_string('unknownuser');
                if (!empty($item->userid)) {
                    $user = \core_user::get_user($item->userid);
                    if ($user) {
                        $item->username = fullname($user);
                    }
                }
                if (!empty($item->affected_userid)) {
                    $affecteduser = \core_user::get_user($item->affected_userid);
                    if ($affecteduser) {
                        $item->affectedusername = fullname($affecteduser);
                    }
                }

                $type = constants::MOD_KANBAN_TYPES[$item->type];
                // One has to be careful, because $item->parameters theoretically could contain user input.
                $item->parameters = helper::sanitize_json_string($item->parameters);
                $item = (object) array_merge((array) $item, json_decode($item->parameters, true));
                $historyitem = [];
                $historyitem['id'] = $item->id;
                $historyitem['text'] = get_string('history_' . $type . '_' . $item->action, 'mod_kanban', $item);
                $historyitem['timestamp'] = $item->timestamp;
                $historyitem['kanban_card'] = $cardid;
                $formatter->put("history", $historyitem);
            }
        }
        return [
            'update' => $formatter->get_formatted_updates(),
        ];
    }

    /**
     * Get the timestamp of the latest entry in a db table from cache.
     *
     * @param int $type one of constants::MOD_KANBAN_BOARD, constants::MOD_KANBAN_COLUMN or constants::MOD_KANBAN_CARD
     * @param int $id Id of the board
     * @return mixed timestamp or false if none found
     */
    public static function get_cached_timestamp(int $type, int $id): mixed {
        $cache = \cache::make('mod_kanban', 'timestamp');
        return $cache->get(join('-', [$type, $id]));
    }

    /**
     * Set the timestamp of the latest entry in a db table from cache.
     *
     * @param int $type one of constants::MOD_KANBAN_BOARD, constants::MOD_KANBAN_COLUMN or constants::MOD_KANBAN_CARD
     * @param int $timestamp value
     * @param int $id Id of the board
     */
    public static function set_cached_timestamp(int $type, int $timestamp, int $id): void {
        $cache = \cache::make('mod_kanban', 'timestamp');
        $cache->set(join('-', [$type, $id]), $timestamp);
    }
}
