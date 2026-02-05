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
 * Restore steps for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_kanban_activity_structure_step extends restore_activity_structure_step {
    /**
     * List of elements that can be restored
     *
     * @return array
     * @throws base_step_exception
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('kanban', '/activity/kanban');
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('board', '/activity/kanban/boards/kanban_board');
        $paths[] = new restore_path_element('column', '/activity/kanban/boards/kanban_board/columns/kanban_column');
        $paths[] = new restore_path_element('card', '/activity/kanban/boards/kanban_board/columns/kanban_column/cards/kanban_card');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'assignee',
                '/activity/kanban/boards/kanban_board/columns/kanban_column/cards/kanban_card/assignees/kanban_assignee'
            );
            $paths[] = new restore_path_element(
                'discussion_comment',
                '/activity/kanban/boards/kanban_board/columns/kanban_column/cards/kanban_card/discussions/kanban_discussion_comment'
            );
            $paths[] = new restore_path_element('historyitem', '/activity/kanban/boards/kanban_board/historyitems/kanban_history');
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore a kanban record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_kanban($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $newid = $DB->insert_record('kanban', $data);
        $this->set_mapping('kanban_id', $oldid, $newid);
        $this->apply_activity_instance($newid);
    }

    /**
     * Restore a board record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_board($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->kanban_instance = $this->get_mappingid('kanban_id', $data->kanban_instance);

        $newid = $DB->insert_record('kanban_board', $data);
        $this->set_mapping('kanban_board_id', $oldid, $newid);
    }

    /**
     * Restore a column record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_column($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->kanban_board = $this->get_mappingid('kanban_board_id', $data->kanban_board);

        $newid = $DB->insert_record('kanban_column', $data);
        $this->set_mapping('kanban_column_id', $oldid, $newid);
    }

    /**
     * Restore a card record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_card($data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userinfo = $this->get_setting_value('userinfo');
        if (!$userinfo) {
            $data->discussion = 0;
        }

        $data->kanban_column = $this->get_mappingid('kanban_column_id', $data->kanban_column);
        $data->kanban_board = $this->get_mappingid('kanban_board_id', $data->kanban_board);
        $data->originalid = $this->get_mappingid('kanban_card_id', $data->originalid);
        $data->createdby = $this->get_mappingid('user', $data->createdby);

        if (empty($data->number)) {
            $data->number = $DB->get_field('kanban_card', 'MAX(number)', ['kanban_board' => $data->kanban_board]) + 1;
        }

        $newid = $DB->insert_record('kanban_card', $data);
        $this->set_mapping('kanban_card_id', $oldid, $newid, true);
        $this->add_related_files('mod_kanban', 'attachments', 'kanban_card_id', null, $oldid);
    }

    /**
     * Restore an assignes record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_assignee($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanban_card = $this->get_mappingid('kanban_card_id', $data->kanban_card);

        $DB->insert_record('kanban_assignee', $data);
    }

    /**
     * Restore an historyitem record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_historyitem($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanban_card = $this->get_mappingid('kanban_card_id', $data->kanban_card);
        $data->kanban_column = $this->get_mappingid('kanban_column_id', $data->kanban_column);
        $data->kanban_board = $this->get_mappingid('kanban_board_id', $data->kanban_board);
        $data->affected_userid = $this->get_mappingid('user', $data->affected_userid);

        $DB->insert_record('kanban_history', $data);
    }

    /**
     * Restore an discussion_comment record.
     *
     * @param array|object $data
     * @throws base_step_exception
     * @throws dml_exception
     * @throws restore_step_exception
     */
    protected function process_discussion_comment($data): void {
        global $DB;

        $data = (object) $data;

        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->kanban_card = $this->get_mappingid('kanban_card_id', $data->kanban_card);

        $DB->insert_record('kanban_discussion_comment', $data);
    }

    /**
     * Extra actions to take once restore is complete.
     */
    protected function after_execute(): void {
        global $DB;
        $this->add_related_files('mod_kanban', 'intro', null);

        $kanbanboards = $DB->get_records('kanban_board', ['kanban_instance' => $this->task->get_activityid()]);

        foreach ($kanbanboards as $board) {
            if ($board->sequence == '') {
                continue;
            }
            $seq = explode(',', $board->sequence);
            foreach ($seq as $key => $columnid) {
                $seq[$key] = $this->get_mappingid('kanban_column_id', $columnid);
            }
            $DB->update_record('kanban_board', ['id' => $board->id, 'sequence' => join(',', $seq)]);
            mod_kanban\helper::update_cached_board($board->id);

            $kanbancolumns = $DB->get_records('kanban_column', ['kanban_board' => $board->id]);

            foreach ($kanbancolumns as $column) {
                if ($column->sequence == '') {
                    continue;
                }
                $seqcard = explode(',', $column->sequence);
                foreach ($seqcard as $cardkey => $cardid) {
                    $seqcard[$cardkey] = $this->get_mappingid('kanban_card_id', $cardid);
                }
                $DB->update_record('kanban_column', ['id' => $column->id, 'sequence' => join(',', $seqcard)]);
            }
        }
    }
}
