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

import {render as renderTemplate} from 'core/templates';
import Log from 'core/log';
import selectors from 'mod_kanban/selectors';
import {debounce} from 'core/utils';

/**
 * Helper to show small notifications on the kanban board.
 *
 * @module     mod_kanban/notification
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class KanbanNotification {
    /**
     * Shows a notification on the kanban board.
     *
     * @param {*} event The event object from which to extract the target element and pointer coordinates.
     * @param {string} message The message to show in the notification.
     * @param {number} [timestamp] Optional timestamp to identify the notification.
     */
    static show(event, message, timestamp = Date.now()) {
        if (!event.target) {
            return;
        }

        const container = event.target.closest(selectors.BOARD);
        if (!container) {
            return;
        }

        const notification = document.createElement('div');
        renderTemplate('mod_kanban/notification', {message: message, timestamp: timestamp}).then((html) => {
            notification.innerHTML = html;
            notification.setAttribute('class', 'mod_kanban_notification_container');
            notification.style.top = `${event.pageY}px`;
            notification.style.left = `${event.pageX}px`;
            container.appendChild(notification);
            return true;
        }).catch((error) => {
            Log.debug(error);
        });
        // Automatically remove the notification after 2 seconds.
        setTimeout(() => {
            notification.remove();
        }, 2000);
        document.addEventListener('pointermove', () => {
            debounce(() => {
                notification.remove();
            }, 500)();
        }, {once: true});
    }
}
