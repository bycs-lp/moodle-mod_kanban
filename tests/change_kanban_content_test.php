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
 * Unit test for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers      \mod_kanban\external\change_kanban_content
 * @runTestsInSeparateProcesses
 */
final class change_kanban_content_test extends \advanced_testcase {
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
        global $DB, $SCRIPT;

        parent::setUp();

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
        // This is just for the tests of auth_saml2 not to fail.
        $SCRIPT = '/mod/kanban/view.php';
    }

    /**
     * Test for creating a column.
     *
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

        $returnvalue = \mod_kanban\external\change_kanban_content::add_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => 0, 'title' => 'Testcolumn']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::add_column_returns(),
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

        $returnvalue = \mod_kanban\external\change_kanban_content::add_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => $columnids[3], 'title' => 'Testcolumn 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::add_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $this->assertCount(2, $update);
        $columnid = $update[1]['fields']['id'];

        $columnids = array_merge($columnids, [$columnid]);
        $this->assertEquals(join(',', $columnids), $update[0]['fields']['sequence']);

        $this->assertEquals(1, $DB->count_records('kanban_column', ['id' => $columnid]));
    }

    /**
     * Test for creating a card.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::add_card(
            $this->kanban->cmid,
            $boardid,
            ['aftercard' => 0, 'columnid' => $columnid, 'title' => 'Testcard']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::add_card_returns(),
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

        $returnvalue = \mod_kanban\external\change_kanban_content::add_card(
            $this->kanban->cmid,
            $boardid,
            ['aftercard' => $cardid, 'columnid' => $columnid, 'title' => 'Testcard 2']
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::add_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);
        $card2id = $update[0]['fields']['id'];
        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('columns', $update[1]['name']);
        $this->assertEquals(join(',', [$cardid, $card2id]), $update[1]['fields']['sequence']);
    }

    /**
     * Test for moving a column.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::move_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[0], $columnids[1]]), $update[0]['fields']['sequence']);

        $returnvalue = \mod_kanban\external\change_kanban_content::move_column(
            $this->kanban->cmid,
            $boardid,
            ['aftercol' => $columnids[1], 'columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::move_column_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('board', $update[0]['name']);

        $this->assertEquals(join(',', [$columnids[2], $columnids[1], $columnids[0]]), $update[0]['fields']['sequence']);
    }

    /**
     * Test for moving a card.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => 0, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::move_card_returns(),
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

        $returnvalue = \mod_kanban\external\change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::move_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('columns', $update[0]['name']);

        $this->assertEquals(join(',', [$cards[2]->id, $cards[0]->id]), $update[0]['fields']['sequence']);

        $returnvalue = \mod_kanban\external\change_kanban_content::move_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[1]->id, 'aftercard' => $cards[2]->id, 'columnid' => $columnids[2]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::move_card_returns(),
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
    }

    /**
     * Test for deleting a card.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::delete_card(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::delete_card_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('columns', $update[0]['name']);
        $this->assertEquals('cards', $update[1]['name']);

        $this->assertEquals('', $update[0]['fields']['sequence']);
        $this->assertEquals($cards[0]->id, $update[1]['fields']['id']);

        // ToDo: Test deleting history / discussion here.
    }

    /**
     * Test for deleting a column.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::delete_column(
            $this->kanban->cmid,
            $boardid,
            ['columnid' => $columnids[0]]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::delete_column_returns(),
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
    }

    /**
     * Test for (un-)assigning an user to a card.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::assign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('users', $update[1]['name']);
        $this->assertEquals([$this->users[0]->id], $update[0]['fields']['assignees']);

        $returnvalue = \mod_kanban\external\change_kanban_content::assign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::assign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(2, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals('users', $update[1]['name']);
        $this->assertEquals([$this->users[0]->id, $this->users[2]->id], $update[0]['fields']['assignees']);

        $returnvalue = \mod_kanban\external\change_kanban_content::unassign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[0]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([$this->users[2]->id], $update[0]['fields']['assignees']);

        $returnvalue = \mod_kanban\external\change_kanban_content::unassign_user(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'userid' => $this->users[2]->id]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::unassign_user_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals([], $update[0]['fields']['assignees']);
    }

    /**
     * Test for setting completion status of a card.
     *
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
        $returnvalue = \mod_kanban\external\change_kanban_content::set_card_complete(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'state' => 1]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(1, $update[0]['fields']['completed']);

        $returnvalue = \mod_kanban\external\change_kanban_content::set_card_complete(
            $this->kanban->cmid,
            $boardid,
            ['cardid' => $cards[2]->id, 'state' => 0]
        );
        $returnvalue = \external_api::clean_returnvalue(
            \mod_kanban\external\change_kanban_content::set_card_complete_returns(),
            $returnvalue
        );

        $update = json_decode($returnvalue['update'], true);

        $this->assertCount(1, $update);
        $this->assertEquals('cards', $update[0]['name']);
        $this->assertEquals(0, $update[0]['fields']['completed']);
    }
}
