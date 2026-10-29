import { expect, type Locator, type Page, type Response } from '@playwright/test';
import { dismissAdminPopups } from './admin-popups';

/** Shopware 6.7 lists transitions as `li.sw-select-result` with numeric `sw-select-option--*` classes, not `--paid`. */
const PAID_CAPTURE_HIGHLIGHT = /^(Paid(\s*\(Capture\))?|Bezahlt \(Erfassung\)|Betaald \(Capture\))$/i;

function paidCaptureSelectResult(page: Page): Locator {
    return page.locator('li.sw-select-result').filter({
        has: page.locator('.sw-highlight-text').filter({ hasText: PAID_CAPTURE_HIGHLIGHT }),
    });
}

const INTERNAL_COMMENT = /internal comment|interner kommentar|interne opmerking|interne toelichting/i;
const CAPTURE_REJECTED = /PAY\. could not complete/i;

export type CaptureOrderOptions = {
    internalComment?: string;
    /** PAY. is expected to refuse the transition. The admin must show the error. */
    expectAccepted?: boolean;
};

export async function captureAuthorizedOrder(
    page: Page,
    orderId: string,
    options: CaptureOrderOptions = {},
): Promise<void> {
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
    if (options.internalComment !== undefined) {
        await fillInternalComment(modal, options.internalComment);
    }

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
    const shopwareTransition = keepHandled(page.waitForResponse(isOrderTransactionTransition, { timeout: captureTimeoutMs }));

    await confirm.click();

    if (options.expectAccepted === false) {
        const swResponse = await shopwareTransition;
        const body = await swResponse.text();
        expect(swResponse.ok(), `PAY. capture was accepted: ${swResponse.status()} ${body}`).toBeFalsy();
        const notifications = page.locator('.sw-notifications .sw-alert--error');
        await expect(notifications, 'PAY. capture failure did not show an error notification').toBeVisible();
        await expect(notifications).toContainText(CAPTURE_REJECTED);
        return;
    }

    await expect(
        modal,
        'Change status modal stayed open — Shopware did not start the payment transition (pop-up, animation, or admin JS)',
    ).toBeHidden({ timeout: 30_000 });

    const swResponse = await shopwareTransition;
    const body = await swResponse.text();
    expect(
        swResponse.ok(),
        `Payment transition failed. PAY. capture runs inside this request, and Shopware keeps the old status when PAY. refuses it: ${swResponse.status()} ${body}`,
    ).toBeTruthy();
}

async function fillInternalComment(modal: Locator, internalComment: string): Promise<void> {
    const labelled = modal.getByLabel(INTERNAL_COMMENT);
    const field = (await labelled.count()) > 0 ? labelled.first() : modal.locator('textarea').first();
    await expect(field, 'Internal comment field was not shown on the status change modal').toBeVisible();
    await field.fill(internalComment);
}

export async function refundOrder(page: Page, orderId: string): Promise<RefundMessage[]> {
    const refundData = keepHandled(page.waitForResponse(isRefundData, { timeout: 60_000 }));
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

    await dismissAdminPopups(page);
    const refundButton = page.locator('.sw-order-detail__smart-bar-save-button');
    await expect(refundButton).toBeEnabled();
    const refundResponsePromise = keepHandled(page.waitForResponse(isRefund, { timeout: 60_000 }));
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

function keepHandled<T>(pending: Promise<T>): Promise<T> {
    void pending.catch(() => undefined);
    return pending;
}

function isOrderTransactionTransition(response: Response): boolean {
    const url = response.url();

    return (
        response.request().method() === 'POST'
        && (
            /\/api\/_action\/order_transaction\/.+\/state\//.test(url)
            || /\/api\/_action\/state-machine\/order_transaction\//.test(url)
        )
    );
}

function isRefundData(response: Response): boolean {
    return response.url().includes('/paynl/get-refund-data') && response.request().method() === 'GET';
}

function isRefund(response: Response): boolean {
    return response.url().includes('/paynl/refund') && response.request().method() === 'POST' && !response.url().includes('get-refund-data');
}
