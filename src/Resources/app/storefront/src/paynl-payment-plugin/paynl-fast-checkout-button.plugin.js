import Plugin from 'src/plugin-system/plugin.class';

export default class PaynlFastCheckoutButton extends Plugin {
    init() {
        document.getElementById('btn-fast-checkout').addEventListener('click', this.onFastCheckoutClick.bind(this));
        document.querySelector('.paynl-ideal-modal-backdrop').addEventListener('click', this.onCloseModalClick.bind(this));
    }

    onFastCheckoutClick(event) {
        event.preventDefault();

        const modal = document.querySelector('.paynl-ideal-modal');
        const backdrop = document.querySelector('.paynl-ideal-modal-backdrop');
        modal.classList.add('visible');
        backdrop.classList.add('visible');
        document.body.style.overflow = 'hidden';
    }

    onCloseModalClick(event) {
        event.preventDefault();

        const modal = document.querySelector('.paynl-ideal-modal');
        const backdrop = document.querySelector('.paynl-ideal-modal-backdrop');
        modal.classList.remove('visible');
        backdrop.classList.remove('visible');
        document.body.style.overflow = '';
    }
}
