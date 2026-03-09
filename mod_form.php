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

use mod_kanban\constants;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Editing form for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_kanban_mod_form extends moodleform_mod {
    /**
     * Defines the editing form for mod_kanban
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'generalhdr', get_string('general'));

        $mform->addElement('text', 'name', get_string('name', 'kanban'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addHelpButton('name', 'name', 'kanban');

        $this->standard_intro_elements(get_string('description'));

        $userboards = [
            constants::MOD_KANBAN_NOUSERBOARDS => get_string('nouserboards', 'kanban'),
            constants::MOD_KANBAN_USERBOARDS_ENABLED => get_string('userboardsenabled', 'kanban'),
            constants::MOD_KANBAN_USERBOARDS_ONLY => get_string('userboardsonly', 'kanban'),
        ];
        $mform->addElement('select', 'userboards', get_string('userboards', 'kanban'), $userboards);
        $mform->addHelpButton('userboards', 'userboards', 'mod_kanban');

        if (!empty(get_config('mod_kanban', 'enablehistory'))) {
            $mform->addElement('advcheckbox', 'history', get_string('enablehistory', 'mod_kanban'));
            $mform->addHelpButton('history', 'enablehistory', 'mod_kanban');
        }

        $mform->addElement('advcheckbox', 'usenumbers', get_string('usenumbers', 'mod_kanban'));
        $mform->addHelpButton('usenumbers', 'usenumbers', 'mod_kanban');

        $mform->addElement('advcheckbox', 'linknumbers', get_string('linknumbers', 'mod_kanban'));
        $mform->addHelpButton('linknumbers', 'linknumbers', 'mod_kanban');
        $mform->hideIf('linknumbers', 'usenumbers', 'notchecked');
        $mform->setDefault('linknumbers', 1);
        $mform->setType('linknumbers', PARAM_INT);

        $mform->addElement('advcheckbox', 'showauthors', get_string('showauthors', 'mod_kanban'));
        $mform->addHelpButton('showauthors', 'showauthors', 'mod_kanban');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons(true, null, null);
    }

    /**
     * Returns whether the custom completion rules are enabled.
     *
     * @param array $data form data
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return (
            !empty($data['completioncreate' . $this->get_suffix()]) ||
            !empty($data['completioncomplete' . $this->get_suffix()])
        );
    }

    /**
     * Adds the custom completion rules for mod_kanban
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $completioncreate = 'completioncreate' . $this->get_suffix();
        $completioncomplete = 'completioncomplete' . $this->get_suffix();

        $mform->addElement(
            'text',
            $completioncreate,
            get_string('completioncreate', 'kanban'),
            ['size' => 3]
        );
        $mform->setType($completioncreate, PARAM_INT);

        $mform->addElement(
            'text',
            $completioncomplete,
            get_string('completioncomplete', 'kanban'),
            ['size' => 3]
        );
        $mform->setType($completioncomplete, PARAM_INT);

        return ([$completioncreate, $completioncomplete]);
    }

    /**
     * Get the suffix to be added to the completion elements when creating them.
     * This acts as a spare for compatibility with Moodle 4.1 and 4.2.
     *
     * @return string The suffix
     */
    public function get_suffix(): string {
        if (method_exists(get_parent_class($this), 'get_suffix')) {
            return parent::get_suffix();
        }
        return '';
    }
}
