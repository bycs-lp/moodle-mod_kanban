// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see http://www.gnu.org/licenses/.

/**
 * Selectors for mod_kanban.
 * @module mod_kanban/selectors
 * @copyright 2024 ISB Bayern
 * @author Stefan Hanauska stefan.hanauska@csg-in.de
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/
export default {
    ADDCARD: `[data-action="add_card"]`,
    ADDCARDCONTAINER: `.mod_kanban_addcard_container`,
    ADDCARDFIRST: `.mod_kanban_addcard_first`,
    ADDCOLUMN: `[data-action="add_column"]`,
    ADDCOLUMNCONTAINER: `.mod_kanban_addcolumn_container`,
    ADDCOLUMNFIRST: `.mod_kanban_addcolumn_first`,
    ASSIGNEES: `.mod_kanban_assignees`,
    ASSIGNSELF: `[data-action="assign_self"]`,
    ASSIGNUSER: `[data-action="assign_user"]`,
    ASSIGNEDUSER: `.mod_kanban_assigned_user`,
    BOARD: `.mod_kanban_board`,
    CARD: `.mod_kanban_card`,
    CARDCOUNT: `.mod_kanban_cardcount`,
    CARDNUMBER: `.mod_kanban_card_number`,
    COLUMN: `.mod_kanban_column`,
    COLUMNCONTAINER: `.mod_kanban_column_container`,
    COLUMNINNER: `.mod_kanban_column_inner`,
    COMPLETE: `[data-action="complete_card"]`,
    COMPLETIONSTATE: `.mod_kanban_card_completion`,
    CONTAINER: `.mod_kanban_render_container`,
    DELETEBOARD: `[data-action="delete_board"]`,
    DELETECARD: `[data-action="delete_card"]`,
    DELETECOLUMN: `[data-action="delete_column"]`,
    DELETEMESSAGE: `[data-action="delete_message"]`,
    DELETETEMPLATE: `[data-action="delete_template"]`,
    DESCRIPTIONMODAL: `.mod_kanban_description`,
    DESCRIPTIONMODALBODY: `.mod_kanban_description_modal .modal-body`,
    DESCRIPTIONMODALFOOTER: `.mod_kanban_description_modal .modal-footer`,
    DESCRIPTIONMODALTITLE: `.mod_kanban_description_modal .modal-title`,
    DESCRIPTIONTOGGLE: `.mod_kanban_description`,
    DETAILBUTTON: `.mod_kanban_detail_trigger`,
    DISCUSSION: `.mod_kanban_discussion`,
    DISCUSSIONINPUT: `.mod_kanban_discussion_input`,
    DISCUSSIONMESSAGES: `.mod_kanban_discussion_messages`,
    DISCUSSIONMODAL: `.mod_kanban_discussion_modal`,
    DISCUSSIONMODALTITLE: `.mod_kanban_discussion_modal .modal-title`,
    DISCUSSIONMODALTRIGGER: `.mod_kanban_discussion_trigger`,
    DISCUSSIONSEND: `[data-action="send_discussion_message"]`,
    DISCUSSIONSHOW: `[data-action="show_discussion"]`,
    DUEDATE: `.mod_kanban_duedate`,
    DUPLICATE: `[data-action="duplicate_card"]`,
    EDITDETAILS: `[data-action="edit_details"]`,
    HIDEHIDDEN: `[data-action="hide_hidden"]`,
    HISTORY: `.mod_kanban_history`,
    HISTORYITEMS: `.mod_kanban_history_items`,
    HISTORYMODAL: `.mod_kanban_history_modal`,
    HISTORYMODALTRIGGER: `[data-action="show_history"]`,
    INPLACEEDITABLE: `.inplaceeditable`,
    LOCKCOLUMN: `[data-action="lock_column"]`,
    LOCKBOARDCOLUMNS: `[data-action="lock_board_columns"]`,
    MAIN: `.mod_kanban_main`,
    MOVECARDAFTERCARD: `.mod_kanban_move_card_aftercard`,
    MOVECARDCOLUMN: `.mod_kanban_move_card_column`,
    MOVEMODALTRIGGER: `[data-action="move_card"]`,
    PUSHCARD: `[data-action="push_card"]`,
    SAVEASTEMPLATE: `[data-action="create_template"]`,
    SCROLLLEFT: `.mod_kanban_scroll_left button`,
    SCROLLRIGHT: `.mod_kanban_scroll_right button`,
    SHOWBOARD: `[data-action="show_board"]`,
    SHOWHIDDEN: `[data-action="show_hidden"]`,
    SHOWTEMPLATE: `[data-action="show_template"]`,
    UNASSIGNSELF: `[data-action="unassign_self"]`,
    UNASSIGNUSER: `[data-action="unassign_user"]`,
    UNCOMPLETE: `[data-action="uncomplete_card"]`,
    UNLOCKCOLUMN: `[data-action="unlock_column"]`,
    UNLOCKBOARDCOLUMNS: `[data-action="unlock_board_columns"]`,
    WIPLIMIT: `.mod_kanban_wiplimit`,
};