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
 * Exporter for use in mustache template.
 * @module mod_kanban/exporter
 * @copyright 2026 ISB Bayern
 * @author Stefan Hanauska stefan.hanauska@csg-in.de
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import capabilities from 'mod_kanban/capabilities';

/**
 * Exporter for use in mustache template.
 */
export default class {
    /**
     * Exports the complete state (for initial rendering).
     * @param {*} state
     * @returns {object}
     */
    static exportStateForTemplate(state) {
        let columnOrder = state.board.sequence.split(',');
        let columns = [];
        let hascolumns = state.board.sequence != '';
        if (hascolumns) {
            columns = columnOrder.map((value) => {
                return this.exportCardsForColumn(state, value);
            });
            columns = columns.filter((value) => {
                return value.id !== undefined;
            });
        }

        let showactionmenu = state.common.userboards == 1 || state.common.groupselector != '' ||
            state.capabilities.get(capabilities.MANAGEBOARD).value ||
            (state.common.userboards == 2 && state.capabilities.get(capabilities.VIEWALLBOARDS).value);

        return Object.assign({
            cmid: state.common.id,
            id: state.board.id,
            sequence: state.board.sequence,
            hascolumns: hascolumns,
            columns: columns,
            locked: state.board.locked,
            hastemplate: state.common.template != 0,
            istemplate: state.board.template != 0,
            heading: state.board.heading,
            groupselector: state.common.groupselector,
            userboards: state.common.userboards,
            history: state.common.history && state.capabilities.get(capabilities.VIEWHISTORY).value,
            groupmode: state.common.groupmode,
            ismyuserboard: state.common.userid == state.board.userid,
            myuserid: state.common.userid,
            showactionmenu: showactionmenu,
            userboardsonly: state.common.userboards == 2,
            iscourseboard: state.board.userid == 0 && state.board.groupid == 0 && state.board.template == 0,
            users: JSON.parse(JSON.stringify(state.users)),
            usenumbers: state.common.usenumbers,
            hasaddcards: state.board.hasaddcards ?? false,
            showauthors: state.board.showauthors ?? false,
        }, this.exportCapabilities(state));
    }

    /**
     * Exports the card for one column.
     * @param {*} state
     * @param {*} columnid
     * @returns {object}
     */
    static exportCardsForColumn(state, columnid) {
        let column = state.columns.get(columnid);
        // This handles a column that is not present in the state.
        if (column === undefined) {
            return {};
        }
        let col = JSON.parse(JSON.stringify(column));
        let options = JSON.parse(col.options);
        col.hascards = col.sequence != '';
        col.autoclose = options.autoclose;
        col.autohide = options.autohide;
        col.addcardshere = options.addcardshere ?? false;
        if (options.wiplimit > 0) {
            col.wiplimit = options.wiplimit;
        }
        if (options.collimit > 0) {
            col.collimit = options.collimit;
        }
        col.cardcount = 0;
        if (col.hascards) {
            let cardOrder = col.sequence.split(',');
            col.cards = cardOrder.map((value) => {
                return this.exportCard(state, value);
            });
            col.cardcount = cardOrder.length;
        }
        col.limitwarning = col.collimit > 0 && col.cardcount >= col.collimit;
        return col;
    }

    /**
     * Exports a card.
     * @param {*} state
     * @param {*} cardid
     * @returns {object}
     */
    static exportCard(state, cardid) {
        let card = {
            id: cardid,
            title: '-',
            assignees: [],
            options: '{}',
            canedit: false,
            number: 0,
        };
        if (state.cards.get(cardid) !== undefined) {
            card = JSON.parse(JSON.stringify(state.cards.get(cardid)));
        }
        card.cardid = card.id;
        card.hasassignees = card.assignees.length > 0;
        card.createdbyname = card.createdbyname ?? '';
        let options = JSON.parse(card.options);
        if (card.hasassignees) {
            card.assignees = card.assignees.map((userid) => {
                return state.users.get(userid);
            });
            card.assignees = [...new Set(card.assignees)];
        }
        return Object.assign(card, options);
    }

    /**
     * Exports the capabilities.
     * @param {*} state
     * @returns {object}
     */
    static exportCapabilities(state) {
        let capabilities = [];
        state.capabilities.forEach((c) => {
            capabilities[c.id] = c.value;
        });
        return Object.assign({}, capabilities);
    }

    /**
     * Exports the discussion for a card.
     * @param {*} state
     * @param {number} cardId
     * @returns {array}
     */
    static exportDiscussion(state, cardId) {
        let d = [];
        state.discussions.forEach((c) => {
            if (c.kanban_card == cardId) {
                d.push(c);
            }
        });
        d = d.sort((a, b) => parseInt(a.timecreated) > parseInt(b.timecreated));
        return d;
    }

    /**
     * Exports history for a card.
     * @param {*} state
     * @param {number} cardId
     * @returns {array}
     */
    static exportHistory(state, cardId) {
        let d = [];
        // Only get history of this card.
        state.history.forEach((c) => {
            if (c.kanban_card == cardId) {
                d.push(c);
            }
        });
        // Sort by timestamp.
        d = d.sort((a, b) => parseInt(a.timestamp) > parseInt(b.timestamp));
        return d;
    }
}
