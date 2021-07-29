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
        if (form.getAttribute('id') === 'profilePersonalForm' && el.id === 'accountType') {
            selectedValues.isBusiness = Boolean(el.value === 'business');
            selectedValues.isPayCountrySelected = selectedValues.isBusiness;
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

        const kvkCountriesIds = kvkBlock.getAttribute('data-countries-with-kvk').split(',');
        if (el.id === 'accountType' || el.id === 'addressaccountType') {
            selectedValues.isBusiness = Boolean(el.value === 'business');
            const countrySelect = form.querySelector('.country-select');
            if (countrySelect !== null) {
                selectedValues.isPayCountrySelected = Boolean(
                    kvkCountriesIds.includes(countrySelect.options[countrySelect.selectedIndex].getAttribute('value'))
                );
            }
        } else if (el.classList.contains('country-select')) {
            selectedValues.isPayCountrySelected = Boolean(
                kvkCountriesIds.includes(el.options[el.selectedIndex].getAttribute('value'))
            );
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
