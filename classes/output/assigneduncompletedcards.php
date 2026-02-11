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

namespace mod_kanban\output;

use core\output\renderable;
use core\output\templatable;
use core\output\renderer_base;

/**
 * Class for displaying uncompleted cards in the activity overview.
 *
 * @package    mod_kanban
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assigneduncompletedcards implements renderable, templatable {
    /** @var int The course module id. */
    protected int $cmid;

    /** @var array The uncompleted assigned cards. */
    protected array $cards;

    /**
     * Constructor.
     *
     * @param int $cmid The course module id.
     * @param array $cards The uncompleted assigned cards.
     */
    public function __construct(int $cmid, array $cards) {
        $this->cmid = $cmid;
        $this->cards = $cards;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output The renderer.
     * @return \stdClass|array
     */
    public function export_for_template(renderer_base $output) {
        $data = new \stdClass();
        $data->cmid = $this->cmid;
        $data->cards = [];
        foreach ($this->cards as $card) {
            $data->cards[] = [
                'title' => $card->title,
                'url' => $card->url,
                'id' => $card->id,
            ];
        }
        return $data;
    }
}
