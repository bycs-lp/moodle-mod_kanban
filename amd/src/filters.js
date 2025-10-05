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
 * Filter the cards of a kanban board.
 *
 * @module     mod_kanban/filters
 * @copyright  2025 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the filter for the kanban board.
 * @param {number} id The board id
 */
export const filters = (id) => {
    const searchinput = document.querySelector(`input[name="mod_kanban_filter_search-${id}"]`);
    if (!searchinput) {
        return;
    }

    searchinput.addEventListener('input', function(event) {
        const input = event.target.closest('input');
        const searchterm = input.value.trim().toLowerCase();
        const board = input.closest('.mod_kanban_board');
        if (!board) {
            return;
        }
        const cards = board.querySelectorAll('.mod_kanban_card');
        cards.forEach((card) => {
            const title = card.querySelector('.mod_kanban_card_title');
            if (!title) {
                return;
            }
            const titletext = title.textContent.trim().toLowerCase();
            if (titletext.includes(searchterm)) {
                card.classList.remove('mod_kanban_card_hidden');
            } else {
                card.classList.add('mod_kanban_card_hidden');
            }
        });
    });

    const closebutton = document.querySelector(`a[data-action="closesearch"]`);
    if (closebutton) {
        closebutton.addEventListener('click', function() {
            if (searchinput) {
                searchinput.value = '';
                const board = searchinput.closest('.mod_kanban_board');
                if (!board) {
                    return;
                }
                const cards = board.querySelectorAll('.mod_kanban_card');
                cards.forEach((card) => {
                    card.classList.remove('mod_kanban_card_hidden');
                });
            }
        });
    }
};

