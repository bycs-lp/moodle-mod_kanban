import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import Log from 'core/log';

/**
 * Mutations library for mod_kanban.
 * The functions are just used to forward data to the webservice.
 */
export default class {
    // Attribute for counting update fails.
    updateFails = 0;

    async saveAsTemplate(stateManager) {
        await this._sendChange('save_as_template', stateManager);
    }

    /**
     * Delete a card.
     * @param {*} stateManager StateManager instance
     * @param {number} cardId Id of the card to be deleted
     */
    async deleteCard(stateManager, cardId) {
        await this._sendChange('delete_card', stateManager, {cardid: cardId});
    }

    /**
     * Delete the board.
     * @param {*} stateManager StateManager instance
     */
    async deleteBoard(stateManager) {
        await this._sendChange('delete_board', stateManager);
    }

    /**
     * Add a card after an existing one.
     * @param {*} stateManager StateManager instance
     * @param {number} columnId Id of the column
     * @param {number} afterCard Id of the card before (0 means to insert at the top of the column)
     */
    async addCard(stateManager, columnId, afterCard) {
        await this._sendChange('add_card', stateManager, {columnid: columnId, aftercard: afterCard});
    }

    /**
     * Move a card to another column.
     * @param {*} stateManager StateManager instance
     * @param {number} cardId Id of the card to be deleted
     * @param {number} columnId Id of the new column
     * @param {number} afterCard Id of the card before (0 means to move at the top of the column)
     */
    async moveCard(stateManager, cardId, columnId, afterCard) {
        await this._sendChange('move_card', stateManager, {cardid: cardId, columnid: columnId, aftercard: afterCard});
    }

    /**
     * Deletes a column and all cards within.
     * @param {*} stateManager StateManager instance
     * @param {number} columnId Id of the column to delete
     */
    async deleteColumn(stateManager, columnId) {
        await this._sendChange('delete_column', stateManager, {columnid: columnId});
    }

    /**
     * Adds a new column.
     * @param {*} stateManager StateManager instance
     * @param {number} afterColumn Id of the column before (0 means to insert at the left of the board)
     */
    async addColumn(stateManager, afterColumn) {
        await this._sendChange('add_column', stateManager, {aftercol: afterColumn});
    }

    /**
     * Moves a column to a new place.
     * @param {*} stateManager StateManager instance
     * @param {number} columnId Id of the column to move
     * @param {number} afterColumn Id of the column before (0 means to insert at the left of the board)
     */
    async moveColumn(stateManager, columnId, afterColumn) {
        await this._sendChange('move_column', stateManager, {columnid: columnId, aftercol: afterColumn});
    }

    /**
     * Assign a user to a card.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card
     * @param {number} userId Id of the user to assign (0 means to assign the current user)
     */
    async assignUser(stateManager, cardId, userId = 0) {
        await this._sendChange('assign_user', stateManager, {cardid: cardId, userid: userId});
    }

    /**
     * Mark a card as completed.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card
     */
    async completeCard(stateManager, cardId) {
        await this._sendChange('set_card_complete', stateManager, {cardid: cardId, state: 1});
    }

    /**
     * Mark a card as not completed.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card
     */
    async uncompleteCard(stateManager, cardId) {
        await this._sendChange('set_card_complete', stateManager, {cardid: cardId, state: 0});
    }

    /**
     * Remove assignment for a user to a card.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card
     * @param {number} userId Id of the user to unassign, defaults to 0 (current user)
     */
    async unassignUser(stateManager, cardId, userId = 0) {
        await this._sendChange('unassign_user', stateManager, {cardid: cardId, userid: userId});
    }

    /**
     * Locks a column.
     * @param {*} stateManager StateManager instance
     * @param {number} columnId Id of the column to lock
     */
    async lockColumn(stateManager, columnId) {
        await this._sendChange('set_column_locked', stateManager, {columnid: columnId, state: 1});
    }

    /**
     * Unlocks a column.
     * @param {*} stateManager StateManager instance
     * @param {number} columnId Id of the column to unlock
     */
    async unlockColumn(stateManager, columnId) {
        await this._sendChange('set_column_locked', stateManager, {columnid: columnId, state: 0});
    }

    /**
     * Locks all columns of the board.
     * @param {*} stateManager StateManager instance
     */
    async lockColumns(stateManager) {
        await this._sendChange('set_board_columns_locked', stateManager, {state: 1});
    }

    /**
     * Unlocks all columns of the board.
     * @param {*} stateManager StateManager instance
     */
    async unlockColumns(stateManager) {
        await this._sendChange('set_board_columns_locked', stateManager, {state: 0});
    }

    /**
     * Adds a message to discussion.
     * @param {*} stateManager
     * @param {*} cardId
     * @param {*} message
     */
    async sendDiscussionMessage(stateManager, cardId, message) {
        await this._sendChange('add_discussion_message', stateManager, {cardid: cardId, message: message});
    }

    /**
     * Delete a message from a discussion.
     * @param {*} stateManager StateManager instance
     * @param {number} messageId Id of the message to be deleted
     */
    async deleteMessage(stateManager, messageId) {
        await this._sendChange('delete_discussion_message', stateManager, {messageid: messageId});
    }

    /**
     * Push a copy of a card to all boards.
     * @param {*} stateManager StateManager instance
     * @param {number} cardId Id of the card to be pushed
     */
    async pushCard(stateManager, cardId) {
        await this._sendChange('push_card_copy', stateManager, {cardid: cardId});
    }

    /**
     * Send change request to webservice
     * @param {string} method Name of the method
     * @param {*} stateManager StateManager instance
     * @param {object} data Data to send
     */
    async _sendChange(method, stateManager, data) {
        const state = stateManager.state;
        const request = {
            methodname: 'mod_kanban_' + method,
            args: {
                cmid: state.common.id,
                boardid: state.board.id,
                data: data
            },
            fail: this.displayError,
        };

        let result = null;
        try {
            result = await Ajax.call([request])[0];
        } catch (e) {
            if (!(e instanceof Object)) {
                // There is a problem with requesting the webservice if we do not obtain a correct object.
                // This for example could be due to a connection loss. We therefore need to catch this error separately.
                Log.warn('Sending a change request to the kanban backend failed, probably due to connection loss.');
                this.processUpdateFail(stateManager);
            }
            return;
        }

        this.processUpdates(stateManager, result);
    }

    /**
     * Update state.
     * @param {*} stateManager
     */
    async getUpdates(stateManager) {
        const state = stateManager.state;
        if (state.board === undefined) {
            stateManager.setReadOnly(false);
            stateManager.eventsToPublish.push({
                eventName: `board:deleted`,
                eventData: {},
                action: `deleted`,
            });
            stateManager.setReadOnly(true);
        } else {
            let result = null;
            try {
                result = await Ajax.call([{
                    methodname: 'mod_kanban_get_kanban_content_update',
                    args: {
                        cmid: state.common.id,
                        boardid: state.board.id,
                        timestamp: state.common.timestamp,
                    },
                    fail: () => {
                        this.processUpdateFail(stateManager);
                    },
                }])[0];
            } catch (e) {
                if (!(e instanceof Object)) {
                    // There is a problem with requesting the webservice if we do not obtain a correct object.
                    // This for example could be due to a connection loss. We therefore need to catch this error separately.
                    Log.warn('Sending a change request to the kanban backend failed, probably due to connection loss.');
                    this.processUpdateFail(stateManager);
                }
                return;
            }

            this.processUpdates(stateManager, result);
        }
    }

    /**
     * Count update fails.
     * @param {*} stateManager
     */
    processUpdateFail(stateManager) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.common.updatefails++;
        stateManager.setReadOnly(true);
    }

    /**
     * Show a modal to display an error message
     * @param {*} data
     */
    async displayError(data) {
        if (data.message) {
            // Can switch to direct call of getString when dropping support for Moodle 4.1.
            await Notification.alert(getString('error'), data.message, getString('cancel'));
        }
    }

    /**
     * Update discussions for a card.
     * @param {*} stateManager
     * @param {number} cardId
     */
    async getDiscussionUpdates(stateManager, cardId) {
        const state = stateManager.state;
        let timestamp = 0;
        state.discussions.forEach((c) => {
            if (c.kanban_card == cardId) {
                if (c.timestamp > timestamp) {
                    timestamp = c.timestamp;
                }
            }
        });

        const result = await Ajax.call([{
            methodname: 'mod_kanban_get_discussion_update',
            args: {
                cmid: state.common.id,
                boardid: state.board.id,
                cardid: cardId,
                timestamp: timestamp,
            },
            fail: () => {
                this.processUpdateFail(stateManager);
            },
        }])[0];

        this.processUpdates(stateManager, result);
    }

    /**
     * Update history for a card.
     * @param {*} stateManager
     * @param {number} cardId
     */
    async getHistoryUpdates(stateManager, cardId) {
        const state = stateManager.state;
        let timestamp = 0;
        state.history.forEach((c) => {
            if (c.kanban_card == cardId) {
                if (c.timestamp > timestamp) {
                    timestamp = c.timestamp;
                }
            }
        });

        const result = await Ajax.call([{
            methodname: 'mod_kanban_get_history_update',
            args: {
                cmid: state.common.id,
                boardid: state.board.id,
                cardid: cardId,
                timestamp: timestamp,
            },
            fail: () => {
                this.processUpdateFail(stateManager);
            },
        }])[0];

        this.processUpdates(stateManager, result);
    }

    /**
     * Process updates.
     *
     * @param {*} stateManager
     * @param {*} result
     */
    async processUpdates(stateManager, result) {
        let updates = JSON.parse(result.update);
        stateManager.processUpdates(updates);
    }

    /**
     * Duplicate a card.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card to duplicate.
     */
    async duplicateCard(stateManager, cardId) {
        await this._sendChange('duplicate_card', stateManager, {cardid: cardId});
    }

    /**
     * Set a card as highlighted.
     * @param {*} stateManager StateManager instance.
     * @param {number} cardId Id of the card to highlight.
     * @param {boolean} value Whether to highlight or unhighlight the card (defaults to true).
     */
    setHighlightedCard(stateManager, cardId, value = true) {
        const state = stateManager.state;
        stateManager.setReadOnly(false);
        state.cards.get(cardId).highlighted = value;
        stateManager.setReadOnly(true);
    }
}
