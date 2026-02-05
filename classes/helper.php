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

/**
 * Helper class
 *
 * @package    mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_kanban;

use calendar_event;
use stdClass;

/**
 * Helper class
 *
 * @package    mod_kanban
 * @copyright   2023-2026 ISB Bayern
 * @author     Stefan Hanauska
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Adds an item to a string sequence of integer values, divided by commas.
     *
     * @param string $sequence The original sequence
     * @param int $afteritem The item to add after
     * @param int $newitem The item to add
     * @return string The new sequence
     */
    public static function sequence_add_after(string $sequence, int $afteritem, int $newitem): string {
        if (empty($sequence)) {
            $seq = [];
        } else {
            $seq = explode(',', $sequence);
        }

        if ($afteritem == 0) {
            $seq = array_merge([$newitem], $seq);
        } else if (!in_array($afteritem, $seq)) {
            $seq[] = $newitem;
        } else {
            $pos = array_search($afteritem, $seq);
            $seq = array_merge(array_slice($seq, 0, $pos + 1), [$newitem], array_slice($seq, $pos + 1));
        }
        return join(',', $seq);
    }

    /**
     * Removes an item from a string sequence of integer values, divided by commas.
     *
     * @param string $sequence The original sequence
     * @param int $item The item to remove
     * @return string The new sequence
     */
    public static function sequence_remove(string $sequence, int $item): string {
        if (empty($sequence)) {
            return '';
        }
        $seq = explode(',', $sequence);

        $posold = array_search($item, $seq);

        if ($posold === false) {
            return $sequence;
        }

        if ($posold >= 0) {
            unset($seq[$posold]);
        }

        return join(',', $seq);
    }

    /**
     * Moves an item inside a string sequence of integer values, divided by commas.
     *
     * @param string $sequence The original sequence
     * @param int $afteritem The item to move after
     * @param int $item The item to move
     * @return string The new sequence
     */
    public static function sequence_move_after(string $sequence, int $afteritem, int $item): string {
        if ($afteritem == $item) {
            return $sequence;
        }
        $seq = self::sequence_remove($sequence, $item);
        return self::sequence_add_after($seq, $afteritem, $item);
    }

    /**
     * Replaces items in a string sequence of integer values, divided by commas.
     *
     * @param string $sequence The original sequence
     * @param array $replace An array of $key => $value replacing rules ($key is replaced by $value or $value->id if $value is an
     *                      object)
     * @return string The new sequence
     */
    public static function sequence_replace(string $sequence, array $replace) {
        if (empty($sequence)) {
            return '';
        }
        $seq = explode(',', $sequence);

        $newseq = [];

        foreach ($seq as $value) {
            if (!array_key_exists($value, $replace)) {
                $newseq[] = $value;
                continue;
            }
            if (is_object($replace[$value])) {
                $newseq[] = $replace[$value]->id;
            } else {
                $newseq[] = $replace[$value];
            }
        }

        return join(',', $newseq);
    }

    /**
     * This function checks permissions if a board is a user or a group board.
     *
     * @param object $board The record from the board table
     * @param \context $context The context of the course module
     * @param \cm_info $cminfo The course module info
     * @param int $type Type of permission to check: constants::MOD_KANBAN_EDIT(default) or constants::MOD_KANBAN_VIEW
     */
    public static function check_permissions_for_user_or_group(
        object $board,
        \context $context,
        \cm_info $cminfo,
        int $type = constants::MOD_KANBAN_EDIT
    ): void {
        global $USER;
        if (!empty($board->template)) {
            require_capability('mod/kanban:manageboard', $context);
        }
        if (!(empty($board->userid) && empty($board->groupid))) {
            if (!empty($board->userid) && $board->userid != $USER->id) {
                require_capability(constants::MOD_KANBAN_CAPABILITY[$type], $context);
            }
            if (!empty($board->groupid)) {
                $members = groups_get_members($board->groupid, 'u.id');
                $members = array_map(function ($v) {
                    return intval($v->id);
                }, $members);
                $ismember = in_array($USER->id, $members);
                $groupmode = groups_get_activity_groupmode($cminfo, $cminfo->course);
                if ($groupmode == SEPARATEGROUPS && !$ismember) {
                    require_capability(constants::MOD_KANBAN_CAPABILITY[$type], $context);
                }
                if ($groupmode == VISIBLEGROUPS && !$ismember && $type == constants::MOD_KANBAN_EDIT) {
                    require_capability(constants::MOD_KANBAN_CAPABILITY[$type], $context);
                }
            }
        }
    }

    /**
     * Get filename and url of all attachments to a card.
     *
     * @param int $contextid Context id of the board
     * @param int $cardid Id of the card
     * @return array
     */
    public static function get_attachments(int $contextid, int $cardid): array {
        $fs = get_file_storage();
        $attachments = $fs->get_area_files($contextid, 'mod_kanban', 'attachments', $cardid, 'filename', false);

        $attachmentslist = [];
        foreach ($attachments as $attachment) {
            $attachmentslist[] = [
                'url' => \moodle_url::make_pluginfile_url(
                    $contextid,
                    'mod_kanban',
                    'attachments',
                    $cardid,
                    $attachment->get_filepath(),
                    $attachment->get_filename()
                )->out(),
                'name' => $attachment->get_filename(),
            ];
        }

        return $attachmentslist;
    }

    /**
     * Send a notification to a user.
     *
     * @param cm_info $cm The affected course module
     * @param string $messagename The name of the message defined in message.php
     * @param array $users The users to send the notification to
     * @param object $data The data to describe the message details
     * @param string|null $altmessagename The name of an alternative message string to be used
     * @param bool $tocurrentuser Whether to send notifications also to current user
     */
    public static function send_notification(
        \cm_info $cm,
        string $messagename,
        array $users,
        object $data,
        ?string $altmessagename = null,
        bool $tocurrentuser = false
    ) {
        global $OUTPUT, $USER;
        $message = new \core\message\message();
        $message->component = 'mod_kanban';
        $message->name = $messagename;
        if (!empty($altmessagename)) {
            $messagename = $altmessagename;
        }
        $message->userfrom = \core_user::get_noreply_user();
        $message->fullmessageformat = FORMAT_MARKDOWN;
        $templatename = 'mod_kanban/message_' . $messagename;

        $message->notification = 1;
        $url = $cm->get_url();
        if (!empty($data->boardid)) {
            $url->param('boardid', $data->boardid);
        }
        $message->contexturl = $url->out(false);

        foreach ($users as $user) {
            $user = \core_user::get_user($user);
            self::fix_current_language($user->lang);
            $message->subject = get_string('message_' . $messagename . '_smallmessage', 'mod_kanban', $data);
            $message->fullmessage = get_string('message_' . $messagename . '_fullmessage', 'mod_kanban', $data);
            $message->smallmessage = $message->subject;
            $message->contexturlname = get_string('toboard', 'mod_kanban', $data);
            if (file_exists(__DIR__ . '/../templates/' . $templatename)) {
                $message->fullmessagehtml = $OUTPUT->render_from_template($templatename, $data);
            }

            // Don't notify current user about own actions.
            if ($user->id != $USER->id || $tocurrentuser) {
                $message->userto = $user;
                message_send($message);
            }
        }
    }

    /**
     * Adds or updates a calendar event.
     *
     * @param stdClass $kanban The kanban record from the database
     * @param stdClass $card The card record from the database
     * @param array $users The usersthat should have the event in their calendar
     */
    public static function add_or_update_calendar_event(stdClass $kanban, stdClass $card, array $users) {
        global $CFG, $DB;

        if (empty($card->duedate)) {
            return;
        }

        require_once($CFG->dirroot . '/calendar/lib.php');
        $data = new stdClass();
        $data->eventtype = 'due';
        $data->type = CALENDAR_EVENT_TYPE_ACTION;
        $data->name = get_string('message_due_smallmessage', 'mod_kanban', $card);
        $data->description = $card->description;
        $data->format = $card->descriptionformat;
        $data->groupid = 0;
        $data->userid = 0;
        $data->modulename = 'kanban';
        $data->instance = $kanban->id;
        $data->timestart = $card->duedate;
        $data->visible = instance_is_visible('kanban', $kanban);
        $data->timeduration = 0;
        $data->uuid = $card->id;
        $data->name = get_string('message_due_smallmessage', 'mod_kanban', $card);
        $data->description = $card->description;
        $data->format = $card->descriptionformat;
        foreach ($users as $user) {
            $data->userid = $user;
            $eventrecord = $DB->get_record('event', ['uuid' => $card->id, 'instance' => $kanban->id, 'userid' => $user]);
            if (!$eventrecord) {
                calendar_event::create($data, false);
            } else {
                $data->id = $eventrecord->id;
                $DB->update_record('event', $data);
                unset($data->id);
            }
        }
    }

    /**
     * Removes a calendar event.
     *
     * @param stdClass $kanban The kanban record from the database
     * @param stdClass $card The card record from the database
     * @param array $users The users that should have the event deleted from their calendar. If empty, all events of this
     *                      card are deleted.
     */
    public static function remove_calendar_event(stdClass $kanban, stdClass $card, array $users = []) {
        global $DB;
        if (!empty($users)) {
            [$sql, $params] = $DB->get_in_or_equal($users, SQL_PARAMS_NAMED);
            $sql = 'instance = :id AND uuid = :cardid AND userid ' . $sql;
            $params['cardid'] = $card->id;
            $params['id'] = $kanban->id;
            $DB->delete_records_select('event', $sql, $params);
        } else {
            $DB->delete_records('event', ['modulename' => 'kanban', 'instance' => $kanban->id]);
        }
    }

    /**
     * Get the board record from cache (if it is not present in the cache, get it from db).
     *
     * @param int $id Id of the board
     * @return stdClass board record
     */
    public static function get_cached_board(int $id): stdClass {
        $cachekey = self::get_cache_key(constants::MOD_KANBAN_BOARD, $id);
        $cache = \cache::make('mod_kanban', constants::MOD_KANBAN_TYPES[constants::MOD_KANBAN_BOARD]);
        $board = $cache->get($cachekey);
        if (!$board) {
            $board = self::update_cached_board($id);
        } else {
            $board = unserialize($board);
        }
        return $board;
    }

    /**
     * Update the board record in cache from db.
     *
     * @param int $id Id of the board
     * @return stdClass The updated board record
     */
    public static function update_cached_board(int $id): stdClass {
        global $DB;
        $cachekey = self::get_cache_key(constants::MOD_KANBAN_BOARD, $id);
        $cache = \cache::make('mod_kanban', constants::MOD_KANBAN_TYPES[constants::MOD_KANBAN_BOARD]);
        $board = $DB->get_record('kanban_board', ['id' => $id]);
        $cache->set($cachekey, serialize($board));
        return $board;
    }

    /**
     * Remove board record from cache.
     *
     * @param int $id Id of the board
     * @return void
     */
    public static function invalidate_cached_board(int $id): void {
        global $DB;
        $cachekey = self::get_cache_key(constants::MOD_KANBAN_BOARD, $id);
        $cache = \cache::make('mod_kanban', constants::MOD_KANBAN_TYPES[constants::MOD_KANBAN_BOARD]);
        $cache->delete($cachekey);
    }

    /**
     * Get the latest timestamp from cache (if it is not present in the cache, get it from db).
     *
     * @param int $boardid Id of the board
     * @param int $type One of constants::MOD_KANBAN_COLUMN, constants::MOD_KANBAN_CARD
     * @return int timestamp
     */
    public static function get_cached_timestamp(int $boardid, int $type): int {
        $cachekey = self::get_cache_key($type, $boardid);
        $cache = self::get_timestamp_cache();
        $timestamp = $cache->get($cachekey);
        if (!$timestamp) {
            $timestamp = self::update_cached_timestamp($boardid, $type);
        }
        return $timestamp;
    }

    /**
     * Update the timestamp in cache from db or from parameter.
     *
     * @param int $boardid Id of the board
     * @param int $type One of constants::MOD_KANBAN_COLUMN, constants::MOD_KANBAN_CARD
     * @param int $timestamp Timestamp to set
     * @return int updated timestamp
     */
    public static function update_cached_timestamp(int $boardid, int $type, int $timestamp = 0): int {
        global $DB;
        $cachekey = self::get_cache_key($type, $boardid);
        $cache = self::get_timestamp_cache();
        $timestamp = $DB->get_field_sql(
            'SELECT MAX(timemodified)
             FROM {kanban_' . constants::MOD_KANBAN_TYPES[$type] . '}
             WHERE kanban_board = :id',
            ['id' => $boardid]
        );
        if (is_null($timestamp)) {
            // There might be no records yet.
            $timestamp = 0;
        }
        $cache->set($cachekey, $timestamp);
        return $timestamp;
    }

    /**
     * Returns the cache key for getting a timestamp from cache
     *
     * @param int $type One of constants::MOD_KANBAN_COLUMN, constants::MOD_KANBAN_CARD
     * @param int $boardid Id of the board
     * @return string
     */
    public static function get_cache_key(int $type, int $boardid): string {
        return constants::MOD_KANBAN_TYPES[$type] . '-' . $boardid;
    }

    /**
     * Get the timestamp cache.
     *
     * @return cache_application
     */
    public static function get_timestamp_cache(): \cache_application {
        return \cache::make('mod_kanban', 'timestamp');
    }

    /**
     * Set the current language to the given one. If this fails, English will be used.
     * @param string $lang Language
     */
    public static function fix_current_language(string $lang) {
        try {
            fix_current_language($lang);
        } catch (\moodle_exception $exception) {
            fix_current_language('en');
        }
    }

    /**
     * Sanitizes a json string by stripping off all html tags.
     *
     * @param string $jsonstring the json encoded string
     * @return string the same string, but HTML code will have been sanitized
     */
    public static function sanitize_json_string(string $jsonstring): string {
        $json = json_decode($jsonstring, true);
        foreach ($json as $key => $value) {
            unset($json[$key]);
            $key = clean_param(clean_param($key, PARAM_CLEANHTML), PARAM_NOTAGS);
            if (is_bool($value) || is_integer($value)) {
                $json[$key] = $value;
            } else {
                $json[$key] = is_array($value)
                    ? clean_param_array($value, PARAM_CLEANHTML, true)
                    : clean_param($value, PARAM_CLEANHTML);
            }
        }
        return json_encode($json);
    }

    /**
     * Heals the sequence of columns by adding/removing missing columns.
     *
     * @param string $sequence The sequence of columns in the board itself.
     * @param array $columnids
     * @return string New sequence of columns.
     */
    public static function heal_missing_columns(string $sequence, array $columnids): string {
        $sequence = explode(',', $sequence);
        $columnsmissinginsequence = array_diff($columnids, $sequence);
        $columnsindatabase = array_intersect($sequence, $columnids);
        $updatedboardsequence = array_merge($columnsindatabase, $columnsmissinginsequence);
        return implode(',', $updatedboardsequence);
    }
}
