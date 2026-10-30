import { test, expect, type FixtureTypes } from '@shopware-ag/acceptance-test-suite';
import type { Locator, Page } from '@playwright/test';
import { captureAuthorizedOrder, refundOrder, assertPayRefundAccepted } from '../src/admin-order';
import { dismissAdminPopups } from '../src/admin-popups';
import {
    acceptTermsAndSubmitOrder,
    reachGuestCheckoutConfirm,
    selectPayPaymentMethod,
    type GuestCheckout,
} from '../src/guest-checkout';
import { completePaySandbox, type SandboxPaymentStatus } from '../src/pay-sandbox';
import {
    assignPayPaymentMethod,
    expectOrderTransactionHistoryComment,
    latestOrderTransaction,
    readPaynlTransactionState,
    waitForOrderPaymentState,
    waitForPaynlTransactionState,
} from '../src/shopware-admin';
import { maskPayConfigCredentialInputs } from '../src/pay-config-page';
import { step } from '../src/step';

const REFUND_STATUS_CODES = [-72, -81, -82];
const ALLOW_REFUNDS = /^(allow refunds|erstattungen zulassen|restituties toestaan)$/i;
const ALLOW_NATIVE_REFUNDS = /^(allow shopware native refunds|shopware native refunds zulassen|shopware native refunds toestaan)$/i;
const CAPTURE_COMMENT = 'Captured in the PAY. acceptance test';
const UNKNOWN_PAY_ORDER_ID = '9999999999X99999';
const REFUND_FIXME = 'PAY. refuses the refund for this API token (forbidden). The token needs the refund right in my.pay.nl. No tracking issue yet.';

type CheckoutFixtures = GuestCheckout & Pick<
    FixtureTypes,
    'StorefrontCheckoutConfirm' | 'StorefrontCheckoutFinish'
>;

async function placePayOrder(
    fixtures: CheckoutFixtures,
    status: SandboxPaymentStatus,
): Promise<{ orderId: string; orderNumber: string | null }> {
    const {
        StorefrontPage,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        DefaultSalesChannel,
        AdminApiContext,
    } = fixtures;

    const payMethod = await step(StorefrontPage, 'Assign PAY. payment method', () => assignPayPaymentMethod(
        AdminApiContext,
        DefaultSalesChannel.salesChannel.id,
    ));

    await reachGuestCheckoutConfirm(fixtures);

    const expected = await step(StorefrontPage, 'Choose PAY. and place the order', async () => {
        await expect(StorefrontCheckoutConfirm.headline).toBeVisible();
        await selectPayPaymentMethod(StorefrontPage, payMethod);
        return acceptTermsAndSubmitOrder(StorefrontCheckoutConfirm);
    });

    await step(StorefrontPage, `Complete the PAY. sandbox as ${status}`, () => completePaySandbox(
        StorefrontPage,
        expected.sandboxAmount,
        status,
    ));

    return step(StorefrontPage, 'Read the order from the thank you page', async () => {
        await StorefrontPage.waitForURL(/checkout\/finish/i, { timeout: 60_000 });
        const orderId = StorefrontCheckoutFinish.getOrderId();
        const orderNumber = await StorefrontCheckoutFinish.getOrderNumber().catch(() => null);
        expect(orderId || orderNumber, 'Order confirmation did not expose an order id or number').toBeTruthy();
        return { orderId, orderNumber };
    });
}

async function enableRefundSwitches(page: Page): Promise<void> {
    await page.goto('./#/sw/extension/config/PaynlPaymentShopware6', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/extension\/config\/PaynlPaymentShopware6/);
    await dismissAdminPopups(page);
    await maskPayConfigCredentialInputs(page);

    const allowRefunds = page.getByRole('checkbox', { name: ALLOW_REFUNDS });
    const nativeRefunds = page.getByRole('checkbox', { name: ALLOW_NATIVE_REFUNDS });
    await expect(allowRefunds).toBeVisible({ timeout: 60_000 });
    await expect(nativeRefunds).toBeVisible();

    const allowChanged = await switchOn(allowRefunds);
    const nativeChanged = await switchOn(nativeRefunds);
    if (!allowChanged && !nativeChanged) {
        return;
    }

    const saved = page.waitForResponse(
        (response) => response.url().includes('/_action/system-config') && response.request().method() === 'POST',
        { timeout: 60_000 },
    );
    await page.locator('.sw-extension-config__save-action').click();
    const response = await saved;
    const body = await response.text();
    expect(response.ok(), `Could not save plugin config: ${response.status()} ${body}`).toBeTruthy();
    await expect(allowRefunds).toBeChecked();
    await expect(nativeRefunds).toBeChecked();
}

async function switchOn(checkbox: Locator): Promise<boolean> {
    if (await checkbox.isChecked()) {
        return false;
    }

    await checkbox.check({ force: true });
    await expect(checkbox).toBeChecked();
    return true;
}

test.describe('PAY. admin capture and refund', () => {
    test('admin Paid on an authorised payment marks the Shopware payment paid', async ({
        AdminPage,
        StorefrontPage,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        ProductData,
        DefaultSalesChannel,
        AdminApiContext,
        ShopCustomer,
        Register,
    }) => {
        test.setTimeout(300_000);

        const placed = await placePayOrder({
            StorefrontPage,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        }, 'authorised');

        const authorized = await step(StorefrontPage, 'Wait until the payment is authorized', () => waitForOrderPaymentState(
            AdminApiContext,
            { orderId: placed.orderId || undefined, orderNumber: placed.orderNumber },
            'authorize',
        ));
        const before = await waitForPaynlTransactionState(AdminApiContext, authorized.id, 95);

        await step(AdminPage, 'Close the Shopware consent and update popups', () => dismissAdminPopups(AdminPage));
        await step(AdminPage, 'Set payment status to Paid (capture)', () => captureAuthorizedOrder(
            AdminPage,
            authorized.id,
            { internalComment: CAPTURE_COMMENT },
        ));

        const paid = await step(StorefrontPage, 'Wait until the Shopware payment is paid', () => waitForOrderPaymentState(
            AdminApiContext,
            { orderId: placed.orderId || undefined, orderNumber: placed.orderNumber },
            'paid',
        ));
        await step(StorefrontPage, 'Wait until PAY. captured the payment', () => waitForPaynlTransactionState(
            AdminApiContext,
            authorized.id,
            100,
        ));
        await step(AdminPage, 'Confirm the internal comment was stored on the payment state change', () => (
            expectOrderTransactionHistoryComment(
                AdminApiContext,
                latestOrderTransaction(paid).id,
                CAPTURE_COMMENT,
            )
        ));
    });

    test('admin Paid shows an error when PAY. rejects the capture', async ({
        AdminPage,
        StorefrontPage,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        StorefrontCheckoutFinish,
        ProductData,
        DefaultSalesChannel,
        AdminApiContext,
        ShopCustomer,
        Register,
    }) => {
        test.setTimeout(300_000);

        const placed = await placePayOrder({
            StorefrontPage,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        }, 'authorised');
        const lookup = { orderId: placed.orderId || undefined, orderNumber: placed.orderNumber };

        const authorized = await step(StorefrontPage, 'Wait until the payment is authorized', () => waitForOrderPaymentState(
            AdminApiContext,
            lookup,
            'authorize',
        ));
        const before = await waitForPaynlTransactionState(AdminApiContext, authorized.id, 95);

        await step(StorefrontPage, 'Point the PAY. row at an order PAY. does not know', async () => {
            const patched = await AdminApiContext.patch(`./paynl-transactions/${before.id}`, {
                data: { paynlTransactionId: UNKNOWN_PAY_ORDER_ID },
            });
            expect(
                patched.ok(),
                `Could not replace the PAY. order id: ${patched.status()} ${await patched.text()}`,
            ).toBeTruthy();
        });

        await step(AdminPage, 'Close the Shopware consent and update popups', () => dismissAdminPopups(AdminPage));
        await step(AdminPage, 'Set payment status to Paid and expect PAY. to refuse it', () => captureAuthorizedOrder(
            AdminPage,
            authorized.id,
            { expectAccepted: false },
        ));

        const stillAuthorized = await step(StorefrontPage, 'Confirm the Shopware payment stayed authorized', () => (
            waitForOrderPaymentState(AdminApiContext, lookup, 'authorize', 15_000)
        ));
        expect(latestOrderTransaction(stillAuthorized).stateMachineState.technicalName).toBe('authorize');
    });

    test.describe('admin refund', () => {
        test.fixme('admin refund of a paid payment refunds it at PAY.', {
            annotation: {
                type: 'fixme',
                description: REFUND_FIXME,
            },
        }, async ({
            AdminPage,
            StorefrontPage,
            StorefrontProductDetail,
            StorefrontCheckoutConfirm,
            StorefrontCheckoutFinish,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        }) => {
            test.setTimeout(300_000);

            await step(AdminPage, 'Close the Shopware consent and update popups', () => dismissAdminPopups(AdminPage));
            await step(AdminPage, 'Turn on Allow refunds and Allow Shopware native refunds', () => enableRefundSwitches(AdminPage));

            const placed = await placePayOrder({
                StorefrontPage,
                StorefrontProductDetail,
                StorefrontCheckoutConfirm,
                StorefrontCheckoutFinish,
                ProductData,
                DefaultSalesChannel,
                AdminApiContext,
                ShopCustomer,
                Register,
            }, 'captured');

            const paid = await step(StorefrontPage, 'Wait until the payment is paid', () => waitForOrderPaymentState(
                AdminApiContext,
                { orderId: placed.orderId || undefined, orderNumber: placed.orderNumber },
                'paid',
            ));
            const before = await readPaynlTransactionState(AdminApiContext, paid.id);
            expect(before.stateId).toBe(100);

            const refundMessages = await step(AdminPage, 'Refund the order', () => refundOrder(AdminPage, paid.id));
            assertPayRefundAccepted(refundMessages);

            const after = await readPaynlTransactionState(AdminApiContext, paid.id);
            expect(
                REFUND_STATUS_CODES,
                `PAY. transaction ${after.paynlTransactionId} stayed at state ${after.stateId}`,
            ).toContain(after.stateId);
        });
    });
});
