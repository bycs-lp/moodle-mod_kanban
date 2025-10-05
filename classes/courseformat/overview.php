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

namespace mod_kanban\courseformat;

use core_courseformat\activityoverviewbase;
use core_courseformat\local\overview\overviewitem;
use mod_kanban\boardmanager;

/**
 * Kanban board overview integration (for Moodle 5.0 and later).
 *
 * @package    mod_kanban
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class overview extends activityoverviewbase {
    /**
     * Constructor.
     *
     * @param \cm_info $cm the course module instance.
     * @param \core\output\renderer_helper $rendererhelper the renderer helper.
     */
    public function __construct(
        \cm_info $cm,
        /** @var \core\output\renderer_helper $rendererhelper the renderer helper */
        protected readonly \core\output\renderer_helper $rendererhelper,
    ) {
        parent::__construct($cm);
    }

    #[\Override]
    public function get_extra_overview_items(): array {
        return ['uncompletedcards' => $this->get_extra_uncompletedcards_overview()];
    }

    /**
     * Get the extra overview item showing the number of uncompleted assigned cards.
     *
     * @return overviewitem|null
     */
    private function get_extra_uncompletedcards_overview(): ?overviewitem {
        global $USER;
        $boardmanager = new boardmanager($this->cm->id);
        $cards = $boardmanager->get_uncompleted_assigned_cards($USER->id);
        if (empty($cards)) {
            return null;
        }
        foreach ($cards as $card) {
            $card->url = new \moodle_url('/mod/kanban/view.php', ['id' => $this->cm->id, 'cardid' => $card->id]);
        }
        return new overviewitem(
            name: get_string('uncompletedassignedcards', 'mod_kanban'),
            value: count($cards),
            content: new \mod_kanban\output\assigneduncompletedcards($this->cm->id, $cards),
        );
    }
}
