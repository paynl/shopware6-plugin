import { clearDelayedCache, expect, test } from '@shopware-ag/acceptance-test-suite';
import type { Page } from '@shopware-ag/acceptance-test-suite';
import { preparePayPartsCardCheckout } from '../src/shopware-admin';
import { acceptCookies } from '../src/storefront';

const SDK_READY_TIMEOUT_MS = 60_000;
const PAY_PARTS_FIXME = 'Pay.Parts card form is not reliable in this suite: the card SDK is loaded from PAY. and this test has not been stabilised. No tracking issue yet.';
const INVALID_CARD_NUMBER = '4111111111111112';
const VALID_CARD_NUMBER = '4111111111111111';

test.fixme('Pay.Parts credit card form is shown on checkout confirm', {
    annotation: {
        type: 'fixme',
        description: PAY_PARTS_FIXME,
    },
}, async ({
    StorefrontPage,
    AdminApiContext,
    DefaultSalesChannel,
    TestDataService,
}) => {
    const cardMethod = await preparePayPartsCardCheckout(
        AdminApiContext,
        DefaultSalesChannel.salesChannel.id,
    );
    const shopper = await TestDataService.createCustomer({
        lastPaymentMethodId: cardMethod.id,
    });
    const product = await TestDataService.createBasicProduct();
    await clearDelayedCache(AdminApiContext);

    await openCheckoutConfirm(StorefrontPage, shopper, product.id);
    await expectCreditCardForm(StorefrontPage, shopper.email);
});

async function openCheckoutConfirm(
    page: Page,
    shopper: { email: string; password: string },
    productId: string,
): Promise<void> {
    await page.goto('account/login');
    await acceptCookies(page);
    await page.locator('#loginMail').fill(shopper.email);
    await page.locator('#loginPassword').fill(shopper.password);
    await page.locator('.login-form button[type="submit"]').click();
    await expect(page.locator('#loginMail')).toBeHidden();

    await page.goto(`detail/${productId}`);
    await page.locator('.buy-widget .btn-buy').click();
    await page.locator('a.begin-checkout-btn').click();
    await page.waitForURL('**/checkout/confirm**');
}

async function expectCreditCardForm(page: Page, email: string): Promise<void> {
    const wrapper = page.locator('.paynl-payparts-card-wrapper');
    await expect(wrapper).toBeVisible();

    const gate = wrapper.locator('.payparts-cc-gate__ctp[data-cc-ctp-view]');
    const sdkError = wrapper.locator('.paynl-payparts-card-error');
    await waitForPayPartsSdk(page);

    // The storefront plugin mounts as soon as the session returns. On a cold load that
    // can be before the SDK module has evaluated, so reload once the script is available.
    if (await sdkError.isVisible()) {
        await page.reload();
        await acceptCookies(page);
        await waitForPayPartsSdk(page);
    }

    await expect(gate, (await sdkError.textContent()) ?? 'Pay.Parts card did not render').toBeVisible({
        timeout: SDK_READY_TIMEOUT_MS,
    });
    await expect(sdkError).toBeHidden();

    await expect(gate.getByText(`No cards found for ${email}.`)).toBeVisible();
    await expect(gate.getByText('Or', { exact: true })).toBeVisible();
    await expect(gate.getByRole('button', { name: 'Use a different e-mail address' })).toBeEnabled();

    const manualEntryButton = gate.getByRole('button', { name: 'Enter card details manually' });
    await expect(manualEntryButton).toBeEnabled();
    await manualEntryButton.click();

    const form = wrapper.getByRole('form').filter({ hasText: /card number/i });
    await expect(form).toBeVisible();
    const cardholderName = form.getByRole('textbox', { name: /cardholder name|full name on card/i });
    const cardNumber = form.getByRole('textbox', { name: /card number|1234 5678/i });
    const expiry = form.getByRole('textbox', { name: /expiry|mm\s*\/\s*yy/i });
    const cvc = form.getByRole('textbox', { name: /cvc/i });
    const payButton = form.getByRole('button', { name: /^Pay\b/ });
    await expect(cardholderName).toBeVisible();
    await expect(cardNumber).toBeVisible();
    await expect(expiry).toBeVisible();
    await expect(cvc).toBeVisible();
    await expect(payButton).toBeVisible();

    await cardholderName.fill('Test Shopper');
    await cardNumber.pressSequentially(INVALID_CARD_NUMBER);
    await expiry.pressSequentially('1230');
    await expect(expiry).toHaveValue('12/30');
    await cvc.pressSequentially('123');
    await expect(payButton).toBeDisabled();

    await cardNumber.fill('');
    await cardNumber.pressSequentially(VALID_CARD_NUMBER);
    await expect(payButton).toBeEnabled();
}

async function waitForPayPartsSdk(page: Page): Promise<void> {
    await page.waitForFunction(() => {
        const sdk = (window as Window & { PayPartsSDK?: unknown }).PayPartsSDK;

        return sdk !== undefined || document.querySelector('.payparts-cc-gate__ctp') !== null;
    }, undefined, { timeout: SDK_READY_TIMEOUT_MS });
}
