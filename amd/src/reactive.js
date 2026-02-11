import {Reactive} from 'core/reactive';
import Ajax from 'core/ajax';

/**
 * Reactive instance for mod_kanban.
 */
export default class extends Reactive {
    /**
     * Load a board and set initial state.
     * @param {number} cmid Course module id
     * @param {number} boardid Board id
     */
    async loadBoard(cmid, boardid) {
        const initialData = await Ajax.call(
            [
                {
                    methodname: 'mod_kanban_get_kanban_content_init',
                    args: {
                        'cmid': cmid,
                        'boardid': boardid,
                        'timestamp': 0,
                    }
                }
            ]
        )[0];

        initialData.filters = {};
        this.setInitialState(initialData);
    }
}