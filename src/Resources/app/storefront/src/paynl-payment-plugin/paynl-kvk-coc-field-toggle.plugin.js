import Plugin from 'src/plugin-system/plugin.class';

const selectedValues = {
    isBusiness: false,
    isPayCountrySelected: false
};

export default class PaynlKvkCocFieldTogglePlugin extends Plugin {
    init() {
        document.addEventListener('change', this.accountFormChange.bind(this));
    }

    accountFormChange(event) {
        const el = event.target;
        const form = el.form;

        if (typeof form === 'undefined' || form === null) {
            return;
        }

        if (form.getAttribute('id') === 'profilePersonalForm' && el.name === 'accountType') {
            selectedValues.isBusiness = Boolean(el.value === 'business');
            this.toggleCocField(selectedValues, form);
            return;
        }

        if (!form.classList.contains('register-form')) {
            return;
        }

        const kvkBlock = form.querySelector('.paynl-kvk-coc-number-field');
        if (!kvkBlock) {
            return;
        }

        if (!['accountType', 'addressaccountType'].includes(el.name)) {
            return;
        }

        selectedValues.isBusiness = Boolean(el.value === 'business');

        this.toggleCocField(selectedValues, form);
    }

    toggleCocField(selectedValues, form) {
        const kvkCocFieldBlock = form.querySelector('.paynl-kvk-coc-number-field');
        if (kvkCocFieldBlock === null) {
            return;
        }
        const kvkCocFieldInput = kvkCocFieldBlock.querySelector('input[name="coc_number"]');

        if (!selectedValues.isBusiness) {
            kvkCocFieldBlock.style.display = 'none';
            kvkCocFieldInput.setAttribute('disabled', 'disabled');

            return;
        }

        kvkCocFieldBlock.style.display = 'block';
        kvkCocFieldInput.removeAttribute('disabled');
    }
}
