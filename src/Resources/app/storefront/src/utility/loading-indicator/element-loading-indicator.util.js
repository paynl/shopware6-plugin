const BACKDROP_CLASS = 'element-loader-backdrop';

/**
 * Loading indicator that covers an element.
 *
 * Uses the markup and CSS classes of Shopware's ElementLoadingIndicatorUtil,
 * so the storefront theme styles it.
 */
export default class ElementLoadingIndicatorUtil {
    /**
     * @param {HTMLElement} el
     * @returns {void}
     */
    static create(el) {
        el.classList.add('has-element-loader');

        if (el.querySelector(`.${BACKDROP_CLASS}`)) {
            return;
        }

        el.insertAdjacentHTML('beforeend', `
            <div class="${BACKDROP_CLASS}">
                <div class="loader" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);

        // Added one tick later, so the theme's fade-in transition runs.
        setTimeout(() => {
            el.querySelector(`.${BACKDROP_CLASS}`)?.classList.add(`${BACKDROP_CLASS}-open`);
        }, 1);
    }
}
