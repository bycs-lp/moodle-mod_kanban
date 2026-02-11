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
 *  Component representing a card in a kanban board.
 *
 * @module     mod_kanban/card
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {DragDrop} from 'core/reactive';
import selectors from 'mod_kanban/selectors';
import exporter from 'mod_kanban/exporter';
import {exception as displayException, saveCancel} from 'core/notification';
import ModalForm from 'core_form/modalform';
import Modal from 'core/modal';
import * as Str from 'core/str';
import {get_string as getString} from 'core/str';
import Templates from 'core/templates';
import KanbanComponent from 'mod_kanban/kanbancomponent';
import Log from 'core/log';

/**
 * Component representing a card in a kanban board.
 */
export default class extends KanbanComponent {
    /**
     * For relative time helper.
     */
    _units = {
        year: 24 * 60 * 60 * 1000 * 365,
        month: 24 * 60 * 60 * 1000 * 365 / 12,
        day: 24 * 60 * 60 * 1000,
        hour: 60 * 60 * 1000,
        minute: 60 * 1000,
        second: 1000
    };

    /**
     * Function to initialize component, called by mustache template.
     * @param {*} target The id of the HTMLElement to attach to
     * @returns {BaseComponent} New component attached to the HTMLElement represented by target
     */
    static init(target) {
        let element = document.getElementById(target);
        return new this({
            element: element,
        });
    }

    /**
     * Called after the component was created.
     */
    create() {
        this.id = this.element.dataset.id;
    }

    /**
     * Watchers for this component.
     * @returns {array} All watchers for this component
     */
    getWatchers() {
        return [
            {watch: `cards[${this.id}]:updated`, handler: this._cardUpdated},
            {watch: `cards[${this.id}]:deleted`, handler: this._cardDeleted},
            {watch: `filters:updated`, handler: this._applyFilters},
            {watch: `filters:deleted`, handler: this._applyFilters},
            {watch: `discussions:created`, handler: this._discussionUpdated},
            {watch: `discussions:updated`, handler: this._discussionUpdated},
            {watch: `discussions:deleted`, handler: this._discussionUpdated},
            {watch: `history:created`, handler: this._historyUpdated},
            {watch: `history:updated`, handler: this._historyUpdated},
            {watch: `history:deleted`, handler: this._historyUpdated},
        ];
    }

    /**
     * Called once when state is ready (also if component is registered after initial state was set), attaching event
     * listeners and initializing drag and drop.
     * @param {*} state The initial state
     */
    stateReady(state) {
        // Get language for relative time formatting.
        let lang = 'en';
        if (state.common.lang !== undefined) {
            lang = state.common.lang;
        }
        // The property state.common.lang contains the locale extracted from the currently used moodle language pack.
        // This should be a real locale and thus suitable for RelativeTimeFormat, for edge cases however we are
        // using a fallback locale here.
        try {
            this.rtf = new Intl.RelativeTimeFormat(lang, {numeric: 'auto'});
        } catch (e) {
            // Fallback if there is no valid lang found.
            this.rtf = new Intl.RelativeTimeFormat('en', {numeric: 'auto'});
        }

        this.addEventListener(
            this.getElement(selectors.DELETECARD, this.id),
            'click',
            this._removeConfirm
        );
        this.addEventListener(
            this.getElement(selectors.ADDCARD, this.id),
            'click',
            this._addCard
        );
        this.addEventListener(
            this.getElement(selectors.COMPLETE, this.id),
            'click',
            this._completeCard
        );
        this.addEventListener(
            this.getElement(selectors.UNCOMPLETE, this.id),
            'click',
            this._uncompleteCard
        );
        this.addEventListener(
            this.getElement(selectors.ASSIGNSELF, this.id),
            'click',
            this._assignSelf
        );
        this.addEventListener(
            this.getElement(selectors.UNASSIGNSELF, this.id),
            'click',
            this._unassignSelf
        );
        this.addEventListener(
            this.getElement(selectors.EDITDETAILS, this.id),
            'click',
            this._editDetails
        );
        this.addEventListener(
            this.getElement(selectors.DISCUSSIONMODALTRIGGER),
            'click',
            this._showDiscussionModal
        );
        this.addEventListener(
            this.getElement(selectors.DISCUSSIONSHOW, this.id),
            'click',
            this._showDiscussionModal
        );
        this.addEventListener(
            this.getElement(selectors.HISTORYMODALTRIGGER),
            'click',
            this._showHistoryModal
        );
        this.addEventListener(
            this.getElement(selectors.MOVEMODALTRIGGER),
            'click',
            this._showMoveModal
        );
        this.addEventListener(
            this.getElement(selectors.PUSHCARD),
            'click',
            this._pushCardConfirm
        );
        this.addEventListener(
            this.getElement(selectors.DUPLICATE),
            'click',
            this._duplicateCard
        );
        this.addEventListener(
            this.getElement(selectors.DETAILBUTTON),
            'click',
            this._showDetailsModal
        );

        this.draggable = false;
        this.dragdrop = new DragDrop(this);
        this.checkEditing(state);
        this.boardid = state.board.id;
        this.cmid = state.common.id;
        this.userid = state.board.userid;
        this.groupid = state.board.groupid;
        this._dueDateFormat();
        if (state.cards.get(this.id).highlighted) {
            this._highlightCard();
        }
    }

    /**
     * Show modal to move a column.
     */
    _showMoveModal() {
        let data = exporter.exportStateForTemplate(this.reactive.state);
        data.cardid = this.id;
        data.kanbancolumn = this.reactive.state.cards.get(this.id).kanban_column;
        Str.get_strings([
            {key: 'movecard', component: 'mod_kanban'},
            {key: 'move', component: 'core'},
        ]).then((strings) => {
            return saveCancel(
                strings[0],
                Templates.render('mod_kanban/movemodal', data),
                strings[1],
                () => {
                    let column = document.querySelector(selectors.MOVECARDCOLUMN + `[data-id="${this.id}"]`).value;
                    let aftercard = document.querySelector(selectors.MOVECARDAFTERCARD + `[data-id="${this.id}"]`).value;
                    this.reactive.dispatch('moveCard', this.id, column, aftercard);
                }
            );
        }).catch((error) => Log.debug(error));
    }

    /**
     * Show modal with card details.
     * @param {*} event Click event.
     */
    async _showDetailsModal(event) {
        let id = this.id;
        if (event.target.dataset.id !== undefined) {
            id = event.target.dataset.id;
        }

        let data = exporter.exportCard(this.reactive.state, id);
        let title = this.reactive.state.common.usenumbers ? '#' + data.number + ' ' + data.title : data.title;

        let body = await Templates.renderForPromise('mod_kanban/descriptionmodal', data);

        const modal = await Modal.create({
            body: body.html,
            bodyJS: body.js,
            title: title,
            removeOnClose: true,
            show: true,
        });
        modal.modal[0].dataset.id = id;
    }

    /**
     * Simulate click on details button.
     * @param {*} event Click event.
     */
    _clickDetailsButton(event) {
        const card = document.querySelector(
            selectors.CARD + `[data-number="${event.target.dataset.id}"]` + ' ' + selectors.DETAILBUTTON
        );
        if (card) {
            card.click();
        }
    }

    /**
     * Display confirmation modal for pushing a card.
     * @param {*} event
     */
    _pushCardConfirm(event) {
        Str.get_strings([
            {key: 'pushcard', component: 'mod_kanban'},
            {key: 'pushcardconfirm', component: 'mod_kanban'},
            {key: 'copy', component: 'core'},
        ]).then((strings) => {
            return saveCancel(
                strings[0],
                strings[1],
                strings[2],
                () => {
                    this._pushCard(event);
                }
            );
        }).catch((error) => Log.debug(error));
    }

    /**
     * Display confirmation modal for deleting a card.
     * @param {*} event
     */
    _removeConfirm(event) {
        Str.get_strings([
            {key: 'deletecard', component: 'mod_kanban'},
            {key: 'deletecardconfirm', component: 'mod_kanban'},
            {key: 'delete', component: 'core'},
        ]).then((strings) => {
            return saveCancel(
                strings[0],
                strings[1],
                strings[2],
                () => {
                    this._removeCard(event);
                }
            );
        }).catch((error) => Log.debug(error));
    }

    /**
     * Show the discussion modal.
     * @param {*} event Click event.
     */
    async _showDiscussionModal(event) {
        let id = this.id;
        if (event.target.dataset.id !== undefined) {
            id = event.target.dataset.id;
        }

        let data = exporter.exportCard(this.reactive.state, id);
        let title = this.reactive.state.common.usenumbers ? '#' + data.number + ' ' + data.title : data.title;

        let body = await Templates.renderForPromise('mod_kanban/discussionmodalbody', data);
        let footer = await Templates.renderForPromise('mod_kanban/discussionmodalfooter', data);

        const modal = await Modal.create({
            body: body.html,
            title: title,
            footer: footer.html,
            removeOnClose: true,
            show: true,
        });
        modal.modal[0].dataset.id = id;
        modal.modal[0].classList.add('mod_kanban_discussion_modal');
        modal.modal[0].classList.add('mod_kanban_loading');
        modal.modal[0].addEventListener('click', (event) => {
            if (event.target.closest(selectors.DISCUSSIONSEND)) {
                this._sendMessage(event);
            }
            if (event.target.closest(selectors.DELETEMESSAGE)) {
                this._removeMessageConfirm(event);
            }
            if (event.target.closest(selectors.CARDNUMBER)) {
                this._clickDetailsButton(event);
            }
        });
        this._renderDiscussionMessages();
        this.reactive.dispatch('getDiscussionUpdates', this.id);
    }

    /**
     * Display confirmation modal for deleting a discussion message.
     * @param {*} event
     */
    _removeMessageConfirm(event) {
        Str.get_strings([
            {key: 'deletemessage', component: 'mod_kanban'},
            {key: 'deletemessageconfirm', component: 'mod_kanban'},
            {key: 'delete', component: 'core'},
        ]).then((strings) => {
            return saveCancel(
                strings[0],
                strings[1],
                strings[2],
                () => {
                    this._removeMessage(event);
                }
            );
        }).catch((error) => Log.debug(error));
    }

    /**
     * Dispatch event to add a message to discussion.
     * @param {*} event
     */
    _sendMessage(event) {
        let el = event.target.closest(selectors.DISCUSSIONMODAL).querySelector(selectors.DISCUSSIONINPUT);
        let message = el.value.trim();
        if (message != '') {
            this.reactive.dispatch('sendDiscussionMessage', this.id, message);
            el.value = '';
        }
    }

    /**
     * Called when discussion was updated.
     * @param {*} event
     */
    async _discussionUpdated(event) {
        if (event.element.kanban_card != this.id) {
            return;
        }
        this._renderDiscussionMessages();
    }

    /**
     * Render the discussion messages in the discussion modal.
     */
    _renderDiscussionMessages() {
        let modal = document.querySelector(selectors.DISCUSSIONMODAL + `[data-id="${this.id}"]`);
        if (modal) {
            let data = {
                discussions: exporter.exportDiscussion(this.reactive.state, this.id)
            };
            Templates.renderForPromise('mod_kanban/discussionmessages', data).then(({html}) => {
                modal.classList.remove('mod_kanban_loading');
                modal.querySelector(selectors.DISCUSSION).innerHTML = html;
                let el = modal.querySelector('.modal-body');
                // Scroll down to latest message.
                el.scrollTop = el.scrollHeight;
                return true;
            }).catch((error) => displayException(error));
        }
    }

    /**
     * Show the history modal for the card.
     * @param {*} event
     */
    async _showHistoryModal(event) {
        let id = this.id;
        if (event.target.dataset.id !== undefined) {
            id = event.target.dataset.id;
        }

        let data = exporter.exportCard(this.reactive.state, id);
        let title = getString(
            'historyfor',
            'mod_kanban',
            this.reactive.state.common.usenumbers ? '#' + data.number + ' ' + data.title : data.title
        );

        let body = await Templates.renderForPromise('mod_kanban/historymodalbody', data);

        const modal = await Modal.create({
            body: body.html,
            title: title,
            removeOnClose: true,
            show: true,
        });
        modal.modal[0].dataset.id = id;
        modal.modal[0].classList.add('mod_kanban_history_modal');
        modal.modal[0].classList.add('mod_kanban_loading');
        this._renderHistoryItems();
        this.reactive.dispatch('getHistoryUpdates', this.id);
    }

    /**
     * Called when history was updated.
     * @param {*} event
     */
    async _historyUpdated(event) {
        if (event.element.kanban_card != this.id) {
            return;
        }
        this._renderHistoryItems();
    }

    /**
     * Render the history items in the history modal.
     */
    _renderHistoryItems() {
        let modal = document.querySelector(selectors.HISTORYMODAL + `[data-id="${this.id}"]`);
        let data = {
            historyitems: exporter.exportHistory(this.reactive.state, this.id)
        };
        Templates.renderForPromise('mod_kanban/historyitems', data).then(({html}) => {
            modal.classList.remove('mod_kanban_loading');
            modal.querySelector(selectors.HISTORY, this.id).innerHTML = html;
            modal.classList.remove('mod_kanban_loading');
            // Scroll down to latest history item.
            let el = modal.querySelector('.modal-body');
            el.scrollTop = el.scrollHeight;
            return true;
        }).catch((error) => displayException(error));
    }

    /**
     * Dispatch event to assign the current user to the card.
     * @param {*} event
     */
    _assignSelf(event) {
        let target = event.target.closest(selectors.ASSIGNSELF);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('assignUser', data.id);
    }

    /**
     * Dispatch event to add a card after this card.
     * @param {*} event
     */
    _addCard(event) {
        document.activeElement.blur();
        let target = event.target.closest(selectors.ADDCARD);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('addCard', data.columnid, data.id);
    }

    /**
     * Highlight this card.
     */
    _highlightCard() {
        this.getElement().scrollIntoView({behavior: 'smooth', block: 'center'});
        this.getElement().classList.add('mod_kanban_highlighted_card');
        this.getElement().focus();
        setTimeout(() => {
            this.getElement().classList.remove('mod_kanban_highlighted_card');
        }, 10000);
    }

    /**
     * Called when card is updated.
     * @param {*} param0
     */
    async _cardUpdated({element}) {
        const card = this.getElement();
        // Card was moved to another column. Move the element to new card (right position is handled by column component).
        if (card.dataset.columnid != element.kanban_column) {
            const col = document.querySelector(selectors.COLUMNINNER + '[data-id="' + element.kanban_column + '"]');
            col.appendChild(card);
            this.getElement(selectors.ADDCARD, this.id).setAttribute('data-columnid', element.kanban_column);
            card.setAttribute('data-columnid', element.kanban_column);
        }
        const assignees = this.getElement(selectors.ASSIGNEES, this.id);
        const assignedUsers = this.getElements(selectors.ASSIGNEDUSER, this.id);
        const userids = [...assignedUsers].map(v => {
            return v.dataset.userid;
        });
        // Update assignees.
        if (element.assignees !== undefined) {
            const additional = element.assignees.filter(x => !userids.includes(x));
            // Remove all elements that represent users that are no longer assigned to this card.
            if (assignedUsers !== null) {
                assignedUsers.forEach(assignedUser => {
                    if (!element.assignees.includes(assignedUser.dataset.userid)) {
                        assignedUser.parentNode.removeChild(assignedUser);
                    }
                });
            }
            this.toggleClass(element.assignees.length == 0, 'mod_kanban_unassigned');
            // Add new assignees.
            if (element.assignees.length > 0) {
                additional.forEach(async user => {
                    let userdata = this.reactive.state.users.get(user);
                    let data = Object.assign({cardid: element.id}, userdata);
                    data = Object.assign(data, exporter.exportCapabilities(this.reactive.state));
                    Templates.renderForPromise('mod_kanban/user', data).then(({html, js}) => {
                        Templates.appendNodeContents(assignees, html, js);
                        return true;
                    }).catch((error) => displayException(error));
                });
            }
        }
        this.toggleClass(element.selfassigned, 'mod_kanban_selfassigned');
        // Set card completion state.
        this.toggleClass(element.completed == 1, 'mod_kanban_closed');
        // Update title (also in modals).
        if (element.title !== undefined) {
            // For Moodle inplace editing title is once needed plain and once with html entities encoded.
            // This avoids double encoding of html entities as the value of "data-value" is exactly what is shown
            // in the input field when clicking on the inplace editable.
            let doc = new DOMParser().parseFromString(element.title, 'text/html');
            const inplaceeditable = this.getElement(selectors.INPLACEEDITABLE);
            inplaceeditable.setAttribute('data-value', doc.documentElement.textContent);
            const inplaceeditableLink = inplaceeditable.querySelector('a');
            if (inplaceeditableLink) {
                inplaceeditableLink.innerHTML = element.title;
            }
        }
        this.toggleClass(element.hasdescription, 'mod_kanban_hasdescription');
        this.toggleClass(element.hasattachment, 'mod_kanban_hasattachment');
        // Update due date.
        if (element.duedate !== undefined) {
            this.getElement(selectors.DUEDATE).setAttribute('data-date', element.duedate);
            this._dueDateFormat();
        }
        this.toggleClass(element.discussion, 'mod_kanban_hasdiscussion');
        // Only option for now is background color.
        if (element.options !== undefined) {
            let options = JSON.parse(element.options);
            if (options.background === undefined) {
                this.getElement().removeAttribute('style');
            } else {
                this.getElement().setAttribute('style', 'background-color: ' + options.background);
            }
        }
        // Enable/disable dragging and inplace editing (e.g. if user is not assigned to the card anymore).
        this.checkEditing();
        if (element.highlighted) {
            this._highlightCard();
        }
        this._applyFilters();
    }

    /**
     * Delete this card.
     */
    _cardDeleted() {
        this.destroy();
    }

    /**
     * Dispatch event to remove this card.
     * @param {*} event
     */
    _removeCard(event) {
        let target = event.target.closest(selectors.DELETECARD);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('deleteCard', data.id);
    }

    /**
     * Dispatch event to push this card.
     * @param {*} event
     */
    _pushCard(event) {
        let target = event.target.closest(selectors.PUSHCARD);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('pushCard', data.id);
    }

    /**
     * Dispatch event to remove this card.
     * @param {*} event
     */
    _removeMessage(event) {
        let target = event.target.closest(selectors.DELETEMESSAGE);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('deleteMessage', data.id);
    }

    /**
     * Dispatch event to complete this card.
     * @param {*} event
     */
    _completeCard(event) {
        let target = event.target.closest(selectors.COMPLETE);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('completeCard', data.id);
    }

    /**
     * Dispatch event to complete this card.
     * @param {*} event
     */
    _uncompleteCard(event) {
        let target = event.target.closest(selectors.UNCOMPLETE);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('uncompleteCard', data.id);
    }

    /**
     * Remove all subcomponents dependencies.
     */
    destroy() {
        if (this.dragdrop !== undefined) {
            this.dragdrop.unregister();
        }
    }

    /**
     * Get the draggable data of this component.
     * @returns {object}
     */
    getDraggableData() {
        return {
            id: this.id,
            type: 'card',
        };
    }

    /**
     * Conditionally enable / disable dragging and inplace editing.
     * @param {*} state
     */
    checkEditing(state) {
        if (state === undefined) {
            state = this.reactive.stateManager.state;
        }
        if (state.cards.get(this.id).canedit) {
            this.draggable = true;
            this.dragdrop.setDraggable(true);
        } else {
            this.draggable = false;
            this.dragdrop.setDraggable(false);
        }
        if (state.cards.get(this.id).completed != 1 && state.cards.get(this.id).canedit) {
            this.getElement(selectors.INPLACEEDITABLE).setAttribute('data-inplaceeditable', '1');
        } else {
            this.getElement(selectors.INPLACEEDITABLE).removeAttribute('data-inplaceeditable');
        }

        this.toggleClass(state.cards.get(this.id).canedit, 'mod_kanban_canedit');
    }

    /**
     * Validate draggable data.
     * @param {object} dropdata
     * @returns {boolean} if the data is valid for this drop-zone.
     */
    validateDropData(dropdata) {
        return dropdata?.type == 'card';
    }

    /**
     * Executed when a valid dropdata is dropped over the drop-zone.
     * @param {object} dropdata
     */
    drop(dropdata) {
        if (dropdata.id != this.id) {
            let newcolumn = this.getElement(selectors.ADDCARD, this.id).dataset.columnid;
            let aftercard = this.id;
            this.reactive.dispatch('moveCard', dropdata.id, newcolumn, aftercard);
        }
    }

    /**
     * Dispatch event to unassign the current user.
     * @param {*} event
     */
    _unassignSelf(event) {
        let target = event.target.closest(selectors.UNASSIGNSELF);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('unassignUser', data.id);
    }

    /**
     * Show modal form to edit card details.
     * @param {*} event
     */
    _editDetails(event) {
        event.preventDefault();

        const modalForm = new ModalForm({
            formClass: "mod_kanban\\form\\edit_card_form",
            args: {
                id: this.id,
                boardid: this.boardid,
                cmid: this.cmid,
                groupid: this.groupid,
                userid: this.userid
            },
            modalConfig: {title: getString('editcard', 'mod_kanban')},
            returnFocus: this.getElement(),
        });
        this.addEventListener(modalForm, modalForm.events.FORM_SUBMITTED, this._updateCard);
        modalForm.show();
    }

    /**
     * Dispatch an event to update card data from the detail modal.
     * @param {*} event
     */
    _updateCard(event) {
        this.reactive.dispatch('processUpdates', event.detail);
    }

    /**
     * Update relative time.
     * @param {int} timestamp
     * @returns {string}
     */
    updateRelativeTime(timestamp) {
        let elapsed = new Date(timestamp) - new Date();
        for (var u in this._units) {
            if (Math.abs(elapsed) > this._units[u] || u == 'second') {
                return this.rtf.format(Math.round(elapsed / this._units[u]), u);
            }
        }
        return '';
    }

    /**
     * Format due date using relative time.
     */
    _dueDateFormat() {
        // Convert timestamp to ms.
        let duedate = this.getElement(selectors.DUEDATE).dataset.date * 1000;
        if (duedate > 0) {
            let element = this.getElement(selectors.DUEDATE);
            element.innerHTML = this.updateRelativeTime(duedate);
            if (duedate < new Date().getTime()) {
                element.classList.add('mod_kanban_overdue');
            } else {
                element.classList.remove('mod_kanban_overdue');
            }
        } else {
            this.getElement(selectors.DUEDATE).innerHTML = '';
        }
    }

    /**
     * Dispatch event to duplicate this card.
     * @param {*} event Click event.
     */
    _duplicateCard(event) {
        let target = event.target.closest(selectors.DUPLICATE);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('duplicateCard', data.id);
    }

    /**
     * Apply current filters to this card, hiding it if it does not match the filter criteria.
     */
    _applyFilters() {
        let matchesFilter = true;
        Object.keys(this.reactive.stateManager.state.filters).forEach(key => {
            const filtervalue = this.reactive.stateManager.state.filters[key];
            const card = this.getElement();
            const title = card.querySelector('.mod_kanban_card_title');
            if (key == 'title') {
                const titletext = title.textContent.trim().toLowerCase();
                if (!titletext.includes(filtervalue.toLowerCase())) {
                    matchesFilter = false;
                }
            }
        });
        if (matchesFilter) {
            this.getElement().classList.remove('d-none');
        } else {
            this.getElement().classList.add('d-none');
        }
    }
}
