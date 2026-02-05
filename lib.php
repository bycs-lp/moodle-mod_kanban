<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Library for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_kanban\boardmanager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once('HTML/QuickForm/input.php');

MoodleQuickForm::registerElementType('color', $CFG->dirroot . '/mod/kanban/classes/form/color.php', 'MoodleQuickForm_color');

/**
 * Adds a new kanban instance
 *
 * @param stdClass $data kanban record
 * @return int
 */
function kanban_add_instance($data): int {
    global $DB;
    $kanbanid = $DB->insert_record("kanban", $data);
    $boardmanager = new boardmanager();
    $boardmanager->load_instance($kanbanid, true);
    $boardmanager->create_board();
    return $kanbanid;
}

/**
 * Updates a kanban instance
 *
 * @param stdClass $data kanban record
 * @return int
 */
function kanban_update_instance($data): int {
    global $DB;
    $data->id = $data->instance;
    return $DB->update_record("kanban", $data);
}

/**
 * Deletes a kanban instance, all boards and all associated data (e.g. files)
 *
 * @param integer $id kanban record
 * @return bool
 */
function kanban_delete_instance($id): bool {
    global $DB;
    $boards = $DB->get_fieldset_sql('SELECT id FROM {kanban_board} WHERE kanban_instance = :id', ['id' => $id]);

    foreach ($boards as $board) {
        $boardmanager = new boardmanager();
        $boardmanager->load_board($board);
        $boardmanager->delete_board($board);
    }

    return $DB->delete_records('kanban', ['id' => $id]);
}

/**
 * Returns whether a feature is supported by this module.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, false if not, null if doesn't know or string for the module purpose.
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @uses FEATURE_IDNUMBER
 * @uses FEATURE_GROUPS
 */
function kanban_supports($feature) {
    switch ($feature) {
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COLLABORATION;
        default:
            return null;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable | null
 * @throws dml_exception
 */
function kanban_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG, $USER;
    require_once($CFG->libdir . '/externallib.php');
    $boardmanager = new boardmanager();

    if ($itemtype == 'card') {
        $card = $boardmanager->get_card($itemid);
        $boardmanager->load_board($card->kanban_board);
    }
    if ($itemtype == 'column') {
        $column = $boardmanager->get_column($itemid);
        $boardmanager->load_board($column->kanban_board);
    }

    $context = context_module::instance($boardmanager->get_cminfo()->id);
    external_api::validate_context($context);

    if ($itemtype == 'card') {
        if (!$boardmanager->can_user_manage_specific_card($card->id)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanban');
        }
    }

    if ($itemtype == 'column') {
        require_capability('mod/kanban:managecolumns', $context);
    }

    \mod_kanban\helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $boardmanager->get_cminfo());

    if ($itemtype == 'card') {
        $boardmanager->update_card($itemid, ['title' => $newvalue]);
    }

    if ($itemtype == 'column') {
        $boardmanager->update_column($itemid, ['title' => $newvalue]);
    }

    return new \core\output\inplace_editable('mod_kanban', $itemtype, $itemid, true, s($newvalue), $newvalue, null, '');
}

/**
 * Delivers the attachment files for cards
 *
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function kanban_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []): ?bool {
    global $DB;
    require_course_login($course, true, $cm);

    // In $args[0] is the card id.

    $cardid = intval($args[0]);
    $boardid = $DB->get_field('kanban_card', 'kanban_board', ['id' => $cardid], MUST_EXIST);

    // Check, whether the user is allowed to access this board.

    require_capability('mod/kanban:view', $context);

    $board = mod_kanban\helper::get_cached_board($boardid);

    mod_kanban\helper::check_permissions_for_user_or_group($board, $context, cm_info::create($cm));

    $fullpath = "/$context->id/mod_kanban/$filearea/" . implode('/', $args);

    $fs = get_file_storage();
    if (!($file = $fs->get_file_by_hash(sha1($fullpath))) || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, false, $options);
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the kanban activity.
 *
 * @param object $mform form passed by reference
 */
function kanban_reset_course_form_definition(&$mform): void {
    $mform->addElement('header', 'kanbanactivityheader', get_string('modulenameplural', 'mod_kanban'));
    $mform->addElement('advcheckbox', 'reset_kanban_personal', get_string('reset_personal', 'mod_kanban'));
    $mform->addElement('advcheckbox', 'reset_kanban_group', get_string('reset_group', 'mod_kanban'));
    $mform->addElement('advcheckbox', 'reset_kanban', get_string('reset_kanban', 'mod_kanban'));
}

/**
 * Course reset form defaults.
 *
 * @param stdClass $course the course object
 * @return array
 */
function kanban_reset_course_form_defaults(stdClass $course): array {
    return [
        'reset_kanban_personal' => 1,
        'reset_kanban_group' => 1,
        'reset_kanban' => 1,
    ];
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function kanban_reset_userdata($data) {
    global $DB;
    $status = [];
    $kanbans = $DB->get_records('kanban', ['course' => $data->courseid]);
    $boards = [];
    foreach ($kanbans as $kanban) {
        if (!empty($data->reset_kanban_personal)) {
            $personalboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanban_board} WHERE kanban_instance = :id AND userid > 0',
                ['id' => $kanban->id]
            );
            if ($personalboards) {
                $boards = array_merge($boards, $personalboards);
            }
        }
        if (!empty($data->reset_kanban_group)) {
            $groupboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanban_board} WHERE kanban_instance = :id AND groupid > 0',
                ['id' => $kanban->id]
            );
            if ($groupboards) {
                $boards = array_merge($boards, $groupboards);
            }
        }
        if (!empty($data->reset_kanban)) {
            $courseboards = $DB->get_fieldset_sql(
                'SELECT id FROM {kanban_board} WHERE kanban_instance = :id AND template = 0',
                ['id' => $kanban->id]
            );
            if ($courseboards) {
                $boards = array_merge($boards, $courseboards);
            }
        }
    }
    $boards = array_unique($boards);
    foreach ($boards as $board) {
        $boardmanager = new boardmanager();
        $boardmanager->load_board($board);
        $boardmanager->delete_board($board);
        $status[] = [
            'component' => get_string('modulenameplural', 'mod_kanban'),
            'item' => get_string('reset_personal', 'mod_kanban'),
            'error' => false,
        ];
    }
    return $status;
}

/**
 * Add custom completion.
 *
 * @param stdClass $cm coursemodule record.
 * @return cached_cm_info
 */
function kanban_get_coursemodule_info(stdClass $cm): cached_cm_info {
    global $DB;

    $kanban = $DB->get_record('kanban', ['id' => $cm->instance]);

    $result = new cached_cm_info();
    if ($kanban) {
        $result->name = $kanban->name;

        if ($cm->showdescription) {
            $result->content = format_module_intro('kanban', $kanban, $cm->id, false);
        }

        if ($cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $result->customdata['customcompletionrules']['completioncreate'] = $kanban->completioncreate;
            $result->customdata['customcompletionrules']['completioncomplete'] = $kanban->completioncomplete;
        }
    }
    return $result;
}
