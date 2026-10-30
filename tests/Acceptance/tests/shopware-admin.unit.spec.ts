import { expect, test } from '@playwright/test';
import { payPluginConfigMatches, type PayPluginConfig } from '../src/shopware-admin';

const wanted: PayPluginConfig = {
    testMode: true,
    useSinglePaymentMethod: true,
    logging: true,
    paymentScreenLanguage: 'en',
};

test('sales-channel PAY. config matches only when logging and English match too', () => {
    expect(payPluginConfigMatches({ ...wanted }, wanted)).toBe(true);
    expect(payPluginConfigMatches({ ...wanted, logging: false }, wanted)).toBe(false);
    expect(payPluginConfigMatches({ ...wanted, paymentScreenLanguage: 'nl' }, wanted)).toBe(false);
    expect(payPluginConfigMatches({ ...wanted, testMode: false }, wanted)).toBe(false);
    expect(payPluginConfigMatches(
        { ...wanted, enablePayPartsCreditCardWidget: false },
        { ...wanted, enablePayPartsCreditCardWidget: true },
    )).toBe(false);
    expect(payPluginConfigMatches(
        { ...wanted, enablePayPartsCreditCardWidget: false },
        wanted,
    )).toBe(true);
});
