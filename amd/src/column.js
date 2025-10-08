import {DragDrop} from 'core/reactive';
import selectors from 'mod_kanban/selectors';
import capabilities from 'mod_kanban/capabilities';
import exporter from 'mod_kanban/exporter';
import {saveCancel} from 'core/notification';
import * as Str from 'core/str';
import {get_string as getString} from 'core/str';
import ModalForm from 'core_form/modalform';
import KanbanComponent from 'mod_kanban/kanbancomponent';
import Log from "core/log";

/**
 * Component representing a column in a kanban board.
 */
export default class extends KanbanComponent {
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
     * @returns {array}
     */
    getWatchers() {
        return [
            {watch: `columns[${this.id}]:updated`, handler: this._columnUpdated},
            {watch: `columns[${this.id}]:deleted`, handler: this._columnDeleted},
            {watch: `cards:created`, handler: this._cardCreated}
        ];
    }

    /**
     * Called once when state is ready, attaching event listeners and initializing drag and drop.
     * @param {object} state
     */
    stateReady(state) {
        this.addEventListener(
            this.getElement(selectors.DELETECOLUMN, this.id),
            'click',
            this._removeConfirm
        );
        this.addEventListener(
            this.getElement(selectors.ADDCARDFIRST),
            'click',
            this._addCard
        );
        this.addEventListener(
            this.getElement(selectors.ADDCOLUMN, this.id),
            'click',
            this._addColumn
        );
        this.addEventListener(
            this.getElement(selectors.LOCKCOLUMN, this.id),
            'click',
            this._lockColumn
        );
        this.addEventListener(
            this.getElement(selectors.UNLOCKCOLUMN, this.id),
            'click',
            this._unlockColumn
        );
        this.addEventListener(
            this.getElement(selectors.EDITDETAILS, this.id),
            'click',
            this._editDetails
        );
        this.addEventListener(
            this.getElement(selectors.SHOWHIDDEN),
            'click',
            this._showHidden
        );
        this.addEventListener(
            this.getElement(selectors.HIDEHIDDEN),
            'click',
            this._hideHidden
        );
        this.draggable = false;
        this.dragdrop = new DragDrop(this);
        this.checkDragging(state);
        this.boardid = state.board.id;
        this.cmid = state.common.id;
    }

    /**
     * Display confirmation modal for deleting a card.
     * @param {*} event
     */
    _removeConfirm(event) {
        Str.get_strings([
            {key: 'deletecolumn', component: 'mod_kanban'},
            {key: 'deletecolumnconfirm', component: 'mod_kanban'},
            {key: 'delete', component: 'core'},
        ]).then((strings) => {
            return saveCancel(
                strings[0],
                strings[1],
                strings[2],
                () => {
                    this._removeColumn(event);
                }
            );
        }).catch((error) => Log.debug(error));
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
     *
     * @returns {Object} the draggable data.
     */
    getDraggableData() {
        return {id: this.id, type: 'column'};
    }

    /**
     * Conditionally enable / disable dragging.
     * @param {*} state
     */
    checkDragging(state) {
        if (state === undefined) {
            state = this.reactive.stateManager.state;
        }

        if (state.capabilities.get(capabilities.MANAGECOLUMNS).value && state.columns.get(this.id).locked == 0) {
            this.dragdrop.setDraggable(true);
        } else {
            this.dragdrop.setDraggable(false);
        }
    }

    /**
     * Validate draggable data. This component accepts cards and columns.
     * @param {object} dropdata
     * @returns {boolean} if the data is valid for this drop-zone.
     */
    validateDropData(dropdata) {
        let type = dropdata?.type;
        return type == 'card' || type == 'column';
    }

    /**
     * Executed when a valid dropdata is dropped over the drop-zone.
     * @param {object} dropdata
     * @param {object} event
     */
    drop(dropdata, event) {
        if (dropdata.type == 'card') {
            let cards = this.getElements(selectors.CARD);
            let aftercard = 0;
            for (let i = 0; i < cards.length; i++) {
                if (cards[i].offsetTop + cards[i].clientHeight / 2 <= event.layerY) {
                    aftercard = cards[i].dataset.id;
                }
            }
            this.reactive.dispatch('moveCard', dropdata.id, this.id, aftercard);
        }
        if (dropdata.type == 'column') {
            if (dropdata.id != this.id) {
                this.reactive.dispatch('moveColumn', dropdata.id, this.id);
            }
        }
    }

    /**
     * Show some visual hints to the user.
     * @param {object} dropdata
     * @param {object} event
     */
    showDropZone(dropdata, event) {
        if (dropdata.type == 'card') {
            let cards = this.getElements(selectors.CARD);
            let aftercard = 0;
            for (let i = 0; i < cards.length; i++) {
                if (cards[i].offsetTop + cards[i].clientHeight / 2 <= event.layerY) {
                    aftercard = cards[i].dataset.id;
                }
            }
            if (aftercard == 0) {
                this.getElement(selectors.ADDCARDCONTAINER).classList.add('mod_kanban_insert');
            } else {
                this.getElement(selectors.ADDCARDCONTAINER, aftercard).classList.add('mod_kanban_insert');
            }
        }
        if (dropdata.type == 'column') {
            this.getElement(selectors.ADDCOLUMNCONTAINER).classList.add('mod_kanban_insert');
        }
    }

    /**
     * Remove visual hints to the user.
     */
    hideDropZone() {
        this.getElement(selectors.ADDCOLUMNCONTAINER).classList.remove('mod_kanban_insert');
        this.getElements(selectors.ADDCARDCONTAINER).forEach((e) => {
            e.classList.remove('mod_kanban_insert');
        });
    }

    /**
     * Dispatch event to add a column after this column.
     * @param {*} event
     */
    _addColumn(event) {
        document.activeElement.blur();
        let target = event.target.closest(selectors.ADDCOLUMN);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('addColumn', data.id);
    }

    /**
     * Called when a card was created in this column.
     * @param {*} param0
     */
    async _cardCreated({element}) {
        if (element.kanban_column == this.id) {
            let data = JSON.parse(JSON.stringify(element));
            Object.assign(data, exporter.exportCapabilities(this.reactive.state));
            let placeholder = document.createElement('li');
            placeholder.setAttribute('data-id', data.id);
            let node = this.getElement(selectors.COLUMNINNER, this.id);
            node.appendChild(placeholder);
            const newcomponent = await this.renderComponent(placeholder, 'mod_kanban/card', data);
            const newelement = newcomponent.getElement();
            node.replaceChild(newelement, placeholder);
            // Update card count after adding new card
            this.getElement(selectors.CARDCOUNT).innerHTML = this.getElements(selectors.CARD).length;
        }
    }

    /**
     * Dispatch event to add a card in this column.
     * @param {*} event
     */
    _addCard(event) {
        document.activeElement.blur();
        let target = event.target.closest(selectors.ADDCARD);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('addCard', data.columnid, 0);
    }

    /**
     * Called when column is updated.
     * @param {*} param0
     */
    _columnUpdated({element}) {
        const el = this.getElement(selectors.COLUMNINNER, this.id);
        if (element.sequence !== undefined) {
            let sequence = element.sequence.split(',');
            // Remove all cards from frontend that are no longer present in the database.
            [...el.children]
                .forEach((node) => {
                    if (node.classList.contains('mod_kanban_card') && !sequence.includes(node.dataset.id)) {
                        el.removeChild(node);
                    }
                });
            // Reorder cards according to sequence from the database.
            [...el.children]
                .sort((a, b) => sequence.indexOf(a.dataset.id) > sequence.indexOf(b.dataset.id) ? 1 : -1)
                .forEach(node => el.appendChild(node));
        }
        if (element.locked !== undefined) {
            this.toggleClass(element.locked != 0, 'mod_kanban_locked_column');
            // Inplace editing of the column title is disabled if the column is locked.
            if (element.locked != 0) {
                this.getElement(selectors.INPLACEEDITABLE).removeAttribute('data-inplaceeditable');
            } else {
                this.getElement(selectors.INPLACEEDITABLE).setAttribute('data-inplaceeditable', '1');
            }
        }
        // Update data for inplace editing if title was updated (this is important if title was modified by another user).
        if (element.title !== undefined) {
            // For Moodle inplace editing title is once needed plain and once with html entities encoded.
            // This avoids double encoding of html entities as the value of "data-value" is exactly what is shown
            // in the input field when clicking on the inplace editable.
            let doc = new DOMParser().parseFromString(element.title, 'text/html');
            this.getElement(selectors.INPLACEEDITABLE).setAttribute('data-value', doc.documentElement.textContent);
            this.getElement(selectors.INPLACEEDITABLE).querySelector('a').innerHTML = element.title;
        }
        // Only autohide option is relevant for the frontend for now. autoclose option is handled by the backend.
        if (element.options !== undefined) {
            let options = JSON.parse(element.options);
            this.toggleClass(options.autohide, 'mod_kanban_autohide');
            this.toggleClass(options.wiplimit > 0, 'mod_kanban_column_wiplimit');
            this.getElement(selectors.WIPLIMIT).innerHTML = options.wiplimit;
        }
        // Update card count after updating column
        this.getElement(selectors.CARDCOUNT).innerHTML = this.getElements(selectors.CARD).length;
        // Enable/disable dragging (e.g. if column is locked).
        this.checkDragging();
    }

    /**
     * Called when this column is deleted.
     */
    _columnDeleted() {
        this.destroy();
    }

    /**
     * Dispatch event to remove this column.
     * @param {*} event
     */
    _removeColumn(event) {
        let target = event.target.closest(selectors.DELETECOLUMN);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('deleteColumn', data.id);
    }

    /**
     * Dispatch event to lock this column.
     * @param {*} event
     */
    _lockColumn(event) {
        let target = event.target.closest(selectors.LOCKCOLUMN);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('lockColumn', data.id);
    }

    /**
     * Dispatch event to unlock this column.
     * @param {*} event
     */
    _unlockColumn(event) {
        let target = event.target.closest(selectors.UNLOCKCOLUMN);
        let data = Object.assign({}, target.dataset);
        this.reactive.dispatch('unlockColumn', data.id);
    }

    /**
     * Show modal form to edit column details.
     * @param {*} event
     */
    _editDetails(event) {
        event.preventDefault();

        const modalForm = new ModalForm({
            formClass: "mod_kanban\\form\\edit_column_form",
            args: {
                id: this.id,
                boardid: this.boardid,
                cmid: this.cmid
            },
            modalConfig: {title: getString('editcolumn', 'mod_kanban')},
            returnFocus: this.getElement(),
        });
        this.addEventListener(modalForm, modalForm.events.FORM_SUBMITTED, this._updateColumn);
        modalForm.show();
    }

    /**
     * Dispatch an event to update column data from the detail modal.
     * @param {*} event
     */
    _updateColumn(event) {
        this.reactive.dispatch('processUpdates', event.detail);
    }

    /**
     * Show hidden cards.
     */
    _showHidden() {
        this.getElement().classList.add('mod_kanban_show_hidden');
    }

    /**
     * Hide hidden cards.
     */
    _hideHidden() {
        this.getElement().classList.remove('mod_kanban_show_hidden');
    }
}
