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

use context_course;

/**
 * Unit test for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanban\boardmanager
 */
final class boardmanager_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private $course;
    /** @var \stdClass The kanban used for testing */
    private $kanban;
    /** @var array The users used for testing */
    private $users;

    /**
     * Prepare testing environment
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();

        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course]);

        for ($i = 0; $i < 3; $i++) {
            $this->users[$i] = $this->getDataGenerator()->create_user(
                [
                    'email' => $i . 'user@example.com',
                    'username' => 'userid' . $i,
                ]
            );
        }

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($this->users[0]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[1]->id, $this->course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($this->users[2]->id, $this->course->id, $teacherrole->id);
    }

    /**
     * Test for creating a (course) board.
     *
     * @return void
     */
    public function test_create_board(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boards = $DB->get_records('kanban_board', ['kanban_instance' => $this->kanban->id]);
        $this->assertCount(1, $boards);
        $boardid = $boardmanager->create_board();
        $this->assertNotEquals(false, $boardid);
        $boards = $DB->get_records('kanban_board', ['kanban_instance' => $this->kanban->id]);
        $this->assertCount(2, $boards);
        // Board should consist of three columns without any cards as there is no template yet.
        $columns = $DB->get_records('kanban_column', ['kanban_board' => $boardid]);
        $this->assertCount(3, $columns);
        $cards = $DB->get_records('kanban_card', ['kanban_board' => $boardid]);
        $this->assertCount(0, $cards);
    }

    /**
     * Test for deleting a board.
     *
     * @return void
     */
    public function test_delete_board(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardcount = $DB->count_records('kanban_board', ['kanban_instance' => $this->kanban->id]);
        $boardid = $boardmanager->create_board();
        $this->assertEquals($boardcount + 1, $DB->count_records('kanban_board', ['kanban_instance' => $this->kanban->id]));
        $this->assertEquals(3, $DB->count_records('kanban_column', ['kanban_board' => $boardid]));

        $boardmanager->delete_board($boardid);
        $this->assertEquals($boardcount, $DB->count_records('kanban_board', ['kanban_instance' => $this->kanban->id]));
        $this->assertEquals(0, $DB->count_records('kanban_column', ['kanban_board' => $boardid]));
    }

    /**
     * Test for creating a card.
     *
     * @return void
     */
    public function test_add_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanban_column', 'id', ['kanban_board' => $boardid], IGNORE_MULTIPLE);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
        $card = $boardmanager->get_card($cardid);
        $this->assertEquals('Testcard', $card->title);
        $this->assertEquals($boardid, $card->kanban_board);
        $this->assertEquals($columnid, $card->kanban_column);

        $card2id = $boardmanager->add_card($columnid, $cardid, ['title' => 'Testcard2']);
        $column = $boardmanager->get_column($columnid);
        $this->assertEquals(join(',', [$cardid, $card2id]), $column->sequence);
    }

    /**
     * Test for moving a card.
     *
     * @return void
     */
    public function test_move_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        // Add one card to each column (three columns expected).
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $boardmanager->move_card($cards[0]->id, 0, $columnids[2]);
        $cards[0] = $boardmanager->get_card($cards[0]->id);
        $this->assertEquals($columnids[2], $cards[0]->kanban_column);

        $column = $boardmanager->get_column($columnids[0]);
        $this->assertEquals('', $column->sequence);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals(join(',', [$cards[0]->id, $cards[2]->id]), $column->sequence);

        $boardmanager->move_card($cards[0]->id, $cards[2]->id);
        $cards[0] = $boardmanager->get_card($cards[0]->id);
        $this->assertEquals($columnids[2], $cards[0]->kanban_column);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals($column->sequence, join(',', [$cards[2]->id, $cards[0]->id]));

        $boardmanager->move_card($cards[1]->id, $cards[2]->id, $columnids[2]);
        $cards[1] = $boardmanager->get_card($cards[1]->id);
        $this->assertEquals($columnids[2], $cards[1]->kanban_column);

        $column = $boardmanager->get_column($columnids[2]);
        $this->assertEquals($column->sequence, join(',', [$cards[2]->id, $cards[1]->id, $cards[0]->id]));
    }

    /**
     * Test for deleting a card.
     *
     * @return void
     */
    public function test_delete_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);

        $cardid = $boardmanager->add_card($columnids[0], 0, ['title' => 'Testcard']);
        $boardmanager->delete_card($cardid);
        $this->assertEquals(0, $DB->count_records('kanban_card', ['id' => $cardid]));

        $column = $boardmanager->get_column($columnids[0]);
        $this->assertEquals('', $column->sequence);

        // ToDo: Test deleting history / discussion here.
    }

    /**
     * Test for creating a column.
     *
     * @return void
     */
    public function test_add_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $columnid = $boardmanager->add_column(0, ['title' => 'Testcolumn']);
        $columnids = array_merge([$columnid], $columnids);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);

        $this->assertEquals(1, $DB->count_records('kanban_column', ['id' => $columnid]));

        $columnid = $boardmanager->add_column($columnids[3], ['title' => 'Testcolumn 2']);
        $columnids = array_merge($columnids, [$columnid]);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);

        $this->assertEquals(1, $DB->count_records('kanban_column', ['id' => $columnid]));
    }

    /**
     * Test for creating a column.
     *
     * @return void
     */
    public function test_move_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $boardmanager->move_column($columnids[2], 0);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', [$columnids[2], $columnids[0], $columnids[1]]), $boardmanager->get_board()->sequence);

        $boardmanager->move_column($columnids[0], $columnids[1]);
        $boardmanager->load_board($boardid);
        $this->assertEquals(join(',', [$columnids[2], $columnids[1], $columnids[0]]), $boardmanager->get_board()->sequence);
    }

    /**
     * Test for deleting a column.
     *
     * @return void
     */
    public function test_delete_column(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columncount = $DB->count_records('kanban_column', ['kanban_board' => $boardid]);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);

        $boardmanager->delete_column($columnids[0]);
        $this->assertEquals($columncount - 1, $DB->count_records('kanban_column', ['kanban_board' => $boardid]));
        array_shift($columnids);
        $this->assertEquals(join(',', $columnids), $boardmanager->get_board()->sequence);
    }

    /**
     * Test for permission checking.
     *
     * @return void
     */
    public function test_can_user_manage_specific_card(): void {
        global $DB;

        $this->resetAfterTest();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);

        // Teacher user.
        $this->setUser($this->users[2]);
        $teachercard = $boardmanager->add_card($columnids[0]);
        $teachercardstudentassigned = $boardmanager->add_card($columnids[0]);
        $boardmanager->assign_user($teachercardstudentassigned, $this->users[0]->id);

        // Student user.
        $this->setUser($this->users[0]);
        $studentcard = $boardmanager->add_card($columnids[1]);

        // Student user should not be able to edit a card created by the teacher.
        $this->assertEquals(false, $boardmanager->can_user_manage_specific_card($teachercard));
        // Student user should be able to edit a card he is assigned to.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($teachercardstudentassigned));
        // Student user should be able to edit a card created by himself.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($studentcard));
        // Teacher user should be able to edit every card.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($studentcard, $this->users[2]->id));

        // Test explicitly the mod_kanban/manageallcards capability.
        $context = context_course::instance($boardmanager->get_cminfo()->course);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        // Current student user should not be able to edit teacher card.
        $this->assertEquals(false, $boardmanager->can_user_manage_specific_card($teachercard));
        assign_capability('mod/kanban:manageallcards', CAP_ALLOW, $studentrole->id, $context);
        // Current student user now should be able to also edit teacher card.
        $this->assertEquals(true, $boardmanager->can_user_manage_specific_card($teachercard));
    }

    /**
     * Test for column limit enforcement when adding cards.
     *
     * @return void
     */
    public function test_collimit_add(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $colwithlimit = $boardmanager->add_column(0, ['title' => 'Testcolumn', 'options' => '{"collimit" : 2}']);
        $colwithoutlimit = $boardmanager->add_column(0, ['title' => 'Testcolumn 2']);
        $boardmanager->add_card($colwithlimit, 0, ['title' => 'Card 1']);
        $boardmanager->add_card($colwithlimit, 0, ['title' => 'Card 2']);
        $cardid = $boardmanager->add_card($colwithoutlimit, 0, ['title' => 'Card 3']);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('collimitreached', 'kanban', ['coltitle' => 'Testcolumn', 'limit' => 2]));
        $boardmanager->add_card($colwithlimit, 0, ['title' => 'Card 4']);
    }

    /**
     * Test for column limit enforcement when moving cards.
     *
     * @return void
     */
    public function test_collimit_move(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $colwithlimit = $boardmanager->add_column(0, ['title' => 'Testcolumn', 'options' => '{"collimit" : 2}']);
        $colwithoutlimit = $boardmanager->add_column(0, ['title' => 'Testcolumn 2']);
        $boardmanager->add_card($colwithlimit, 0, ['title' => 'Card 1']);
        $cardid = $boardmanager->add_card($colwithoutlimit, 0, ['title' => 'Card 3']);
        $cardid2 = $boardmanager->add_card($colwithoutlimit, 0, ['title' => 'Card 2']);

        // Moving the first card to the column with limit should work as there is still one free slot.
        $boardmanager->move_card($cardid2, 0, $colwithlimit);

        // This one is too much and should throw an exception.
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('collimitreached', 'kanban', ['coltitle' => 'Testcolumn', 'limit' => 2]));
        $boardmanager->move_card($cardid, 0, $colwithlimit);

        $this->assertEquals($colwithoutlimit, $boardmanager->get_card($cardid)->kanban_column);
    }

    /**
     * Tests the json sanitization function.
     *
     * @dataProvider sanitize_json_string_provider
     * @param string $json the json string to sanitize
     * @param string $expected the expected sanitized json string
     * @return void
     */
    public function test_sanitize_json_string(string $json, string $expected): void {
        $output = helper::sanitize_json_string($json);
        $this->assertEquals($expected, $output);
    }

    /**
     * Data Provider for self::test_sanitize_json_string.
     *
     * @return array[] containing the keys 'json' and 'expected'
     */
    public static function sanitize_json_string_provider(): array {
        return [
            [
                'json' => '{"test": "<b>bad html</b><script>console.log(\"bla\")</script>"}',
                'expected' => '{"test":"<b>bad html<\/b>"}',
            ],
            [
                'json' => '{"test": "<b>good html</b>"}',
                'expected' => '{"test":"<b>good html<\/b>"}',
            ],
            [
                'json' => '{"test": "<b>good html</b>","anotherkey": [{"nestedkey":"<script>console.log(\"bla\");</script>"}]}',
                'expected' => '{"test":"<b>good html<\/b>","anotherkey":[{"nestedkey":""}]}',
            ],
            [
                'json' => '{"te<script>console.log(\"bla\");</script>st": "<b>good html</b>"}',
                'expected' => '{"test":"<b>good html<\/b>"}',
            ],
        ];
    }
}
