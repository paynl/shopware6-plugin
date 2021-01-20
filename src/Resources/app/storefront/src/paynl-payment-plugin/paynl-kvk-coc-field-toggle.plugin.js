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

        if (el.id === 'accountType' || el.id === 'addressaccountType') {
            selectedValues.isBusiness = Boolean(el.value === 'business');
            const countrySelect = form.querySelector('.paynl-country-select');
            if (countrySelect !== null) {
                selectedValues.isPayCountrySelected = Boolean(countrySelect.options[countrySelect.selectedIndex].getAttribute('data-paynl-kvk-coc-field'));
            }
        } else if (el.classList.contains('paynl-country-select')) {
            selectedValues.isPayCountrySelected = Boolean(el.options[el.selectedIndex].getAttribute('data-paynl-kvk-coc-field'));
            const accountTypeSelect = form.querySelector('select[name="address[accountType]"]');
            if (accountTypeSelect !== null) {
                selectedValues.isBusiness = Boolean(accountTypeSelect.value === 'business');
            }
        } else {
            return;
        }

        this.toggleCocField(selectedValues, form);
    }

    toggleCocField(selectedValues, form) {
        const kvkCocFieldBlock = form.querySelector('.paynl-kvk-coc-number-field');
        if (kvkCocFieldBlock === null) {
            return;
        }
        const kvkCocFieldInput = kvkCocFieldBlock.querySelector('input[name="coc_number"]');

        if (!selectedValues.isBusiness || !selectedValues.isPayCountrySelected) {
            kvkCocFieldBlock.style.display = 'none';
            kvkCocFieldInput.setAttribute('disabled', 'disabled');

            return;
        }

        kvkCocFieldBlock.style.display = 'block';
        kvkCocFieldInput.removeAttribute('disabled');
    }
}
