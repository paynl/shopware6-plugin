import { expect, type Locator, type Page, type Response } from '@playwright/test';
import { dismissAdminPopups } from './admin-popups';

/** Shopware 6.7 lists transitions as `li.sw-select-result` with numeric `sw-select-option--*` classes, not `--paid`. */
const PAID_CAPTURE_HIGHLIGHT = /^(Paid(\s*\(Capture\))?|Bezahlt \(Erfassung\)|Betaald \(Capture\))$/i;

function paidCaptureSelectResult(page: Page): Locator {
    return page.locator('li.sw-select-result').filter({
        has: page.locator('.sw-highlight-text').filter({ hasText: PAID_CAPTURE_HIGHLIGHT }),
    });
}

export async function captureAuthorizedOrder(page: Page, orderId: string): Promise<void> {
    await page.goto(`./#/sw/order/detail/${orderId}/general`, { waitUntil: 'domcontentloaded' });
    await dismissAdminPopups(page);

    const payment = page.locator('.sw-order-general-info__order-state-payment');
    await expect(payment, 'Payment status select was not shown on the order').toBeVisible({ timeout: 60_000 });
    await payment.locator('.sw-single-select__selection').click();

    const paid = paidCaptureSelectResult(page);
    await expect(paid.first(), 'Paid capture was not offered in the payment status list').toBeVisible({ timeout: 60_000 });
    await paid.first().click();

    const modal = page.locator('.sw-order-state-change-modal');
    await expect(modal).toBeVisible();
    const confirm = modal.getByRole('button', {
        name: /^(update status|status aktualisieren|status bijwerken)$/i,
    });
    await expect(confirm).toBeVisible();
    await expect(confirm).toBeEnabled();
    await confirm.scrollIntoViewIfNeeded();

    const sendMail = modal.getByRole('checkbox', { name: /send email|e-mail.*customer|mail/i });
    if (await sendMail.isChecked().catch(() => false)) {
        await sendMail.uncheck();
    }

    const captureTimeoutMs = Number(process.env.PAY_STATUS_TIMEOUT_MS || 120_000);
    const shopwareTransition = page.waitForResponse(isOrderTransactionTransition, { timeout: captureTimeoutMs });
    const payCapture = page.waitForResponse(isChangeTransactionStatus, { timeout: captureTimeoutMs });

    await confirm.click();

    await expect(
        modal,
        'Change status modal stayed open — Shopware did not start the payment transition (pop-up, animation, or admin JS)',
    ).toBeHidden({ timeout: 30_000 });

    const swResponse = await shopwareTransition;
    expect(swResponse.ok(), `Shopware transition failed: ${swResponse.status()} ${await swResponse.text()}`).toBeTruthy();

    const response = await payCapture;
    const body = await response.text();
    expect(response.ok(), `Capture status change failed: ${response.status()} ${body}`).toBeTruthy();
    const payload = JSON.parse(body) as { currentActionName?: string };
    expect(payload.currentActionName).toBe('paid');
}

export async function refundOrder(page: Page, orderId: string): Promise<RefundMessage[]> {
    const refundData = page.waitForResponse(isRefundData, { timeout: 60_000 });
    await page.goto(`./#/paynl/refund/page/view/${orderId}`, { waitUntil: 'domcontentloaded' });
    await dismissAdminPopups(page);

    const dataResponse = await refundData;
    const dataBody = await dataResponse.text();
    expect(dataResponse.ok(), `Refund data failed: ${dataResponse.status()} ${dataBody}`).toBeTruthy();
    const data = JSON.parse(dataBody) as { availableForRefund?: number | string; errorMessage?: string };
    expect(data.errorMessage ?? '').toEqual('');
    expect(Number(data.availableForRefund), 'Nothing is available to refund').toBeGreaterThan(0);

    const shipping = page.getByText(
        /allow shipping refund|versandrückerstattung zulassen|restitutie van verzendkosten toestaan/i,
    );
    if (await shipping.isVisible().catch(() => false)) {
        await shipping.click();
    }

    await dismissAdminPopups(page, 2_000);
    const refundButton = page.locator('.sw-order-detail__smart-bar-save-button');
    await expect(refundButton).toBeEnabled();
    const refundResponsePromise = page.waitForResponse(isRefund, { timeout: 60_000 });
    await refundButton.click();

    const refundResponse = await refundResponsePromise;
    const refundBody = await refundResponse.text();
    expect(refundResponse.ok(), `Refund failed: ${refundResponse.status()} ${refundBody}`).toBeTruthy();
    return JSON.parse(refundBody) as RefundMessage[];
}

export type RefundMessage = { type?: string; content?: string };

/** Fails before later order-state checks when PAY refuses the refund. */
export function assertPayRefundAccepted(messages: RefundMessage[]): void {
    const content = messages[0]?.content ?? '';
    expect(
        content,
        'PAY. refused the refund. The plugin refund switches are on and the order can be read, but this API token cannot refund the payment. Enable the refund right for the token in my.pay.nl.',
    ).not.toMatch(/forbidden/i);
    expect(messages[0]?.type, content).toBe('success');
}

function isOrderTransactionTransition(response: Response): boolean {
    return (
        response.request().method() === 'POST'
        && /\/api\/_action\/state-machine\/order_transaction\//.test(response.url())
    );
}

function isChangeTransactionStatus(response: Response): boolean {
    return response.url().includes('/paynl/change-transaction-status') && response.request().method() === 'POST';
}

function isRefundData(response: Response): boolean {
    return response.url().includes('/paynl/get-refund-data') && response.request().method() === 'GET';
}

function isRefund(response: Response): boolean {
    return response.url().includes('/paynl/refund') && response.request().method() === 'POST' && !response.url().includes('get-refund-data');
}
