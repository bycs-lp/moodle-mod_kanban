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
 * Board exporter.
 *
 * @package    mod_kanban
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

$url = new moodle_url('/mod/kanban/export.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());

$PAGE->set_heading($SITE->fullname);

$boardid = required_param('boardid', PARAM_INT);

$boardmanager = new \mod_kanban\boardmanager(0, $boardid);
$board = $boardmanager->get_board();
$cmid = $boardmanager->get_cmid();
$context = context_module::instance($cmid);
$course = $boardmanager->get_cminfo()->get_course();

require_capability('mod/kanban:manageboard', $context);

\mod_kanban\helper::check_permissions_for_user_or_group(
    $board,
    $context,
    $boardmanager->get_cminfo(),
    \mod_kanban\constants::MOD_KANBAN_VIEW
);

$exporter = new \mod_kanban\exporter($boardmanager);
$exporter->export('excel');
