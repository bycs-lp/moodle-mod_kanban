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
use mod_kanban\constants;
use moodle_url;

/**
 * From for editing a card.
 *
 * @package    mod_kanban
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_card_form extends dynamic_form {
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

        $mform->addElement('text', 'title', get_string('cardtitle', 'kanban'), ['size' => '50']);
        $mform->setType('title', PARAM_TEXT);

        $userid = $this->optional_param('userid', 0, PARAM_INT);
        $groupid = $this->optional_param('groupid', 0, PARAM_INT);

        $context = $this->get_context_for_dynamic_submission();
        if (has_capability('mod/kanban:assignothers', $context)) {
            $userlist = get_enrolled_users($context, '', $groupid);

            $users = [];
            foreach ($userlist as $user) {
                if (!empty($userid) && $userid != $user->id) {
                    continue;
                }
                $users[$user->id] = fullname($user);
            }
            $mform->addElement(
                'autocomplete',
                'assignees',
                get_string('assignees', 'mod_kanban'),
                $users,
                ['multiple' => true]
            );
        }

        $mform->addElement('editor', 'description_editor', get_string('description'), null, ['maxfiles' => -1]);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('date_time_selector', 'duedate', get_string('duedate', 'kanban'), ['optional' => true]);

        $mform->addElement('date_time_selector', 'reminderdate', get_string('reminderdate', 'kanban'), ['optional' => true]);

        $repeatgroup = [];
        $repeatgroup[] = $mform->createElement('advcheckbox', 'repeat_enable', get_string('enable'));
        $repeatgroup[] = $mform->createElement('text', 'repeat_interval', get_string('repeat_interval', 'kanban'), ['size' => 3]);
        $repeatgroup[] = $mform->createElement('select', 'repeat_interval_type', get_string('repeat_interval_type', 'kanban'), [
            constants::MOD_KANBAN_REPEAT_HOURS => get_string('hours'),
            constants::MOD_KANBAN_REPEAT_DAYS => get_string('days'),
            constants::MOD_KANBAN_REPEAT_WEEKS => get_string('weeks'),
            constants::MOD_KANBAN_REPEAT_MONTHS => get_string('months'),
            constants::MOD_KANBAN_REPEAT_YEARS => get_string('years'),
        ]);
        $repeatgroup[] = $mform->createElement('select', 'repeat_newduedate', get_string('repeat_newduedate', 'kanban'), [
            constants::MOD_KANBAN_REPEAT_NONEWDUEDATE => get_string('nonewduedate', 'kanban'),
            constants::MOD_KANBAN_REPEAT_NEWDUEDATE_AFTERDUE => get_string('afterdue', 'kanban'),
            constants::MOD_KANBAN_REPEAT_NEWDUEDATE_AFTERCOMPLETION => get_string('aftercompletion', 'kanban'),
        ]);

        $mform->addElement('group', 'repeatgroup', get_string('repeat', 'kanban'), $repeatgroup, ' ', false);

        $mform->setType('repeat_interval', PARAM_INT);
        $mform->setType('repeat_interval_type', PARAM_INT);
        $mform->setDefault('repeat_enable', 0);
        $mform->setDefault('repeat_interval', 1);
        $mform->disabledIf('repeatgroup', 'repeat_enable');
        $mform->disabledIf('repeat_interval', 'repeat_newduedate', 'eq', constants::MOD_KANBAN_REPEAT_NONEWDUEDATE);
        $mform->disabledIf('repeat_interval_type', 'repeat_newduedate', 'eq', constants::MOD_KANBAN_REPEAT_NONEWDUEDATE);
        $mform->addHelpButton('repeatgroup', 'repeat', 'kanban');

        $mform->addElement('filemanager', 'attachments', get_string('attachments', 'kanban'));

        $mform->addElement('color', 'color', get_string('color', 'mod_kanban'), ['size' => 5]);
        $mform->setType('color', PARAM_TEXT);
        $mform->setDefault('color', '#ffffff');
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
        $boardmanager = new boardmanager($cmid, $boardid);

        if (!$boardmanager->can_user_manage_specific_card($id)) {
            throw new moodle_exception('editing_this_card_is_not_allowed', 'mod_kanban');
        }

        $modinfo = get_fast_modinfo($COURSE);
        $cm = $modinfo->get_cm($cmid);
        helper::check_permissions_for_user_or_group($kanbanboard, $context, $cm);
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array Returns whether a new template was created.
     */
    public function process_dynamic_submission(): array {
        $cmid = $this->optional_param('cmid', null, PARAM_INT);
        $boardid = $this->optional_param('boardid', null, PARAM_INT);
        $context = $this->get_context_for_dynamic_submission();
        $formdata = $this->get_data();
        $formdata->options = json_encode(['background' => $formdata->color]);

        if (!has_capability('mod/kanban:assignothers', $context)) {
            unset($formdata->assignees);
        }

        $formdata->description = $formdata->description_editor['text'];
        $formdata->descriptionformat = $formdata->description_editor['format'];

        $formdata->description = file_save_draft_area_files(
            $formdata->attachments,
            $context->id,
            'mod_kanban',
            'attachments',
            $formdata->id,
            [],
            $formdata->description
        );

        $boardmanager = new boardmanager($cmid, $boardid);

        $boardmanager->update_card($formdata->id, (array) $formdata);

        return [
            'update' => $boardmanager->get_formatted_updates(),
        ];
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;
        $context = $this->get_context_for_dynamic_submission();
        $id = $this->optional_param('id', null, PARAM_INT);
        $card = $DB->get_record('kanban_card', ['id' => $id]);
        $options = json_decode($card->options);
        $card->title = html_entity_decode($card->title, ENT_COMPAT, 'UTF-8');
        $card->cmid = $this->optional_param('cmid', null, PARAM_INT);
        $card->boardid = $card->kanban_board;
        $card->assignees = $DB->get_fieldset_select('kanban_assignee', 'userid', 'kanban_card = :cardid', ['cardid' => $id]);
        $card->color = $options->background;
        $draftitemid = file_get_submitted_draft_itemid('attachments');
        $card->description = file_prepare_draft_area(
            $draftitemid,
            $context->id,
            'mod_kanban',
            'attachments',
            $card->id,
            [],
            $card->description
        );
        $card->description_editor['text'] = $card->description;
        $card->description_editor['format'] = $card->descriptionformat;
        $card->description_editor['itemid'] = $draftitemid;
        $card->attachments = $draftitemid;
        $this->set_data($card);
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
}
