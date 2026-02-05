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

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
require_course_login($course);

$coursecontext = context_course::instance($course->id);

$PAGE->set_url('/mod/kanban/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($coursecontext);

echo $OUTPUT->header();

$modulenameplural = get_string('modulenameplural', 'mod_kanban');
echo $OUTPUT->heading($modulenameplural);

$kanbans = get_all_instances_in_course('kanban', $course);
$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head = [get_string('sectionname', 'format_' . $course->format), get_string('name')];
    $table->align = ['left', 'left'];
} else {
    $table->head  = [get_string('name')];
    $table->align = ['left'];
}

$kanbanfound = false;

foreach ($kanbans as $kanban) {
    $context = context_module::instance($kanban->coursemodule, IGNORE_MISSING);
    if (!$context || !has_capability('mod/kanban:view', $context)) {
        continue;
    }

    $kanbanfound = true;
    $linkcss = null;

    if (!$kanban->visible) {
        $linkcss = ['class' => 'dimmed'];
    }

    $link = html_writer::link(new moodle_url('/mod/kanban/view.php', ['id' => $kanban->coursemodule]), $kanban->name, $linkcss);

    if ($usesections) {
        $table->data[] = [get_section_name($course, $kanban->section), $link];
    } else {
        $table->data[] = [$link];
    }
}

if (!$kanbanfound) {
    notice(get_string('nokanbaninstances', 'mod_kanban'), new moodle_url('/course/view.php', ['id' => $course->id]));
} else {
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
