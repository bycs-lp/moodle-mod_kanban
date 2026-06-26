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

use mod_kanban\external\change_kanban_content;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Unit test for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(change_kanban_content::class)]
final class change_kanban_content_test extends \advanced_testcase {
    /** @var \stdClass The course used for testing */
    private \stdClass $course;
    /** @var \stdClass The main kanban used for testing */
    private \stdClass $kanban;
    /** @var \stdClass A second kanban in the same course used for testing */
    private \stdClass $otherkanban;
    /** @var int The boardid of a second kanban in the same course used for testing */
    private int $otherkanbanboardid;
    /** @var int A cardid of a second kanban in the same course used for testing */
    private int $otherkanbancardid;
    /** @var int A columnid of a second kanban in the same course used for testing */
    private int $otherkanbancolumnid;
    /** @var int A messageid of a second kanban in the same course used for testing */
    private int $otherkanbanmessageid;
    /** @var array The users used for testing */
    private array $users;

    /**
     * Prepare testing environment
     *
     * @return void
     */
    public function setUp(): void {
        global $DB, $SCRIPT;

        parent::setUp();

        $this->course = $this->getDataGenerator()->create_course();
        $this->kanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course, 'enablehistory' => 1]);
        $this->otherkanban = $this->getDataGenerator()->create_module('kanban', ['course' => $this->course]);
        $boardmanager = new boardmanager($this->otherkanban->cmid);
        $this->otherkanbanboardid = $boardmanager->create_board();
        $boardmanager->load_board($this->otherkanbanboardid);
        $this->otherkanbancolumnid = $boardmanager->add_column(0, ['title' => 'Testcolumn']);
        $this->otherkanbancardid = $boardmanager->add_card($this->otherkanbancolumnid, 0, ['title' => 'Testcard']);
        $this->otherkanbanmessageid = $boardmanager->add_discussion_message($this->otherkanbancardid, 'Testmessage');

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
        // This is just for the tests of auth_saml2 not to fail.
        $SCRIPT = '/mod/kanban/view.php';
    }

    /**
     * Test for creating a column.
     * @covers \mod_kanban\external\change_kanban_content::add_column
     * @return void
     */
    public function test_add_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);

        $returnvalue = change_kanban_content::add_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => 0, 'title' => 'Testcolumn']
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::add_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('board', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $columnid = $update[1]['fields']['id'];

        $columnids = array_merge([$columnid], $columnids);
        $this->assertEquals(join(',', $columnids), $update[0]['fields']['sequence']);

        $this->assertEquals(1, $DB->count_records('kanban_column', ['id' => $columnid]));

        $returnvalue = change_kanban_content::add_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => $columnids[3], 'title' => 'Testcolumn 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::add_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(2, $update);
        $columnid = $update[1]['fields']['id'];

        $columnids = array_merge($columnids, [$columnid]);
        $this->assertEquals(join(',', $columnids), $update[0]['fields']['sequence']);

        $this->assertEquals(1, $DB->count_records('kanban_column', ['id' => $columnid]));

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::add_column(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['aftercol' => 0, 'title' => 'Testcolumn']
        );
    }

    /**
     * Test for creating a card.
     * @covers \mod_kanban\external\change_kanban_content::add_card
     * @return void
     */
    public function test_add_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $DB->get_field('kanban_column', 'id', ['kanban_board' => $boardid], IGNORE_MULTIPLE);
        $returnvalue = change_kanban_content::add_card(
            $this->kanban->cmid,
            $boardid,
            ['aftercard' => 0, 'columnid' => $columnid, 'title' => 'Testcard']
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::add_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $cardid = $update[0]['fields']['id'];

        $card = $boardmanager->get_card($cardid);
        $this->assertEquals('Testcard', $card->title);
        $this->assertEquals($boardid, $update[0]['fields']['kanban_board']);
        $this->assertEquals($columnid, $update[0]['fields']['kanban_column']);
        $this->assertEquals($cardid, $update[1]['fields']['sequence']);

        $returnvalue = change_kanban_content::add_card(
            $this->kanban->cmid,
            $boardid,
            ['aftercard' => $cardid, 'columnid' => $columnid, 'title' => 'Testcard 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::add_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $card2id = $update[0]['fields']['id'];
        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals(join(',', [$cardid, $card2id]), $update[1]['fields']['sequence']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::add_card(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['aftercard' => 0, 'title' => 'Testcard', 'columnid' => $this->otherkanbancolumnid]
        );
    }

    /**
     * Test for moving a column.
     * @covers \mod_kanban\external\change_kanban_content::move_column
     * @return void
     */
    public function test_move_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $returnvalue = change_kanban_content::move_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[0], $columnids[1]]), $update[0]['fields']['sequence']);

        $returnvalue = change_kanban_content::move_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => $columnids[1], 'columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[1], $columnids[0]]), $update[0]['fields']['sequence']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::move_column(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['aftercol' => 0, 'columnid' => $this->otherkanbancolumnid]
        );
    }

    /**
     * Test for moving a card.
     * @covers \mod_kanban\external\change_kanban_content::move_card
     * @return void
     */
    public function test_move_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        // As the target column has autoclose enabled by default, we get two updates for cards.
        $this->assertCount(4, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('columns', $update[2]['name']);
        $this->assertEquals('cards', $update[3]['name']);

        $this->assertEquals(join(',', [$cards[0]->id, $cards[2]->id]), $update[2]['fields']['sequence']);
        $this->assertEquals('', $update[1]['fields']['sequence']);
        $this->assertEquals($columnids[2], $update[0]['fields']['kanban_column']);

        $returnvalue = change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('columns', $update[0]['name']);

        $this->assertEquals(join(',', [$cards[2]->id, $cards[0]->id]), $update[0]['fields']['sequence']);

        $returnvalue = change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[1]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        // As the target column has autoclose enabled by default, we get two updates for cards.
        $this->assertCount(4, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('columns', $update[2]['name']);
        $this->assertEquals('cards', $update[3]['name']);

        $this->assertEquals(join(',', [$cards[2]->id, $cards[1]->id, $cards[0]->id]), $update[2]['fields']['sequence']);
        $this->assertEquals('', $update[1]['fields']['sequence']);
        $this->assertEquals($columnids[2], $update[0]['fields']['kanban_column']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::move_card(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['aftercard' => 0, 'title' => 'Testcard', 'columnid' => $this->otherkanbancolumnid]
        );
    }

    /**
     * Test for deleting a card.
     * @covers \mod_kanban\external\change_kanban_content::delete_card
     * @return void
     */
    public function test_delete_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = change_kanban_content::delete_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::delete_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('columns', $update[0]['name']);
        $this->assertEquals('cards', $update[1]['name']);

        $this->assertEquals('', $update[0]['fields']['sequence']);
        $this->assertEquals($cards[0]->id, $update[1]['fields']['id']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::delete_card(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $this->otherkanbancardid]
        );
    }

    /**
     * Test for deleting a column.
     * @covers \mod_kanban\external\change_kanban_content::delete_column
     * @return void
     */
    public function test_delete_column(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = change_kanban_content::delete_column(
            $this->kanban->cmid,
            $boardid,
            ['columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::delete_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(3, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals('board', $update[2]['name']);

        $this->assertEquals($cards[0]->id, $update[0]['fields']['id']);
        $this->assertEquals($columnids[0], $update[1]['fields']['id']);
        $this->assertEquals(join(',', [$columnids[1], $columnids[2]]), $update[2]['fields']['sequence']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::delete_column(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['columnid' => $this->otherkanbancolumnid]
        );
    }

    /**
     * Test for (un-)assigning an user to a card.
     * @covers \mod_kanban\external\change_kanban_content::assign_user
     * @covers \mod_kanban\external\change_kanban_content::unassign_user
     * @return void
     */
    public function test_assign_unassign_user(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = change_kanban_content::assign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('users', $update[1]['name']);
        $this->assertEquals([$this->users[0]->id], $update[0]['fields']['assignees']);

        $returnvalue = change_kanban_content::assign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('users', $update[1]['name']);
        $this->assertEquals([$this->users[0]->id, $this->users[2]->id], $update[0]['fields']['assignees']);

        $returnvalue = change_kanban_content::unassign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([$this->users[2]->id], $update[0]['fields']['assignees']);

        $returnvalue = change_kanban_content::unassign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([], $update[0]['fields']['assignees']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::assign_user(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $this->otherkanbancardid, 'userid' => $this->users[0]->id]
        );
    }

    /**
     * Test for setting completion status of a card.
     * @covers \mod_kanban\external\change_kanban_content::set_card_complete
     * @return void
     */
    public function test_set_card_complete(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnids = $DB->get_fieldset_select('kanban_column', 'id', 'kanban_board = :id', ['id' => $boardid]);
        $cards = [];
        foreach ($columnids as $columnid) {
            $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Testcard']);
            $cards[] = $boardmanager->get_card($cardid);
        }
        $returnvalue = change_kanban_content::set_card_complete(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'state' => 1]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(1, $update[0]['fields']['completed']);

        $returnvalue = change_kanban_content::set_card_complete(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'state' => 0]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(0, $update[0]['fields']['completed']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::set_card_complete(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $this->otherkanbancardid, 'state' => 1]
        );
    }

    /**
     * Test for pushing a copy of a card to another board.
     * @covers \mod_kanban\external\change_kanban_content::push_card_copy
     * @return void
     */
    public function test_push_card_copy(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        // Set to the teacher to have permissions to push cards to other kanbans.
        $this->setUser($this->users[2]);
        // Create a new kanban activity with user boards enabled to simplify testing.
        $this->kanban = $this->getDataGenerator()->create_module(
            'kanban',
            ['course' => $this->course, 'enablehistory' => 1, 'userboards' => 1]
        );
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $personalboardid = $boardmanager->create_board_from_template(0, ['userid' => $this->users[0]->id]);

        $boardmanager->load_board($boardid);
        $columnid = $boardmanager->add_column(0, ['title' => 'Test Column']);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Test Card']);

        $returnvalue = change_kanban_content::push_card_copy(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cardid]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::push_card_copy_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(0, $update);

        $personalcards = $DB->get_records('kanban_card', ['kanban_board' => $personalboardid]);
        $this->assertCount(1, $personalcards);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::push_card_copy(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $cardid]
        );
    }

    /**
     * Test for duplicating a card within the same board.
     * @covers \mod_kanban\external\change_kanban_content::duplicate_card
     * @return void
     */
    public function test_duplicate_card(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);

        $columnid = $boardmanager->add_column(0, ['title' => 'Test Column']);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'dup']);

        $returnvalue = change_kanban_content::duplicate_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cardid]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::duplicate_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('dup', $update[0]['fields']['title']);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::duplicate_card(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $cardid]
        );
    }

    /**
     * Test for deleting a board.
     * @covers \mod_kanban\external\change_kanban_content::delete_board
     * @return void
     */
    public function test_delete_board(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();

        $returnvalue = change_kanban_content::delete_board(
            $this->kanban->cmid,
            $boardid
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::delete_board_returns(),
            $returnvalue
        );
        $this->assertFalse($DB->record_exists('kanban_board', ['id' => $boardid]));

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::delete_board(
            $this->kanban->cmid,
            $this->otherkanbanboardid
        );
    }

    /**
     * Test for saving a board as template.
     * @covers \mod_kanban\external\change_kanban_content::save_board_as_template
     * @return void
     */
    public function test_save_as_template(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);

        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $boardmanager->add_column(0, ['title' => 'Test Column']);

        $returnvalue = change_kanban_content::save_as_template(
            $this->kanban->cmid,
            $boardid
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::save_as_template_returns(),
            $returnvalue
        );
        $this->assertTrue($DB->record_exists('kanban_board', ['template' => 1, 'kanban_instance' => $this->kanban->id]));

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::save_as_template(
            $this->kanban->cmid,
            $this->otherkanbanboardid
        );
    }

    /**
     * Test for deleting a discussion message.
     * @covers \mod_kanban\external\change_kanban_content::delete_discussion_message
     * @return void
     */
    public function test_delete_discussion_message(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $boardmanager->add_column(0, ['title' => 'Test Column']);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Test Card']);
        $messageid = $boardmanager->add_discussion_message($cardid, 'Test Message');

        $returnvalue = change_kanban_content::delete_discussion_message(
            $this->kanban->cmid,
            $boardid,
            ['messageid' => $messageid]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::delete_discussion_message_returns(),
            $returnvalue
        );
        $this->assertFalse($DB->record_exists('kanban_discussion_comment', ['id' => $messageid]));

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::delete_discussion_message(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['messageid' => $this->otherkanbanmessageid]
        );
    }

    /**
     * Test for adding a discussion message.
     * @covers \mod_kanban\external\change_kanban_content::add_discussion_message
     * @return void
     */
    public function test_add_discussion_message(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $boardmanager->add_column(0, ['title' => 'Test Column']);
        $cardid = $boardmanager->add_card($columnid, 0, ['title' => 'Test Card']);

        $returnvalue = change_kanban_content::add_discussion_message(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cardid, 'message' => 'Test Message']
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::add_discussion_message_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(2, $update);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::add_discussion_message(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['cardid' => $this->otherkanbancardid, 'message' => 'Test Message']
        );
    }

    /**
     * Test for locking a column.
     * @covers \mod_kanban\external\change_kanban_content::set_column_locked
     * @return void
     */
    public function test_set_column_locked(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $boardmanager->load_board($boardid);
        $columnid = $boardmanager->add_column(0, ['title' => 'Test Column']);
        $returnvalue = change_kanban_content::set_column_locked(
            $this->kanban->cmid,
            $boardid,
            ['columnid' => $columnid, 'state' => 1]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::set_column_locked_returns(),
            $returnvalue
        );
        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(1, $update);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::set_column_locked(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['columnid' => $this->otherkanbancolumnid, 'state' => 1]
        );
    }

    /**
     * Test for locking all columns of a board.
     * @covers \mod_kanban\external\change_kanban_content::set_board_columns_locked
     * @return void
     */
    public function test_set_board_columns_locked(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/lib/externallib.php');

        $this->resetAfterTest();
        $this->setUser($this->users[2]);
        $boardmanager = new boardmanager($this->kanban->cmid);
        $boardid = $boardmanager->create_board();
        $returnvalue = change_kanban_content::set_board_columns_locked(
            $this->kanban->cmid,
            $boardid,
            ['state' => 1]
        );
        $returnvalue = \external_api::clean_returnvalue(
            change_kanban_content::set_board_columns_locked_returns(),
            $returnvalue
        );
        $update = json_decode($returnvalue['update'], true);
        // As the board is created with 3 columns by default, we get 3 updates for the columns and one for the board itself.
        $this->assertCount(4, $update);

        $this->expectException(\moodle_exception::class);
        $returnvalue = change_kanban_content::set_board_columns_locked(
            $this->kanban->cmid,
            $this->otherkanbanboardid,
            ['state' => 1]
        );
    }
}
