import { expect, test, type FixtureTypes } from '@shopware-ag/acceptance-test-suite';
import type { Page } from '@playwright/test';
import { acceptTermsAndSubmitOrder, reachGuestCheckoutConfirm, selectPayPaymentMethod } from '../src/guest-checkout';
import { completePaySandbox } from '../src/pay-sandbox';
import {
    assignIdealPaymentMethod,
    assignPayPaymentMethod,
    IDEAL_PAYNL_ID,
    installIndividualPayPaymentMethods,
    latestOrderTransaction,
    waitForPaidOrder,
} from '../src/shopware-admin';
import { step } from '../src/step';

async function confirmPaidOrder(
    page: Page,
    finish: FixtureTypes['StorefrontCheckoutFinish'],
    adminApi: FixtureTypes['AdminApiContext'],
    expected: { amount: number; currency: string },
): Promise<void> {
    const confirmationHeading = page.locator('h1.finish-header, .finish-header, h1').first();
    await expect(confirmationHeading).toBeVisible({ timeout: 30_000 });
    await expect(confirmationHeading).toContainText(/thank you|successful|danke|bedankt|payment successful/i);

    const orderId = finish.getOrderId();
    const orderNumber = await finish.getOrderNumber().catch(() => null);
    expect(orderId || orderNumber, 'Order confirmation did not expose an order id or number').toBeTruthy();

    const order = await waitForPaidOrder(
        adminApi,
        { orderId: orderId || undefined, orderNumber },
        { amount: expected.amount, currency: expected.currency },
    );

    expect(latestOrderTransaction(order).stateMachineState.technicalName).toBe('paid');
}

test.describe('PAY. checkout', () => {
    test('guest checkout reaches paid after PAY. sandbox payment', async ({
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
        const payMethod = await step(StorefrontPage, 'Assign PAY. payment method', () => assignPayPaymentMethod(
            AdminApiContext,
            DefaultSalesChannel.salesChannel.id,
        ));

        await reachGuestCheckoutConfirm({
            StorefrontPage,
            StorefrontProductDetail,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        });

        const expected = await step(StorefrontPage, 'Choose PAY. and place the order', async () => {
            await expect(StorefrontCheckoutConfirm.headline).toBeVisible();
            await selectPayPaymentMethod(StorefrontPage, payMethod);
            return acceptTermsAndSubmitOrder(StorefrontCheckoutConfirm);
        });

        await step(StorefrontPage, 'Complete the PAY. sandbox', () => completePaySandbox(StorefrontPage, expected.sandboxAmount));

        await step(StorefrontPage, 'Confirm the order is paid', () => confirmPaidOrder(
            StorefrontPage,
            StorefrontCheckoutFinish,
            AdminApiContext,
            expected,
        ));
    });

    test('iDEAL checkout reaches the thank you page', async ({
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

        await step(StorefrontPage, 'Turn off the uniform PAY. method and install iDEAL', () => assignIdealPaymentMethod(
            AdminApiContext,
            DefaultSalesChannel.salesChannel.id,
        ));

        await reachGuestCheckoutConfirm({
            StorefrontPage,
            StorefrontProductDetail,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        });

        const expected = await step(StorefrontPage, 'Choose iDEAL and place the order', async () => {
            await expect(StorefrontCheckoutConfirm.headline).toBeVisible();

            const idealMethod = StorefrontPage.locator('.payment-method').filter({
                has: StorefrontPage.locator(`input[data-paynlid="${IDEAL_PAYNL_ID}"]`),
            });
            await expect(idealMethod).toHaveCount(1);
            await expect(idealMethod).toBeVisible();

            const idealInput = idealMethod.locator(`input[data-paynlid="${IDEAL_PAYNL_ID}"]`);
            if (!(await idealInput.isChecked())) {
                await idealInput.check({ force: true });
                await StorefrontPage.waitForLoadState('domcontentloaded');
            }

            const savePaymentMethod = idealMethod.locator('.paynl-change-payment-method');
            if (await savePaymentMethod.isVisible().catch(() => false)) {
                await savePaymentMethod.click();
                await StorefrontPage.waitForLoadState('domcontentloaded');
            }

            return acceptTermsAndSubmitOrder(StorefrontCheckoutConfirm);
        });

        await step(StorefrontPage, 'Complete the PAY. payment', () => completePaySandbox(
            StorefrontPage,
            expected.sandboxAmount,
        ));

        await step(StorefrontPage, 'Confirm the thank you page', async () => {
            await expect(StorefrontPage).toHaveURL(/checkout\/finish/i, { timeout: 120_000 });
            await confirmPaidOrder(StorefrontPage, StorefrontCheckoutFinish, AdminApiContext, expected);
        });
    });

    test('payment methods with a logo show it on checkout confirm', async ({
        StorefrontPage,
        StorefrontProductDetail,
        StorefrontCheckoutConfirm,
        ProductData,
        DefaultSalesChannel,
        AdminApiContext,
        ShopCustomer,
        Register,
    }) => {
        test.setTimeout(300_000);

        const methods = await step(StorefrontPage, 'Install PAY. payment methods', () => installIndividualPayPaymentMethods(
            AdminApiContext,
            DefaultSalesChannel.salesChannel.id,
        ));

        await reachGuestCheckoutConfirm({
            StorefrontPage,
            StorefrontProductDetail,
            ProductData,
            DefaultSalesChannel,
            AdminApiContext,
            ShopCustomer,
            Register,
        });

        await step(StorefrontPage, 'Check each payment method logo', async () => {
            await expect(StorefrontPage).toHaveURL(/checkout\/confirm/);
            await expect(StorefrontCheckoutConfirm.headline).toBeVisible();

            const methodsWithLogo = methods.filter((method) => method.mediaId !== null);
            expect(methodsWithLogo.length, 'No installed PAY. payment method has a logo').toBeGreaterThan(0);

            for (const method of methods) {
                const label = StorefrontPage.locator(`label.payment-method-label[for="paymentMethod${method.id}"]`);
                await expect(label, `${method.name} is missing from checkout confirm`).toBeVisible();

                const logo = label.locator('> img');
                if (method.mediaId === null) {
                    await expect(logo, `${method.name} has no logo, so no img should be shown`).toHaveCount(0);
                    continue;
                }

                await expect(logo, `${method.name} has a logo but no img inside its payment-method-label`).toHaveCount(1);
                await logo.scrollIntoViewIfNeeded();
                await expect(logo, `${method.name} logo is not displayed`).toBeVisible();
                await expect(logo).toHaveAttribute('src', /\S/);
                await expect.poll(
                    () => logo.evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0),
                    { message: `${method.name} logo did not load` },
                ).toBe(true);
            }
        });
    });
});
