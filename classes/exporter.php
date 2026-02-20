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

namespace mod_kanban;

/**
 * Kanban exporter.
 *
 * @package    mod_kanban
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class exporter {
    /**
     * The board manager instance.
     *
     * @var boardmanager
     */
    protected $boardmanager;

    /**
     * Constructor.
     *
     * @param boardmanager $boardmanager The board manager instance.
     */
    public function __construct(boardmanager $boardmanager) {
        $this->boardmanager = $boardmanager;
    }

    /**
     * Exports the board data.
     *
     * @param string $dataformat The data format to export to.
     * @return void
     */
    public function export(string $dataformat): void {
        \core\dataformat::download_data(
            'kanban' . $this->boardmanager->get_board()->id,
            $dataformat,
            $this->boardmanager->get_columntitles(),
            $this->boardmanager->get_cardtitles()
        );
    }
}
