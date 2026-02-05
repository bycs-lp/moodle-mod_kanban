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
 * Class numberfilter
 *
 * @package    mod_kanban
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class numberfilter {
    /**
     * Adds custom tags to card numbers in the text. This is used to allow handling a click on
     * the number by JavaScript (to open the detail modal of the card).
     *
     * @param string $text
     * @return string
     */
    public static function filter(string $text): string {
        if (stripos($text, '#') === false) {
            return $text;
        }

        $pattern = '/#(\d+)/';
        $text = preg_replace_callback($pattern, function ($matches) {
            $number = (int)$matches[1];
            return '<a class="mod_kanban_card_number" data-id="' . $number . '">#' . $number . '</a>';
        }, $text);
        return $text;
    }
}
