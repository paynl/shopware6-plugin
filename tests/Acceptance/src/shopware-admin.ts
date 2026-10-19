import { expect, type FixtureTypes } from '@shopware-ag/acceptance-test-suite';

type AdminApiContext = FixtureTypes['AdminApiContext'];

export const IDEAL_PAYNL_ID = '10';

/** Id used by the uniform "Pay by PAY." method, not an installed sales-location method. */
const SINGLE_PAYNL_ID = '123456789';

/** Preference order matches PaynlPaymentMethodsIdsEnum::getPayPartsCardPaymentIds(). */
export const PAY_PARTS_CARD_PAYNL_IDS = [
    '11',
    '706',
    '3141',
    '3138',
    '708',
    '2268',
    '710',
    '711',
    '712',
    '715',
    '1705',
    '1939',
    '1945',
] as const;

export type PayPaymentMethod = {
    id: string;
    name: string;
    paynlId: string;
    mediaId: string | null;
};

type SearchResponse<T> = {
    data: T[];
};

type PaynlCustomFields = {
    paynlId?: number | string | null;
};

type PaymentMethodRecord = PayPaymentMethod & {
    active?: boolean;
    customFields?: PaynlCustomFields;
    translated?: { name?: string; customFields?: PaynlCustomFields };
    attributes?: {
        name?: string;
        active?: boolean;
        mediaId?: string | null;
        customFields?: PaynlCustomFields;
        translated?: { name?: string; customFields?: PaynlCustomFields };
        handlerIdentifier?: string;
    };
};

function mapPaymentMethod(method: PaymentMethodRecord): PayPaymentMethod & { active: boolean } {
    const attributes = method.attributes ?? method;
    const paynlId = attributes.customFields?.paynlId
        ?? attributes.translated?.customFields?.paynlId
        ?? method.translated?.customFields?.paynlId;

    const mediaId = attributes.mediaId || method.mediaId || null;

    return {
        id: method.id,
        name: attributes.name || attributes.translated?.name || method.name || '',
        paynlId: paynlId === undefined || paynlId === null || paynlId === '' ? '' : String(paynlId),
        mediaId,
        active: Boolean(attributes.active),
    };
}

export async function findPayPaymentMethods(
    adminApi: AdminApiContext,
    activeOnly = true,
): Promise<Array<PayPaymentMethod & { active: boolean }>> {
    const filter: Array<Record<string, unknown>> = [
        {
            type: 'contains',
            field: 'handlerIdentifier',
            value: 'PaynlPayment',
        },
    ];
    if (activeOnly) {
        filter.push({ type: 'equals', field: 'active', value: true });
    }

    const response = await adminApi.post('./search/payment-method', {
        data: { limit: 500, filter },
    });

    expect(response.ok(), `Payment method search failed: ${response.status()} ${await response.text()}`).toBeTruthy();
    const payload = (await response.json()) as SearchResponse<PaymentMethodRecord>;
    return (payload.data ?? []).map(mapPaymentMethod);
}

type PayPluginConfig = {
    testMode: boolean;
    useSinglePaymentMethod: boolean;
    enablePayPartsCreditCardWidget?: boolean;
};

async function writePayPluginConfig(adminApi: AdminApiContext, config: PayPluginConfig): Promise<void> {
    const tokenCode = process.env.PAY_TOKEN_CODE || '';
    const apiToken = process.env.PAY_API_TOKEN || '';
    const serviceId = process.env.PAY_SERVICE_ID || '';
    expect(tokenCode && apiToken && serviceId, 'PAY_TOKEN_CODE, PAY_API_TOKEN and PAY_SERVICE_ID must be set').toBeTruthy();

    const response = await adminApi.post('./_action/system-config', {
        data: {
            'PaynlPaymentShopware6.config.tokenCode': tokenCode,
            'PaynlPaymentShopware6.config.apiToken': apiToken,
            'PaynlPaymentShopware6.config.serviceId': serviceId,
            'PaynlPaymentShopware6.config.testMode': config.testMode,
            'PaynlPaymentShopware6.config.useSinglePaymentMethod': config.useSinglePaymentMethod,
            'PaynlPaymentShopware6.config.logging': true,
            'PaynlPaymentShopware6.config.paymentScreenLanguage': 'en',
            ...(config.enablePayPartsCreditCardWidget === undefined
                ? {}
                : {
                    'PaynlPaymentShopware6.config.enablePayPartsCreditCardWidget': config.enablePayPartsCreditCardWidget,
                }),
        },
    });
    expect(response.ok(), `Could not write PAY. config: ${response.status()} ${await response.text()}`).toBeTruthy();

    const cache = await adminApi.delete('./_action/cache');
    expect(cache.ok(), `Could not clear the shop cache: ${cache.status()} ${await cache.text()}`).toBeTruthy();
}

export async function setPayPluginConfig(
    adminApi: AdminApiContext,
    values: Record<string, string | boolean>,
): Promise<void> {
    const data: Record<string, string | boolean> = {};
    for (const [key, value] of Object.entries(values)) {
        data[`PaynlPaymentShopware6.config.${key}`] = value;
    }

    const response = await adminApi.post('./_action/system-config', { data });
    expect(response.ok(), `Could not write PAY. config: ${response.status()} ${await response.text()}`).toBeTruthy();

    const cache = await adminApi.delete('./_action/cache');
    expect(cache.ok(), `Could not clear the shop cache: ${cache.status()} ${await cache.text()}`).toBeTruthy();
}

async function assertPluginInstalled(adminApi: AdminApiContext): Promise<void> {
    const response = await adminApi.post('./search/plugin', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'name',
                    value: 'PaynlPaymentShopware6',
                },
            ],
        },
    });
    expect(response.ok(), `Plugin search failed: ${response.status()} ${await response.text()}`).toBeTruthy();
    const payload = (await response.json()) as SearchResponse<{
        name?: string;
        active?: boolean;
        installedAt?: string | null;
        attributes?: { name?: string; active?: boolean; installedAt?: string | null };
    }>;
    const plugin = payload.data[0];
    const attributes = plugin?.attributes ?? plugin;
    const installed = Boolean(attributes?.installedAt);
    const active = Boolean(attributes?.active);

    expect(
        plugin && installed && active,
        'PaynlPaymentShopware6 must be installed and active before tests run. Start the shop, then: npm run env:plugin',
    ).toBeTruthy();
}

async function installPayPaymentMethods(adminApi: AdminApiContext): Promise<void> {
    const response = await adminApi.get(`paynl/install-payment-methods?_nocache=${Date.now()}`, {
        headers: {
            'Cache-Control': 'no-cache, no-store',
            Pragma: 'no-cache',
        },
    });
    const body = await response.text();
    expect(
        response.ok(),
        `Could not install PAY. payment methods (${response.status()}). PaynlPaymentShopware6 routes are missing — install and activate the plugin (npm run env:plugin).`,
    ).toBeTruthy();

    const payload = JSON.parse(body) as { success?: boolean; message?: string };
    expect(payload.success !== false, payload.message || 'install-payment-methods failed').toBeTruthy();
}

function asBool(value: unknown): boolean {
    return value === true || value === 1 || value === '1';
}

async function readPayPluginConfig(adminApi: AdminApiContext): Promise<PayPluginConfig> {
    const response = await adminApi.get('./_action/system-config?domain=PaynlPaymentShopware6.config');
    expect(response.ok(), `Could not read PAY. config: ${response.status()} ${await response.text()}`).toBeTruthy();
    const payload = (await response.json()) as Record<string, unknown>;
    return {
        testMode: asBool(payload['PaynlPaymentShopware6.config.testMode']),
        useSinglePaymentMethod: asBool(payload['PaynlPaymentShopware6.config.useSinglePaymentMethod']),
        enablePayPartsCreditCardWidget: asBool(payload['PaynlPaymentShopware6.config.enablePayPartsCreditCardWidget']),
    };
}

async function ensureMethodActive(
    adminApi: AdminApiContext,
    method: PayPaymentMethod & { active: boolean },
): Promise<void> {
    if (method.active) {
        return;
    }

    const activate = await adminApi.patch(`./payment-method/${method.id}`, { data: { active: true } });
    expect(activate.ok(), `Could not activate ${method.name}: ${activate.status()}`).toBeTruthy();
    method.active = true;
}

async function syncPayPaymentMethods(
    adminApi: AdminApiContext,
    config: PayPluginConfig,
    isReady: (methods: Array<PayPaymentMethod & { active: boolean }>) => boolean,
): Promise<Array<PayPaymentMethod & { active: boolean }>> {
    await assertPluginInstalled(adminApi);
    const current = await readPayPluginConfig(adminApi);
    const configMatches = current.testMode === config.testMode
        && current.useSinglePaymentMethod === config.useSinglePaymentMethod
        && (config.enablePayPartsCreditCardWidget === undefined
            || current.enablePayPartsCreditCardWidget === config.enablePayPartsCreditCardWidget);
    let methods = await findPayPaymentMethods(adminApi, false);

    if (!configMatches || !isReady(methods)) {
        if (!configMatches) {
            await writePayPluginConfig(adminApi, config);
        }
        await installPayPaymentMethods(adminApi);
        methods = await findPayPaymentMethods(adminApi, false);
    }

    return methods;
}

async function assignSalesChannelPaymentMethods(
    adminApi: AdminApiContext,
    salesChannelId: string,
    paymentMethodIds: string[],
): Promise<void> {
    const assignResponse = await adminApi.post('./_action/sync', {
        data: {
            'write-sales-channel-payment-method': {
                entity: 'sales_channel_payment_method',
                action: 'upsert',
                payload: paymentMethodIds.map((paymentMethodId) => ({
                    salesChannelId,
                    paymentMethodId,
                })),
            },
        },
    });
    expect(
        assignResponse.ok(),
        `Could not assign payment methods to the sales channel: ${assignResponse.status()} ${await assignResponse.text()}`,
    ).toBeTruthy();
}

async function setSalesChannelPaymentMethod(
    adminApi: AdminApiContext,
    salesChannelId: string,
    paymentMethodId: string,
): Promise<void> {
    await assignSalesChannelPaymentMethods(adminApi, salesChannelId, [paymentMethodId]);

    const patchResponse = await adminApi.patch(`./sales-channel/${salesChannelId}`, {
        data: {
            paymentMethodId,
        },
    });
    expect(
        patchResponse.ok(),
        `Could not set the sales channel payment method: ${patchResponse.status()} ${await patchResponse.text()}`,
    ).toBeTruthy();
}

type DomainRecord = {
    id: string;
    url?: string;
    languageId?: string;
    currencyId?: string;
    snippetSetId?: string;
    attributes?: {
        url?: string;
        languageId?: string;
        currencyId?: string;
        snippetSetId?: string;
    };
};

export async function ensureStorefrontDomainAliases(
    adminApi: AdminApiContext,
    storefrontUrl: string,
    salesChannelId: string,
): Promise<void> {
    const canonical = storefrontUrl.replace(/\/$/, '');
    const wanted = [canonical];
    if (canonical.startsWith('https://')) {
        wanted.push(canonical.replace(/^https:/, 'http:'));
    }

    const search = await adminApi.post('./search/sales-channel-domain', {
        data: {
            limit: 50,
            filter: [{ type: 'equals', field: 'salesChannelId', value: salesChannelId }],
        },
    });
    expect(search.ok(), `Sales channel domain search failed: ${search.status()} ${await search.text()}`).toBeTruthy();
    const payload = (await search.json()) as SearchResponse<DomainRecord>;
    const existing = payload.data ?? [];
    const known = new Set(
        existing.map((domain) => (domain.attributes?.url ?? domain.url ?? '').replace(/\/$/, '')),
    );
    const template = existing[0];
    expect(template, `Sales channel ${salesChannelId} has no domain to copy`).toBeTruthy();

    for (const url of wanted) {
        if (known.has(url)) {
            continue;
        }
        const create = await adminApi.post('./sales-channel-domain?_response=detail', {
            data: {
                salesChannelId,
                url,
                languageId: template.attributes?.languageId ?? template.languageId,
                currencyId: template.attributes?.currencyId ?? template.currencyId,
                snippetSetId: template.attributes?.snippetSetId ?? template.snippetSetId,
            },
        });
        expect(
            create.ok(),
            `Could not add sales channel domain ${url}: ${create.status()} ${await create.text()}`,
        ).toBeTruthy();
        known.add(url);
    }

    await adminApi.delete('./_action/cache').catch(() => undefined);
}

export async function preparePayPartsCardCheckout(
    adminApi: AdminApiContext,
    salesChannelId: string,
): Promise<PayPaymentMethod> {
    const methods = await syncPayPaymentMethods(
        adminApi,
        { testMode: true, useSinglePaymentMethod: false, enablePayPartsCreditCardWidget: true },
        (installed) => installed.some((method) => method.active && isPayPartsCardPaynlId(method.paynlId)),
    );
    const selected = PAY_PARTS_CARD_PAYNL_IDS
        .map((paynlId) => methods.find((method) => method.paynlId === paynlId))
        .find((method) => method !== undefined);

    if (selected === undefined) {
        throw new Error(
            `No PAY. card method for Pay.Parts. Active methods: ${
                methods.map((method) => `${method.name} (${method.paynlId || 'no id'})`).join(', ')
            }`,
        );
    }

    await ensureMethodActive(adminApi, selected);
    await setSalesChannelPaymentMethod(adminApi, salesChannelId, selected.id);

    return selected;
}

function isPayPartsCardPaynlId(paynlId: string): boolean {
    return (PAY_PARTS_CARD_PAYNL_IDS as readonly string[]).includes(paynlId);
}

export async function installIndividualPayPaymentMethods(
    adminApi: AdminApiContext,
    salesChannelId: string,
): Promise<PayPaymentMethod[]> {
    const methods = await syncPayPaymentMethods(
        adminApi,
        { testMode: true, useSinglePaymentMethod: false },
        (installed) => installed.some((method) => method.active && isInstalledPaynlId(method.paynlId)),
    );
    const installed = methods.filter((method) => method.active && isInstalledPaynlId(method.paynlId));
    expect(
        installed.length,
        'No individual PAY. payment methods were installed.',
    ).toBeGreaterThan(0);
    await assignSalesChannelPaymentMethods(
        adminApi,
        salesChannelId,
        installed.map((method) => method.id),
    );
    await setSalesChannelPaymentMethod(adminApi, salesChannelId, installed[0].id);

    return installed;
}

function isInstalledPaynlId(paynlId: string): boolean {
    return paynlId !== '' && paynlId !== SINGLE_PAYNL_ID;
}

export async function assignPayPaymentMethod(
    adminApi: AdminApiContext,
    salesChannelId: string,
    preferredName = process.env.PAY_PAYMENT_METHOD || 'Pay by PAY.',
): Promise<PayPaymentMethod> {
    const wanted = preferredName === 'Pay by PAY.'
        ? ['Pay by PAY.', 'Mit PAY. bezahlen', 'Betalen met PAY.']
        : [preferredName];
    const matches = (name: string): boolean => wanted.some((candidate) => (
        name === candidate || name.toLowerCase().includes(candidate.toLowerCase())
    ));
    const methods = await syncPayPaymentMethods(
        adminApi,
        { testMode: true, useSinglePaymentMethod: true },
        (installed) => installed.some((method) => method.active && matches(method.name)),
    );
    expect(
        methods.length,
        'No PAY. payment methods found. Install and activate PaynlPaymentShopware6, then retry.',
    ).toBeGreaterThan(0);

    const selected = methods.find((method) => matches(method.name))
        ?? (methods.length === 1 ? methods[0] : undefined);
    if (!selected) {
        throw new Error(
            `No PAY. payment method named "${preferredName}". Active methods: ${methods.map((method) => method.name).join(', ')}`,
        );
    }

    await ensureMethodActive(adminApi, selected);
    await setSalesChannelPaymentMethod(adminApi, salesChannelId, selected.id);

    return selected;
}

export async function assignIdealPaymentMethod(
    adminApi: AdminApiContext,
    salesChannelId: string,
): Promise<PayPaymentMethod> {
    const methods = await syncPayPaymentMethods(
        adminApi,
        { testMode: true, useSinglePaymentMethod: false },
        (installed) => installed.some((method) => method.active && method.paynlId === IDEAL_PAYNL_ID),
    );
    const selected = methods.find((method) => method.paynlId === IDEAL_PAYNL_ID);
    if (!selected) {
        throw new Error(
            `No active PAY. method with paynlId ${IDEAL_PAYNL_ID} (iDEAL). Active methods: ${
                methods.map((method) => `${method.name} (${method.paynlId || 'no id'})`).join(', ')
            }`,
        );
    }

    await ensureMethodActive(adminApi, selected);
    await setSalesChannelPaymentMethod(adminApi, salesChannelId, selected.id);

    return selected;
}

export type PaidOrder = {
    id: string;
    orderNumber: string;
    amountTotal: number;
    currency: { isoCode: string };
    stateMachineState?: { technicalName: string };
    transactions: Array<{
        id: string;
        createdAt?: string;
        amount: { totalPrice: number };
        stateMachineState: { technicalName: string };
        paymentMethod?: { name: string };
    }>;
};

export function latestOrderTransaction(order: Pick<PaidOrder, 'transactions'>): PaidOrder['transactions'][number] {
    const transactions = order.transactions ?? [];
    expect(transactions.length, 'Order has no transactions').toBeGreaterThan(0);
    return [...transactions].sort((left, right) =>
        (right.createdAt ?? '').localeCompare(left.createdAt ?? ''),
    )[0];
}

export async function waitForOrderPaymentState(
    adminApi: AdminApiContext,
    lookup: { orderId?: string; orderNumber?: string | null },
    technicalName: string,
    timeoutMs = Number(process.env.PAY_STATUS_TIMEOUT_MS || 120_000),
): Promise<PaidOrder> {
    const deadline = Date.now() + timeoutMs;
    let lastState = 'unknown';
    const filter = lookup.orderId
        ? [{ type: 'equals', field: 'id', value: lookup.orderId }]
        : [{ type: 'equals', field: 'orderNumber', value: lookup.orderNumber }];
    const label = lookup.orderNumber || lookup.orderId || 'unknown-order';

    while (Date.now() < deadline) {
        const response = await adminApi.post('./search/order', {
            data: {
                limit: 1,
                filter,
                associations: {
                    currency: {},
                    stateMachineState: {},
                    transactions: {
                        sort: [{ field: 'createdAt', order: 'DESC' }],
                        associations: {
                            stateMachineState: {},
                            paymentMethod: {},
                        },
                    },
                },
            },
        });

        expect(response.ok(), `Order search failed: ${response.status()} ${await response.text()}`).toBeTruthy();
        const payload = (await response.json()) as SearchResponse<PaidOrder>;
        const order = payload.data[0];

        if (order) {
            const transaction = order.transactions?.length ? latestOrderTransaction(order) : undefined;
            lastState = transaction?.stateMachineState?.technicalName ?? 'missing-transaction';
            if (lastState === technicalName) {
                return order;
            }
        }

        await new Promise((resolve) => setTimeout(resolve, 3000));
    }

    throw new Error(
        `Shopware transaction for order ${label} did not reach "${technicalName}" within ${timeoutMs}ms (last state: ${lastState})`,
    );
}

export async function waitForPaidOrder(
    adminApi: AdminApiContext,
    lookup: { orderId?: string; orderNumber?: string | null },
    expected: { amount: number; currency: string },
    timeoutMs = Number(process.env.PAY_STATUS_TIMEOUT_MS || 120_000),
): Promise<PaidOrder> {
    const order = await waitForOrderPaymentState(adminApi, lookup, 'paid', timeoutMs);
    const transaction = latestOrderTransaction(order);
    expect(order.currency.isoCode).toBe(expected.currency);
    expect(order.amountTotal).toBeCloseTo(expected.amount, 2);
    expect(transaction.amount.totalPrice).toBeCloseTo(expected.amount, 2);
    return order;
}

type PaynlTransactionRecord = {
    stateId?: number | string;
    paynlTransactionId?: string;
    attributes?: {
        stateId?: number | string;
        paynlTransactionId?: string;
    };
};

export async function readPaynlTransactionState(
    adminApi: AdminApiContext,
    orderId: string,
): Promise<{ stateId: number; paynlTransactionId: string }> {
    const response = await adminApi.post('./search/paynl-transactions', {
        data: {
            limit: 1,
            filter: [{ type: 'equals', field: 'orderId', value: orderId }],
            sort: [{ field: 'createdAt', order: 'DESC' }],
        },
    });
    expect(response.ok(), `PAY. transaction search failed: ${response.status()} ${await response.text()}`).toBeTruthy();
    const payload = (await response.json()) as SearchResponse<PaynlTransactionRecord>;
    const row = payload.data[0];
    const stateId = Number(row?.stateId ?? row?.attributes?.stateId);
    const paynlTransactionId = row?.paynlTransactionId ?? row?.attributes?.paynlTransactionId ?? '';
    expect(paynlTransactionId, `No paynl_transactions row for order ${orderId}`).not.toEqual('');
    expect(Number.isNaN(stateId), `PAY. transaction ${paynlTransactionId} has no stateId`).toBe(false);
    return { stateId, paynlTransactionId };
}

export async function waitForPaynlTransactionState(
    adminApi: AdminApiContext,
    orderId: string,
    expectedStateId: number,
    timeoutMs = Number(process.env.PAY_STATUS_TIMEOUT_MS || 120_000),
): Promise<{ stateId: number; paynlTransactionId: string }> {
    const deadline = Date.now() + timeoutMs;
    let lastStateId = -1;

    while (Date.now() < deadline) {
        const current = await readPaynlTransactionState(adminApi, orderId);
        lastStateId = current.stateId;
        if (current.stateId === expectedStateId) {
            return current;
        }

        await new Promise((resolve) => setTimeout(resolve, 3_000));
    }

    throw new Error(
        `PAY. transaction for order ${orderId} did not reach stateId ${expectedStateId} within ${timeoutMs}ms (last stateId: ${lastStateId})`,
    );
}
