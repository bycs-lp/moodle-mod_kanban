import {BaseComponent} from 'core/reactive';

/**
 * Component representing a card in a kanban board.
 */
export default class extends BaseComponent {
    /**
     * Does nothing if trueFalseOrUndefined is undefined.
     * If it is true, class is added to elements classList else it is removed.
     *
     * @param {*} trueFalseOrUndefined
     * @param {*} className
     */
    toggleClass(trueFalseOrUndefined, className) {
        if (trueFalseOrUndefined !== undefined) {
            if (trueFalseOrUndefined == true) {
                this.getElement().classList.add(className);
            } else {
                this.getElement().classList.remove(className);
            }
        }
    }

    /**
     * Helper function to add an event listener to an element if it exists.
     * @param {*} element
     * @param {*} event
     * @param {*} listener
     */
    addEventListener(element, event, listener) {
        if (element) {
            super.addEventListener(element, event, listener);
        }
    }
}