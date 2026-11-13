/**
 * Loading indicator on a button.
 *
 * Works on the markup and CSS classes of Shopware's ButtonLoadingIndicatorUtil,
 * so it can remove an indicator that Shopware put on the button.
 */
export default class ButtonLoadingIndicatorUtil {
    /**
     * @param {HTMLButtonElement|HTMLAnchorElement} parent
     * @param {'before'|'after'|'inner'} position
     */
    constructor(parent, position = 'before') {
        this.parent = parent;
        this.position = position;
    }

    /**
     * Removes the indicator and enables the button again.
     *
     * @returns {void}
     */
    remove() {
        const indicators = this.parent.querySelectorAll('.loader');

        if (indicators.length === 0) {
            console.warn('[ButtonLoadingIndicatorUtil] No loading indicator present on the given element.', this.parent);
            return;
        }

        indicators.forEach((indicator) => indicator.remove());
        this.parent.classList.remove(`is-loading-indicator-${this.position}`);

        if (this.parent.tagName === 'BUTTON') {
            this.parent.disabled = false;
        } else if (this.parent.tagName === 'A') {
            this.parent.classList.remove('disabled');
        }
    }
}
