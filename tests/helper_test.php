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
 * Unit tests for helper class.
 *
 * @package    mod_kanban
 * @category   test
 * @copyright  2023-2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass     \mod_kanban\helper
 */
final class helper_test extends \advanced_testcase {
    /**
     * Tests the sequence_add_after method.
     *
     * @covers ::sequence_add_after
     * @return void
     */
    public function test_sequence_add_after(): void {
        // Test adding a new element after an existing element.
        $this->assertEquals('1,2,3', helper::sequence_add_after('1,2', 2, 3));
        $this->assertEquals('1,3,2', helper::sequence_add_after('1,2', 1, 3));
        // Test adding a new element at the beginning.
        $this->assertEquals('3,1,2', helper::sequence_add_after('1,2', 0, 3));
        // Test adding a new element after a non existing element (should be added at the end).
        $this->assertEquals('1,2,3', helper::sequence_add_after('1,2', 4, 3));
        // Test adding a new element to an empty sequence.
        $this->assertEquals('3', helper::sequence_add_after('', 0, 3));
    }

    /**
     * Tests the sequence_remove method.
     *
     * @covers ::sequence_remove
     *
     * @return void
     */
    public function test_sequence_remove(): void {
        // Test removing an element.
        $this->assertEquals('1,3', helper::sequence_remove('1,2,3', 2));
        // Test removing the first element.
        $this->assertEquals('2,3', helper::sequence_remove('1,2,3', 1));
        // Test removing the last element.
        $this->assertEquals('1,2', helper::sequence_remove('1,2,3', 3));
        // Test removing a non existing element.
        $this->assertEquals('1,2,3', helper::sequence_remove('1,2,3', 4));
        // Test removing from an empty sequence.
        $this->assertEquals('', helper::sequence_remove('', 1));
    }

    /**
     * Tests the sequence_move_after method.
     *
     * @covers ::sequence_move_after
     *
     * @return void
     */
    public function test_sequence_move_after(): void {
        // Test moving the last element after another element.
        $this->assertEquals('1,3,2', helper::sequence_move_after('1,2,3', 1, 3));
        // Test moving an element at the beginning.
        $this->assertEquals('3,1,2', helper::sequence_move_after('1,2,3', 0, 3));
        // Test moving an element after itself (should not change the sequence).
        $this->assertEquals('1,2,3', helper::sequence_move_after('1,2,3', 2, 2));
        // Test moving an element after a non existing element (should move it at the end).
        $this->assertEquals('1,3,2', helper::sequence_move_after('1,2,3', 4, 2));
        // Test moving an element in an empty sequence (should insert it at the end).
        $this->assertEquals('4', helper::sequence_move_after('', 1, 4));
    }

    /**
     * Tests the sequence_replace method.
     *
     * @covers ::sequence_replace
     *
     * @return void
     */
    public function test_sequence_replace(): void {
        // Test replacing elements.
        $this->assertEquals('4,5,6', helper::sequence_replace('1,2,3', [1 => 4, 2 => 5, 3 => 6]));
        // Test replacing elements with objects (having an id attribute).
        $this->assertEquals(
            '4,5,6',
            helper::sequence_replace('1,2,3', [1 => (object)['id' => 4], 2 => (object)['id' => 5], 3 => (object)['id' => 6]])
        );
        // Test replacing elements with a non existing key.
        $this->assertEquals('4,2,6', helper::sequence_replace('1,2,3', [1 => 4, 3 => 6, 10 => 12]));
        // Test replacing elements in an empty sequence (should return an empty sequence).
        $this->assertEquals('', helper::sequence_replace('', [1 => 4]));
    }
}
