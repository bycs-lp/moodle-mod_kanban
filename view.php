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
 * View a kanban instance
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('lib.php');

use mod_kanban\boardmanager;
use mod_kanban\constants;
use mod_kanban\helper;

$id = required_param('id', PARAM_INT);
$boardid = optional_param('boardid', 0, PARAM_INT);
$userid = optional_param('user', 0, PARAM_INT);
$cardid = optional_param('cardid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'kanban');

require_course_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/kanban:view', $context);

$kanban = $DB->get_record('kanban', ['id' => $cm->instance], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/kanban/view.php', ['id' => $id]));
$PAGE->set_title(get_string('pluginname', 'mod_kanban') . ' ' . $kanban->name);
$PAGE->set_heading($kanban->name);

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

echo $OUTPUT->header();

$groupselector = '';
$groupid = 0;

$groupmode = groups_get_activity_groupmode($cm, $course);

if (!empty($groupmode)) {
    $groupid = groups_get_activity_group($cm, true);
}

if (
    $kanban->userboards == constants::MOD_KANBAN_USERBOARDS_ONLY &&
    empty($groupid) &&
    empty($userid)
) {
    $userid = $USER->id;
}

if (!empty($userid)) {
    $groupid = 0;
}

if (!empty($cardid)) {
    $boardidforcard = $DB->get_field('kanban_card', 'kanban_board', ['id' => $cardid], MUST_EXIST);
    if (!empty($boardid) && $boardid != $boardidforcard) {
        throw new moodle_exception('invalidcardboardcombination', 'mod_kanban');
    }
    $boardid = $boardidforcard;
}

if (empty($boardid)) {
    $board = $DB->get_record(
        'kanban_board',
        ['kanban_instance' => $kanban->id, 'userid' => $userid, 'groupid' => $groupid, 'template' => 0],
        '*'
    );
    if (!$board) {
        $boardmanager = new boardmanager($cm->id);
        if (empty($userid)) {
            if (empty($groupid)) {
                $boardid = $boardmanager->create_board();
            } else {
                $boardid = $boardmanager->create_group_board($groupid);
            }
        } else {
            $boardid = $boardmanager->create_user_board($userid);
        }
        $boardmanager->load_board($boardid);
        $board = $boardmanager->get_board();
    } else {
        $boardid = $board->id;
    }
} else {
    $board = $DB->get_record('kanban_board', ['id' => $boardid, 'kanban_instance' => $kanban->id]);
    helper::check_permissions_for_user_or_group($board, $context, $cm, constants::MOD_KANBAN_VIEW);
}

echo $OUTPUT->render_from_template(
    'mod_kanban/container',
    [
        'cmid' => $cm->id,
        'id' => $boardid,
        'cardid' => $cardid,
    ]
);

$PAGE->requires->js_call_amd('mod_kanban/main', 'init', ['mod_kanban_render_container-' . $cm->id, $cm->id, $boardid]);

echo $OUTPUT->footer();
