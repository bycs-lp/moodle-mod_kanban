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
 * Tests for Kanban board number filter
 *
 * @package    mod_kanban
 * @category   test
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_kanban\numberfilter
 */
final class numberfilter_test extends \advanced_testcase {
    /**
     * Test number filter
     */
    public function test_filter(): void {
        $text = 'This is a test #1234 and #5678';
        $expected = 'This is a test <a class="mod_kanban_card_number" data-id="1234">#1234</a>' .
            ' and <a class="mod_kanban_card_number" data-id="5678">#5678</a>';
        $this->assertEquals($expected, numberfilter::filter($text));
    }

    /**
     * Test number filter without numbers
     */
    public function test_filter_without_numbers(): void {
        $text = 'This is a test without numbers';
        $this->assertEquals($text, numberfilter::filter($text));
    }
}
