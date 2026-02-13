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

import {debounce} from 'core/utils';
import KanbanComponent from 'mod_kanban/kanbancomponent';

/**
 * Filter the cards of a kanban board.
 *
 * @module     mod_kanban/filters
 * @copyright  2025 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Called once when state is ready (also if component is registered after initial state was set), attaching event listeners.
     */
    stateReady() {
        const searchinput = this.getElement();
        this.addEventListener(this.getElement(), 'input', debounce((event) => {
            const input = event.target.closest('input');
            const searchterm = input.value.trim().toLowerCase();
            const board = input.closest('.mod_kanban_board');
            if (!board) {
                return;
            }
            this.reactive.dispatch('updateFilter', {type: 'title', value: searchterm});
        }, 500));

        const closebutton = this.getElement().parentElement.querySelector(`a[data-action="closesearch"]`);
        if (closebutton) {
            this.addEventListener(closebutton, 'click', () => {
                searchinput.value = '';
                this.reactive.dispatch('removeFilter', {type: 'title'});
            });
        }
    }
}
