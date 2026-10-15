import { pathToFileURL } from 'node:url';
import { expect, type Page } from '@playwright/test';
import { appears } from './locator-helpers';

const PAY_HOST = /pay\.nl|achterelkebetaling\.nl|payments\.nl/i;
const ISSUER_HOST = /ideal\.nl|cloudflare/i;
const WERO_PAY_HOST = /(?:^|\/\/)ext\.pay\.wero\.ideal\.nl(?::\d+)?(?:[/?#]|$)/i;

function isPayHost(href: string): boolean {
    return WERO_PAY_HOST.test(href) || (PAY_HOST.test(href) && !ISSUER_HOST.test(href));
}

function isIssuerHost(href: string): boolean {
    return !WERO_PAY_HOST.test(href) && ISSUER_HOST.test(href);
}

const CHECKOUT_HOST = String.raw`checkout\.(?:pay\.nl|achterelkebetaling\.nl|payments\.nl)`;
const ORDER_UUID = String.raw`[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}`;
const SANDBOX_FORM = new RegExp(
    `^https:\\/\\/${CHECKOUT_HOST}\\/[a-z]{2}-[a-z]{2}\\/sandbox\\/${ORDER_UUID}\\/?$`,
    'i',
);
const ENGLISH_SANDBOX = new RegExp(
    `^https:\\/\\/${CHECKOUT_HOST}\\/en-us\\/sandbox\\/${ORDER_UUID}\\/?$`,
    'i',
);

function sandboxSecret(): string {
    return process.env.PAY_SANDBOX_SECRET || '';
}

function secretField(page: Page) {
    return page.locator('input#secret');
}

async function isCloudflareChallenge(page: Page): Promise<boolean> {
    return page.getByRole('heading', { name: /performing security verification|verify you are human/i })
        .or(page.getByText(/verify you are human/i))
        .first()
        .isVisible()
        .catch(() => false);
}

async function leaveIssuerChallenge(page: Page): Promise<void> {
    if (!isIssuerHost(page.url()) && !(await isCloudflareChallenge(page))) {
        return;
    }

    if (page.url() !== 'about:blank') {
        await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => undefined);
    }
}

async function openSandbox(page: Page): Promise<void> {
    if (SANDBOX_FORM.test(page.url())) {
        return;
    }

    // The order id in /order/{id} is not the sandbox payment id. Choosing Sandbox
    // starts that payment and lands on /{locale}/sandbox/{paymentId}.
    const sandboxMethod = page.locator('a[href*="/pay/with/sandbox"]');
    await expect(sandboxMethod, `Sandbox payment method was not listed at ${page.url()}`).toBeVisible();
    await sandboxMethod.click();
    await expect(page).toHaveURL(SANDBOX_FORM);
}

async function selectAmericanEnglish(page: Page): Promise<void> {
    if (ENGLISH_SANDBOX.test(page.url())) {
        return;
    }

    const english = page.locator('a[lang="en_US"][href*="/en-us/sandbox/"]');
    await expect(english, 'American English was not listed in the sandbox language menu').toBeAttached();
    const href = await english.getAttribute('href');
    if (!href) {
        throw new Error(`American English sandbox link has no href at ${page.url()}`);
    }
    await page.goto(new URL(href, page.url()).href, { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(ENGLISH_SANDBOX);
}

/** Sandbox radio ids. Authorised is the British spelling used by the PAY. form (status 95). */
export type SandboxPaymentStatus = 'captured' | 'authorised';

async function fillSandboxForm(page: Page, amount: string, status: SandboxPaymentStatus): Promise<void> {
    await leaveIssuerChallenge(page);
    await openSandbox(page);
    await selectAmericanEnglish(page);
    await submitSandboxStatus(page, amount, status);
}

async function submitSandboxStatus(page: Page, amount: string, status: SandboxPaymentStatus): Promise<void> {
    const secret = sandboxSecret();
    expect(
        secret,
        'PAY_SANDBOX_SECRET must be the sales-location secret from my.pay.nl (Settings → Sales location), not the API token.',
    ).not.toEqual('');

    const secretInput = secretField(page);
    await expect(secretInput, `Secret field #secret was not shown at ${page.url()}`).toBeVisible({ timeout: 30_000 });
    await secretInput.evaluate((element) => {
        if (element instanceof HTMLInputElement) {
            element.type = 'password';
        }
    });
    await secretInput.fill(secret);

    const statusInput = status === 'captured'
        ? page.locator('input[name="paymentStatus"][value="captured"]#captured')
        : page.locator('input[name="paymentStatus"]#authorised');
    await expect(statusInput, `Sandbox status #${status} was not shown at ${page.url()}`).toBeVisible();
    await statusInput.check();

    const amountInput = page.locator(`input#${status}Amount`);
    if (await appears(amountInput, 2000)) {
        // The selected status keeps its amount input inside a disabled fieldset.
        await amountInput.evaluate((element) => {
            element.closest('fieldset')?.removeAttribute('disabled');
            if (element instanceof HTMLInputElement) {
                element.disabled = false;
            }
        });
        await amountInput.fill(amount);
    }

    const updateButton = page.locator('button[type="submit"]', { hasText: 'Update status' });
    await expect(updateButton).toBeVisible();
    await updateButton.click();

    const invalidSecret = page.getByText(/ungültiges geheimnis|invalid secret|ongeldig geheim/i);
    if (await appears(invalidSecret, 5000)) {
        throw new Error(
            'PAY. rejected the sandbox secret for this sales location. Set PAY_SANDBOX_SECRET to the sales-location secret from my.pay.nl (Settings → Sales location). Do not use PAY_API_TOKEN.',
        );
    }
}

const FINISH_URL = /checkout\/finish|PaynlPayment\/finalize-transaction/i;
const IDEAL_TEST_HANDLER = /https:\/\/handler\.ext\.idealtesttool\.nl(?:\/|$)/i;

/** iDEAL test mode opens Wero, then the iDEAL test handler. Sandbox checkout stays on checkout.pay.nl. */
async function completeWeroIdeal(page: Page): Promise<void> {
    const selectBank = page.getByRole('button', { name: 'Select your bank' });
    await expect(selectBank).toBeVisible();
    await selectBank.click();

    const testBank = page.locator('a[href*="issuerId=TESTNL2A"]');
    await expect(testBank, `TESTNL2A was not listed at ${page.url()}`).toBeVisible();
    await testBank.click();
    await page.waitForURL(IDEAL_TEST_HANDLER, { timeout: 60_000 });

    const success = page.getByRole('button', { name: 'Success', exact: true });
    await expect(success).toBeVisible();
    await success.click();
    await page.waitForURL(FINISH_URL, { timeout: 120_000 });
}

async function returnFromSandbox(page: Page, amount: string, status: SandboxPaymentStatus): Promise<void> {
    if (FINISH_URL.test(page.url())) {
        return;
    }

    const redirected = await page.waitForURL(FINISH_URL, { timeout: 15_000 }).then(() => true).catch(() => false);
    if (redirected) {
        return;
    }

    // Authorised (95) reloads the sandbox. "Continue to the website" is target=_blank,
    // so select Captured / Paid (#captured) and submit in this tab.
    if (status === 'authorised') {
        await submitSandboxStatus(page, amount, 'captured');
        if (!FINISH_URL.test(page.url())) {
            await page.waitForURL(FINISH_URL, { timeout: 60_000 });
        }
        return;
    }

    throw new Error(`Sandbox did not return to the shop after the status update. Current URL: ${page.url()}`);
}

export async function completePaySandbox(
    page: Page,
    amount: string,
    status: SandboxPaymentStatus = 'captured',
): Promise<void> {
    await page.waitForURL(
        (url) => isPayHost(url.href),
        { timeout: 60_000 },
    );
    if (WERO_PAY_HOST.test(page.url())) {
        await completeWeroIdeal(page);
        return;
    }
    await fillSandboxForm(page, amount, status);
    await returnFromSandbox(page, amount, status);
}

function normalizeDecimal(raw: string): string {
    const numeric = raw.replace(/[^\d,.-]/g, '');
    const lastComma = numeric.lastIndexOf(',');
    const lastDot = numeric.lastIndexOf('.');

    if (lastComma >= 0 && lastDot >= 0) {
        return lastComma > lastDot
            ? numeric.replaceAll('.', '').replace(',', '.')
            : numeric.replaceAll(',', '');
    }

    if (lastComma < 0 && lastDot < 0) {
        return numeric;
    }

    const separator = lastComma >= 0 ? ',' : '.';
    const parts = numeric.split(separator);
    const fraction = parts.at(-1) ?? '';
    const decimal = parts.length === 2 && fraction.length > 0 && fraction.length <= 2;
    if (!decimal) {
        return parts.join('');
    }

    return separator === ',' ? `${parts[0]}.${fraction}` : numeric;
}

export function parseAmount(raw: string): { amount: number; currency: string; sandboxAmount: string } {
    const currencyMatch = raw.match(/[€£$]|EUR|GBP|USD/i);
    const currency = currencyMatch?.[0]?.replace('€', 'EUR').replace('£', 'GBP').replace('$', 'USD').toUpperCase() ?? 'EUR';
    const amount = Number.parseFloat(normalizeDecimal(raw));
    return {
        amount,
        currency: currency === 'EUR' || currency === 'GBP' || currency === 'USD' ? currency : 'EUR',
        sandboxAmount: amount.toFixed(2),
    };
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
    const cases: Array<[string, number]> = [
        ['€ 10.00', 10],
        ['€ 10,00', 10],
        ['1.234,56', 1234.56],
        ['1,234.56', 1234.56],
        ['1.234', 1234],
    ];
    for (const [raw, expected] of cases) {
        const parsed = parseAmount(raw);
        if (parsed.amount !== expected || parsed.sandboxAmount !== expected.toFixed(2)) {
            throw new Error(`parseAmount(${JSON.stringify(raw)}) => ${parsed.amount} / ${parsed.sandboxAmount}`);
        }
    }

    const hostCases: Array<[string, boolean, boolean]> = [
        ['https://ext.pay.wero.ideal.nl/10001', true, false],
        ['https://checkout.pay.nl/en-us/sandbox/00000000-0000-0000-0000-000000000000', true, false],
        ['https://www.ideal.nl/issuer', false, true],
        ['https://challenges.cloudflare.com/', false, true],
    ];
    for (const [href, pay, issuer] of hostCases) {
        if (isPayHost(href) !== pay || isIssuerHost(href) !== issuer) {
            throw new Error(`host ${href} => pay ${isPayHost(href)}, issuer ${isIssuerHost(href)}`);
        }
    }
    console.log('parseAmount self-check ok');
}
