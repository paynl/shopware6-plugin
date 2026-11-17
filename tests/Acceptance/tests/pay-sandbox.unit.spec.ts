import { expect, test } from '@playwright/test';
import { isIssuerHost, isPayHost, parseAmount } from '../src/pay-sandbox';

const amounts: Array<[string, number]> = [
    ['€ 10.00', 10],
    ['€ 10,00', 10],
    ['1.234,56', 1234.56],
    ['1,234.56', 1234.56],
    ['1.234', 1234],
];

const hosts: Array<[string, boolean, boolean]> = [
    ['https://ext.pay.wero.ideal.nl/10001', true, false],
    ['https://checkout.pay.nl/en-us/sandbox/00000000-0000-0000-0000-000000000000', true, false],
    ['https://www.ideal.nl/issuer', false, true],
    ['https://challenges.cloudflare.com/', false, true],
    ['https://shop.example/checkout/finish?return=https://checkout.pay.nl/pay', false, false],
    ['https://random-name.trycloudflare.com/', false, false],
    ['https://notpay.nl/', false, false],
];

test('parseAmount reads shop totals the sandbox can charge', () => {
    for (const [raw, expected] of amounts) {
        const parsed = parseAmount(raw);
        expect(parsed.amount, raw).toBe(expected);
        expect(parsed.sandboxAmount, raw).toBe(expected.toFixed(2));
    }
});

test('pay hosts and issuer hosts stay distinct', () => {
    for (const [href, pay, issuer] of hosts) {
        expect(isPayHost(href), href).toBe(pay);
        expect(isIssuerHost(href), href).toBe(issuer);
    }
});
