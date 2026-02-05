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
 * mod_kanban db upgrades.
 *
 * @package    mod_kanban
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define upgrade steps to be performed to upgrade the plugin from the old version to the current one.
 *
 * @param int $oldversion Version number the plugin is being upgraded from.
 */
function xmldb_kanban_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024121602) {
        // Define field repeat_enable to be added to kanban_card.
        $table = new xmldb_table('kanban_card');
        $field = new xmldb_field('repeat_enable', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'timemodified');

        // Conditionally launch add field repeat_enable.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('repeat_interval', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, '1', 'repeat_enable');

        // Conditionally launch add field repeat_interval.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'repeat_interval_type',
            XMLDB_TYPE_INTEGER,
            '11',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'repeat_interval'
        );

        // Conditionally launch add field repeat_interval_type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'repeat_newduedate',
            XMLDB_TYPE_INTEGER,
            '5',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'repeat_interval_type'
        );

        // Conditionally launch add field repeat_newduedate.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2024121602, 'kanban');
    }

    if ($oldversion < 2025020301) {
        // Define field usenumbers to be added to kanban.
        $table = new xmldb_table('kanban');
        $field = new xmldb_field('usenumbers', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'history');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field linknumbers to be added to table kanban.
        $field = new xmldb_field('linknumbers', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'usenumbers');

        // Conditionally launch add field linknumbers.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field number to be added to table kanban_card.
        $table = new xmldb_table('kanban_card');
        $field = new xmldb_field('number', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'timemodified');

        // Conditionally launch add field id.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Set numbers for all cards.
        $board = 0;
        $nextnumber = 0;
        $cards = $DB->get_recordset('kanban_card', ['number' => 0], 'kanban_board ASC, timecreated ASC');
        foreach ($cards as $card) {
            if ($card->kanban_board != $board) {
                $board = $card->kanban_board;
                $nextnumber = $DB->get_field('kanban_card', 'MAX(number)', ['kanban_board' => $board]) + 1;
            } else {
                $nextnumber++;
            }
            $DB->set_field('kanban_card', 'number', $nextnumber, ['id' => $card->id]);
        }
        $cards->close();

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2025020301, 'kanban');
    }

    if ($oldversion < 2025120801) {
        // Define field timemodified to be added to kanban.
        $table = new xmldb_table('kanban');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completioncomplete');

        // Conditionally launch add field timemodified.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Kanban savepoint reached.
        upgrade_mod_savepoint(true, 2025120801, 'kanban');
    }

    return true;
}
