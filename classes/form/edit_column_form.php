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

namespace mod_kanban\form;

use context;
use context_module;
use core_form\dynamic_form;
use mod_kanban\boardmanager;
use mod_kanban\helper;
use moodle_url;

/**
 * From for editing a column.
 *
 * @package    mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_column_form extends dynamic_form {
    /**
     * Define the form
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'boardid');
        $mform->setType('boardid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('columntitle', 'kanban'), ['size' => '50']);
        $mform->setType('title', PARAM_TEXT);

        $userid = $this->optional_param('userid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);

        $mform->addElement('advcheckbox', 'autoclose', get_string('autoclose', 'kanban'));
        $mform->setType('autoclose', PARAM_BOOL);

        $mform->addElement('advcheckbox', 'autohide', get_string('autohide', 'kanban'));
        $mform->setType('autohide', PARAM_BOOL);

        $wiparray = [];
        $wiparray[] = $mform->createElement('advcheckbox', 'wiplimitenable', get_string('wiplimitenable', 'kanban'));
        $wiparray[] = $mform->createElement('text', 'wiplimit', get_string('wiplimit', 'kanban'), ['size' => '5']);
        $mform->addGroup($wiparray, 'wipgroup', '', '', false);

        $mform->setType('wiplimit', PARAM_INT);
        $mform->setType('wiplimitenable', PARAM_BOOL);

        $mform->disabledIf('wiplimit', 'wiplimitenable', 'notchecked');
    }

    /**
     * Returns context where this form is used
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        return context_module::instance($cmid);
    }

    /**
     * Checks if current user has access to this card, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        global $COURSE;
        $context = $this->get_context_for_dynamic_submission();
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $boardid = $this->optional_param('boardid', null, PARAM_INT);
        $kanbanboard = helper::get_cached_board($boardid);
        $id = $this->optional_param('id', null, PARAM_INT);
        require_capability('mod/kanban:managecolumns', $context);
        $modinfo = get_fast_modinfo($COURSE);
        $cm = $modinfo->get_cm($cmid);
        \mod_kanban\helper::check_permissions_for_user_or_group($kanbanboard, $context, $cm);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array Returns whether a new template was created.
     */
    public function process_dynamic_submission(): array {
        global $COURSE;
        $formdata = $this->get_data();
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $boardid = $this->optional_param('boardid', null, PARAM_INT);
        $modinfo = get_fast_modinfo($COURSE);
        $cminfo = $modinfo->get_cm($cmid);
        $context = $this->get_context_for_dynamic_submission();

        $boardmanager = new boardmanager($cmid, $boardid);

        helper::check_permissions_for_user_or_group($boardmanager->get_board(), $context, $cminfo);

        $boardmanager->update_column($formdata->id, (array) $formdata);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $id = $this->optional_param('id', null, PARAM_INT);
        $column = $DB->get_record('kanban_column', ['id' => $id]);
        $column->cmid = $this->optional_param('cmid', null, PARAM_INT);
        $column->title = html_entity_decode($column->title, ENT_COMPAT, 'UTF-8');
        $column->boardid = $column->kanban_board;
        $options = json_decode($column->options);
        $column->autoclose = $options->autoclose;
        $column->autohide = $options->autohide;
        $column->wiplimitenable = !empty($options->wiplimit);
        $column->wiplimit = (empty($options->wiplimit) ? 0 : $options->wiplimit);
        $this->set_data($column);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        $params = [
            'id' => $this->optional_param('id', null, PARAM_INT),
            'boardid' => $this->optional_param('boardid', null, PARAM_INT),
            'cmid' => $this->optional_param('cmid', null, PARAM_INT),
        ];
        return new moodle_url('/mod/kanban/view.php', $params);
    }

    /**
     * Validate the form data
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['wiplimitenable']) && $data['wiplimit'] <= 0) {
            $errors['wipgroup'] = get_string('wiplimitgreaterzero', 'kanban');
        }

        return $errors;
    }
}
