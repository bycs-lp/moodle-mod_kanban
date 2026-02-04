<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language file for mod_kanban
 *
 * @package     mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author      Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcard'] = 'Add a card to this column';
$string['addcolumn'] = 'Add a column to this board';
$string['aftercompletion'] = 'after card is closed';
$string['afterdue'] = 'after card is due';
$string['assignee'] = 'Assignee';
$string['assignees'] = 'Assignees';
$string['assignme'] = 'Assign me';
$string['attachments'] = 'Attachments';
$string['autoclose'] = 'Auto close cards';
$string['autohide'] = 'Auto hide closed cards';
$string['cachedef_board'] = 'Cache for a board instance';
$string['cachedef_timestamp'] = 'Timestamp of last modification of card, column or board instance';
$string['cardcount'] = 'Number of cards in this column';
$string['cardnotfound'] = 'Card not found';
$string['cardtitle'] = 'Card title';
$string['changegroup'] = 'Change group board';
$string['changeuser'] = 'Change user board';
$string['closecard'] = 'Close card';
$string['color'] = 'Color';
$string['column'] = 'Column';
$string['columntitle'] = 'Column title';
$string['completioncomplete'] = 'Complete this number of cards';
$string['completioncreate'] = 'Create this number of cards';
$string['completiondetail:complete'] = 'Complete cards: {$a}';
$string['completiondetail:create'] = 'Create cards: {$a}';
$string['connectionlost'] = 'Connection lost';
$string['connectionlostmessage'] = 'Connection to the server was lost. Trying to reconnect...';
$string['courseboard'] = 'Shared board';
$string['createtemplate'] = 'Create template';
$string['deleteboard'] = 'Delete board';
$string['deleteboardconfirm'] = 'Are you sure you want to delete this board? A new board will be created based on the template.';
$string['deletecard'] = 'Delete card';
$string['deletecardconfirm'] = 'Do you really want to delete this card?';
$string['deletecolumn'] = 'Delete column';
$string['deletecolumnconfirm'] = 'Do you really want to delete this column?';
$string['deletemessage'] = 'Delete message';
$string['deletemessageconfirm'] = 'Do you really want to delete this message?';
$string['deletetemplate'] = 'Delete template';
$string['deletetemplateconfirm'] = 'Are you sure you want to delete this template?';
$string['doing'] = 'Doing';
$string['done'] = 'Done';
$string['due'] = 'Due';
$string['duedate'] = 'Due date';
$string['editboard'] = 'Edit board';
$string['editcard'] = 'Edit card';
$string['editcolumn'] = 'Edit column';
$string['editdetails'] = 'Edit details';
$string['editing_this_card_is_not_allowed'] = 'Editing this card is not allowed';
$string['enablehistory'] = 'Enable history';
$string['enablehistory_help'] = 'Enable recording history of cards in this board (e.g. when card was moved / renamed / completed)';
$string['enablehistorydescription'] = 'Enabling this option will make history of changes available to the boards.';
$string['filtersearch'] = 'Search card titles';
$string['filtersearchplaceholder'] = 'Search card titles...';
$string['groupboard'] = 'Group board for group "{$a}"';
$string['hidehidden'] = 'Hide hidden cards';
$string['history'] = 'History';
$string['history_card_added'] = '{$a->username} added card "{$a->title}" to column "{$a->columnname}"';
$string['history_card_assigned'] = '{$a->username} assigned card to user {$a->affectedusername}';
$string['history_card_completed'] = '{$a->username} completed the card';
$string['history_card_deleted'] = '{$a->username} deleted card from column "{$a->columnname}"';
$string['history_card_moved'] = '{$a->username} moved card to column "{$a->columnname}"';
$string['history_card_reopened'] = '{$a->username} reopened the card';
$string['history_card_unassigned'] = '{$a->username} unassigned card from user {$a->affectedusername}';
$string['history_card_updated'] = '{$a->username} changed card title to "{$a->title}"';
$string['history_discussion_added'] = '{$a->username} added discussion message';
$string['history_discussion_deleted'] = '{$a->username} deleted discussion message';
$string['historyfor'] = 'History for card "{$a}"';
$string['invalidcardboardcombination'] = "Card doesn't belong to selected board.";
$string['kanban:addcard'] = 'Add a card to a Kanban board';
$string['kanban:addinstance'] = 'Add a Kanban board';
$string['kanban:assignothers'] = 'Assign others to a card';
$string['kanban:assignself'] = 'Assign self to a card';
$string['kanban:editallboards'] = 'Edit all boards';
$string['kanban:manageallcards'] = 'Edit / move all cards';
$string['kanban:manageassignedcards'] = 'Edit / move cards assigned to oneself';
$string['kanban:manageboard'] = 'Manage the board (templates, delete the board)';
$string['kanban:managecolumns'] = 'Edit the columns of the board';
$string['kanban:view'] = 'View a Kanban board';
$string['kanban:viewallboards'] = 'View all boards';
$string['kanban:viewhistory'] = 'View the history of the board';
$string['linknumbers'] = 'Link card numbers';
$string['linknumbers_help'] = 'Card numbers in card descriptions and discussion comments will be linked.';
$string['liveupdatetime'] = 'Interval for live update in seconds';
$string['liveupdatetimedescription'] = 'Boards will look for updates after this interval. Set to 0 to disable live update.';
$string['loading'] = 'Loading kanban board';
$string['loadingdiscussion'] = 'Loading discussion';
$string['loadinghistory'] = 'Loading history';
$string['lock'] = 'Lock';
$string['lockboardcolumns'] = 'Lock board columns';
$string['message_assigned_fullmessage'] = 'Card "{$a->title}" in board "{$a->boardname}" was assigned to you by {$a->username}';
$string['message_assigned_smallmessage'] = 'Card "{$a->title}" was assigned to you';
$string['message_closed_fullmessage'] = 'Card "{$a->title}" was closed by {$a->username}';
$string['message_closed_smallmessage'] = 'Card "{$a->title}" was closed';
$string['message_discussion_fullmessage'] = 'There is a new message in discussion for card "{$a->title}" in board "{$a->boardname}":
{$a->username}
{$a->content}';
$string['message_discussion_smallmessage'] = 'Card "{$a->title}" was discussed';
$string['message_due_fullmessage'] = 'Card "{$a->title}" in board "{$a->boardname}" is due at {$a->duedate}';
$string['message_due_smallmessage'] = 'Card "{$a->title}" is due';
$string['message_moved_fullmessage'] = 'Card "{$a->title}" was moved to column "{$a->columnname}" by {$a->username}';
$string['message_moved_smallmessage'] = 'Card "{$a->title}" was moved';
$string['message_reopened_fullmessage'] = 'Card "{$a->title}" in board "{$a->boardname}" was reopened by {$a->username}';
$string['message_reopened_smallmessage'] = 'Card "{$a->title}" was reopened';
$string['message_unassigned_fullmessage'] = 'Card "{$a->title}" in board "{$a->boardname}" was unassigned from you by {$a->username}';
$string['message_unassigned_smallmessage'] = 'Card "{$a->title}" was unassigned from you';
$string['messageprovider:assigned'] = 'Card assigned / unassigned';
$string['messageprovider:closed'] = 'Card closed / reopened';
$string['messageprovider:discussion'] = 'Card discussion';
$string['messageprovider:due'] = 'Card due';
$string['messageprovider:moved'] = 'Card moved';
$string['modulename'] = 'Kanban board';
$string['modulename_help'] = 'This activity supports using the Kanban method for managing projects or learning processes.
Kanban is an agile project management method that organizes tasks through a visual board to optimize workflow. Tasks are categorized into columns such as "To Do," "In Progress," and "Done" to make progress transparent. The goal is to identify bottlenecks in the workflow and continuously improve efficiency.
<br>Depending on the settings, there can be several types of boards within a Kanban activity:
<ul>
    <li>The course board: Accessible to everyone who has access to the activity</li>
    <li>Personal boards: For each user</li>
    <li>Group boards</li>
    <li>Template boards: Anyone who can manage boards can copy an existing board as a template.</li>
</ul>';
$string['modulenameplural'] = 'Kanban boards';
$string['moveaftercard'] = 'Move after';
$string['movecard'] = 'Move card';
$string['movecolumn'] = 'Move column';
$string['myuserboard'] = 'My personal board';
$string['name'] = 'Name of the board';
$string['name_help'] = 'This name will be visible in course overview and as a title of the board';
$string['newcard'] = 'New card';
$string['newcolumn'] = 'New column';
$string['nogroupavailable'] = 'No group available';
$string['nokanbaninstances'] = 'There are no kanban boards in this course or you are not allowed to access them';
$string['nonewduedate'] = 'No new due date';
$string['nouser'] = 'No user';
$string['nouserboards'] = 'No personal boards';
$string['pluginadministration'] = 'Kanban administration';
$string['pluginname'] = 'Kanban board';
$string['privacy:metadata:action'] = "Action";
$string['privacy:metadata:affected_userid'] = "Affected user";
$string['privacy:metadata:content'] = "Content";
$string['privacy:metadata:createdby'] = "User that created the card";
$string['privacy:metadata:groupid'] = "Group id";
$string['privacy:metadata:kanban_assignee'] = "Assignee";
$string['privacy:metadata:kanban_board'] = "Board";
$string['privacy:metadata:kanban_card'] = "Card";
$string['privacy:metadata:kanban_column'] = "Column";
$string['privacy:metadata:kanban_discussion_comment'] = "Comment";
$string['privacy:metadata:kanban_history'] = "History";
$string['privacy:metadata:parameters'] = "Information about the action";
$string['privacy:metadata:timecreated'] = "Time of creation";
$string['privacy:metadata:timemodified'] = "Time of last modification";
$string['privacy:metadata:timestamp'] = "Time of the action";
$string['privacy:metadata:userid'] = "User id";
$string['pushcard'] = 'Push card to all boards';
$string['pushcardconfirm'] = 'This will send a copy of this card to all boards inside this kanban activity including templates. Existing copies will be replaced.';
$string['reminderdate'] = 'Reminder date';
$string['remindertask'] = 'Send reminder notifications';
$string['repeat'] = 'Repeat card';
$string['repeat_help'] = "If selected, a new copy of this card will be created in the leftmost column as soon as this instance is completed. Discussion, history and assignees are not copied.
You can choose how to calculate the new due date, if needed. This will also be applied to the new reminder date.";
$string['repeat_interval'] = 'Interval';
$string['repeat_interval_type'] = 'Frequency';
$string['repeat_newduedate'] = 'New due date';
$string['reset_group'] = 'Reset group boards';
$string['reset_kanban'] = 'Reset shared boards';
$string['reset_personal'] = 'Reset personal boards';
$string['saveastemplate'] = 'Save as template';
$string['saveastemplateconfirm'] = 'Are you sure you want to save this board as a template? It will replace the current template if there is one.';
$string['senddiscussion'] = 'Send discussion message';
$string['showattachment'] = 'Show attachments';
$string['showboard'] = 'Show shared board';
$string['showdescription'] = 'Show description';
$string['showdiscussion'] = 'Show discussion';
$string['showhidden'] = 'Show hidden cards';
$string['showtemplate'] = 'Show template';
$string['startdiscussion'] = 'Start discussion';
$string['template'] = 'Template';
$string['toboard'] = 'Board "{$a->boardname}"';
$string['todo'] = 'Todo';
$string['topofcolumn'] = 'Top of column';
$string['unassign'] = 'Unassign this user';
$string['unassignme'] = 'Unassign me';
$string['uncomplete'] = 'Reopen';
$string['uncompletedassignedcards'] = 'Uncompleted cards assigned to you';
$string['unlock'] = 'Unlock';
$string['unlockboardcolumns'] = 'Unlock board columns';
$string['usenumbers'] = 'Use card numbers';
$string['usenumbers_help'] = 'This enables card numbers for this kanban activity. Numbers are unique per board (i.e. cards in user / group boards and the shared board can have the same number).';
$string['userboard'] = 'Personal board for {$a}';
$string['userboards'] = 'Personal boards';
$string['userboards_help'] = 'Enables personal boards for the participants (only visible to them and to the trainers)';
$string['userboardsenabled'] = 'Personal boards enabled';
$string['userboardsonly'] = 'Personal boards only';
$string['wiplimit'] = 'WIP limit per person';
$string['wiplimitenable'] = 'Enable WIP limit';
$string['wiplimitgreaterzero'] = 'WIP limit needs to be greater than zero';
$string['wiplimitreached'] = 'WIP limit is reached for {$a->users}.';
