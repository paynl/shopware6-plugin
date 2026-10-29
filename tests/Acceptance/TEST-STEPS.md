# Acceptance test steps

These tests run one at a time in Chromium against a Shopware shop with PaynlPaymentShopware6 installed and active. Before the suite, setup installs and activates the plugin and prepares PAY. test payment methods. No live payment is made: the PAY. sandbox uses the sales-location secret in `PAY_SANDBOX_SECRET`.

Two tests are marked fixme and do not run. Playwright reports the reason on each:

- `admin refund of a paid payment refunds it at PAY.` — PAY. refuses the refund for this API token (forbidden). The token needs the refund right in my.pay.nl. No tracking issue yet.
- `Pay.Parts credit card form is shown on checkout confirm` — the card SDK is loaded from PAY. and this test has not been stabilised. No tracking issue yet.

## Shared guest checkout

The checkout tests and the capture/refund tests all reach the confirm page the same way.

1. Through the Admin API, make sure the sales channel has both the `https` and `http` versions of the storefront URL as domain aliases, so the shop recognises the tunnel.
2. Set the Shopware `cookie-preference` cookie (and `cookie-config-hash` when `/cookie/groups` returns a language hash) on the browser context, then open the storefront home page. The cookie banner is not clicked.
3. Open the fixture product’s detail page, set quantity to 1, and click **Add to cart**.
4. In the off-canvas cart, click **Go to checkout**.
5. If the shop shows the register page, turn guest checkout on (uncheck **Create a customer account**, or check the guest option). The Shopware suite then fills and submits the guest registration form. The test waits until the URL is `/checkout/confirm`.

Placing the order then requires the grand total to be visible, reads that text (there is no fallback amount), parses the amount and currency, ticks the first matching terms checkbox when it is visible, and clicks **Submit order**.

## Shared PAY. sandbox

After submit, the browser must land on a PAY. host. The hostname must be `pay.nl`, `achterelkebetaling.nl`, `payments.nl`, a subdomain of those, or `ext.pay.wero.ideal.nl`. A shop URL that only mentions `pay.nl` in the query string does not count. `*.trycloudflare.com` is the shop tunnel, not an iDEAL issuer; the issuer hosts are `ideal.nl` and `challenges.cloudflare.com`.

iDEAL test mode opens `ext.pay.wero.ideal.nl` instead of the sandbox form. On that host the helper clicks **Select your bank**, chooses the `TESTNL2A` bank, and clicks **Success**. It does not open Sandbox, type `PAY_SANDBOX_SECRET`, or select Captured. Those steps apply when the host is `checkout.pay.nl`, `achterelkebetaling.nl`, or `payments.nl`.

1. If an iDEAL issuer page or a Cloudflare challenge is in the way, go back.
2. Click the **Sandbox** payment method. The URL must match `https://checkout.<pay-host>/<locale>/sandbox/<payment-uuid>`.
3. Switch the sandbox page to American English (`/en-us/sandbox/...`).
4. Type `PAY_SANDBOX_SECRET` into `#secret` (the field is switched to a password input first).
5. Select the requested status: **Authorised** (`#authorised`) or **Captured** (`#captured`). Wait until that status’s amount field is enabled, then fill it with the checkout total.
6. Click **Update status**. The test waits until either the invalid-secret message or the return to the shop (continue link, or the thank-you header) is visible. An invalid secret fails the test.
7. Wait until the shop returns to `/checkout/finish` or `/PaynlPayment/finalize-transaction`. **Captured** redirects there directly. **Authorised** reloads the sandbox; the helper follows **Continue to the website** (English, German, or Dutch label) via its `href` in the same tab, then waits for the shop URL.

---

## `checkout-pay.spec.ts` — PAY. checkout

### Guest checkout reaches paid after PAY. sandbox payment

Timeout: 3 minutes.

1. Write PAY. plugin config for this worker’s sales channel (`salesChannelId` on `POST /_action/system-config`): test mode on, **one** payment method on (`useSinglePaymentMethod`), logging on, payment screen language English. Credentials come from `PAY_TOKEN_CODE`, `PAY_API_TOKEN`, and `PAY_SERVICE_ID`. The shop cache is cleared. The global config written by `env:up` is left alone. The same sales channel is passed to `paynl/install-payment-methods` when methods have to be installed.
2. Install PAY. payment methods if they are missing or the config changed. Activate **Pay by PAY.** (also accepted as *Mit PAY. bezahlen* or *Betalen met PAY.*) and set it as the sales channel’s payment method.
3. Run the shared guest checkout until the confirm page. The confirm headline must be visible.
4. Select that payment method by its radio `input[name="paymentMethodId"]`. If the radio has Shopware’s `payment-method-input` class, the change submits the payment form and the test waits for the next document load of `/checkout/confirm`. If the radio is missing, click the method name and wait for that same reload.
5. Accept the terms and submit the order.
6. Complete the PAY. sandbox as **Captured** (the default status).
7. On the thank-you page, the heading must contain “thank you”, “successful”, “danke”, “bedankt”, or “payment successful”.
8. Read the order id, or the order number if the id is missing.
9. Poll the Admin API with `expect.poll` (every 3 seconds, up to 2 minutes) until the order transaction state is `paid`. A missing order is a poll result, not an immediate failure.
10. Assert the order currency and both the order total and the transaction amount match the amount read on the confirm page, and that the transaction state is `paid`.

### iDEAL checkout reaches the thank you page

Timeout: 5 minutes.

1. Write PAY. config for this worker’s sales channel with test mode on and the uniform method **off**, then install the individual methods for that channel.
2. Find the active method whose PAY. id is `10` (iDEAL). Activate it and set it as the sales channel’s only assigned payment method.
3. Run the shared guest checkout until confirm. The headline must be visible.
4. Find the single `.payment-method` block that contains `input[data-paynlid="10"]`. It must be visible.
5. Check that radio if it is not already checked. If it auto-submits (`payment-method-input`), wait for the `/checkout/confirm` document to load again.
6. If a **change payment method** control (`.paynl-change-payment-method`) is visible inside that block, click it and wait for the same reload.
7. Accept the terms and submit the order.
8. Complete the PAY. payment. On the Wero host that is the bank `TESTNL2A` and **Success**. On the classic sandbox host that is **Captured**.
9. The URL must match `/checkout/finish` within 30 seconds.
10. Run the same paid-order checks as the guest checkout test: thank-you heading, then Admin API assertions that the transaction is `paid` and the amount and currency match the confirm page.

### Payment methods with a logo show it on checkout confirm

Timeout: 5 minutes. This test stops on the confirm page and does not pay.

1. Turn the uniform method off for this worker’s sales channel, install the individual PAY. methods for that channel, and assign every active installed method to it. The first one becomes the default. The placeholder id `123456789` (the uniform “Pay by PAY.” method) is excluded.
2. At least one individual method must be installed.
3. Run the shared guest checkout. The URL must contain `/checkout/confirm` and the headline must be visible.
4. At least one of those methods must have a `mediaId` (a logo).
5. For every installed method, the label `label.payment-method-label[for="paymentMethod{id}"]` must be visible.
6. If the method has no logo, that label must contain no direct `img`.
7. If it has a logo, the label must contain exactly one direct `img`. Scroll it into view. It must be visible, its `src` must be non-empty, and the image must finish loading with a natural width greater than 0.

---

## `admin-capture-refund.spec.ts` — PAY. admin capture and refund

The capture tests place an order with the shared guest checkout and the uniform **Pay by PAY.** method (same Admin API setup as the first checkout test). The thank-you page must expose an order id or number. Admin is the suite’s `AdminPage`, already logged in. Shop setup declines the usage-data consents (`product_analytics` and `backend_data`) and sets `SHOPWARE_DISABLE_UPDATE_CHECK`, so those notices are not waited on. A notice that is already on screen is closed without a fixed wait. Video and screenshots stay on for this file; only the plugin-config spec turns them off.

### Admin Paid on an authorised payment marks the Shopware payment paid

Timeout: 5 minutes.

1. Place the order and complete the sandbox as **Authorised**; the helper returns to the shop via the sandbox continue link without capturing at PAY.
2. Poll the Admin API until the Shopware transaction state is `authorize` (up to 2 minutes). The `paynl-transactions` row must have `stateId` `95` (PAY. authorised). A missing row keeps polling.
3. Open the order in admin, dismiss popups, change payment status to **Paid**, type the internal comment `Captured in the PAY. acceptance test`, and confirm. The Shopware payment transition request must succeed. PAY. capture runs inside that request. If PAY. refuses it, the request fails before Shopware stores the new payment status.
4. Poll until the Shopware transaction is `paid`.
5. Poll until the `paynl-transactions` row is `stateId` `100`.
6. The `state-machine-history` entry for that payment’s `paid` transition must contain the internal comment.

### Admin Paid shows an error when PAY. rejects the capture

Timeout: 5 minutes. Same authorised checkout as the capture test.

1. Place the order and complete the sandbox as **Authorised**. Wait until Shopware is `authorize` and the PAY. row is `stateId` `95`.
2. Replace that row’s PAY. order id with `9999999999X99999`, an order PAY. does not know, so the status call inside the Paid transition fails.
3. In admin, change payment status to **Paid** and confirm.
4. The Shopware transition request must fail. The admin error notification (`.sw-notifications .sw-alert--error`) must be visible and say that PAY. could not complete the change.
5. The Shopware payment must still be `authorize`.

### Admin refund of a paid payment refunds it at PAY.

Fixme (`test.fixme`). It does not run. The reason is on the test: PAY. refuses the refund for this API token. Timeout would be 5 minutes. Admin is the suite’s `AdminPage`.

1. Close the Shopware usage-data dialog or the “new Shopware version” notice if one is already on screen. Setup has already declined usage-data consent and turned the update check off, so this does not wait for them to appear.
2. Open **Extensions → PaynlPaymentShopware6** config (`#/sw/extension/config/PaynlPaymentShopware6`).
3. Turn on **Allow refunds** and **Allow Shopware native refunds** (English, German, and Dutch labels are accepted). If either was off, click **Save** and wait for a successful `POST /_action/system-config`. Both checkboxes must end up checked. If both were already on, nothing is saved.
4. In the storefront, place an order and complete the sandbox as **Captured**.
5. Wait until the Shopware transaction is `paid`, then assert the PAY. transaction `stateId` is `100`.
6. Open the plugin refund screen `#/paynl/refund/page/view/{orderId}`.
7. `GET /paynl/get-refund-data` must succeed, with an empty error message and `availableForRefund` greater than 0.
8. If **Allow shipping refund** is visible, click it.
9. Click the refund button (`.sw-order-detail__smart-bar-save-button`) once it is enabled.
10. `POST /paynl/refund` must succeed. The first message must have type `success`. A message containing “forbidden” fails the test (the token cannot refund).
11. Read `paynl-transactions` again. `stateId` must be one of `-72`, `-81`, or `-82`.

---

## `plugin-config.spec.ts` — PAY. plugin config

### Test API Keys connects with the sales location

Timeout: 3 minutes. Requires `PAY_TOKEN_CODE`, `PAY_API_TOKEN`, and `PAY_SERVICE_ID`.

1. Open `#/sw/extension/config/PaynlPaymentShopware6` on the suite’s `AdminPage`. Close the Shopware usage-data dialog or the “new Shopware version” notice if one is already on screen. The Token-Code field must be visible within 60 seconds. This spec turns video and screenshots off because the credential fields are on screen; the inputs are also masked.
2. Confirm **Token-Code**, **API-token**, and **Service-ID** are already filled.
3. Dismiss popups again, then click **Test API Keys** (English, German, or Dutch).
4. Wait for `POST` to a URL containing `/paynl/test-api-keys`. The response must be HTTP OK.
5. The JSON body must have `success: true` and `message` equal to `paynlValidation.messages.correctCredentials`.
6. The admin notification area (`.sw-notifications`) must be visible and contain the success text (“correct credentials”, or the German or Dutch equivalent).

---

## `credit-card-form.spec.ts`

### Pay.Parts credit card form is shown on checkout confirm

Fixme (`test.fixme`). It does not run. The reason is on the test. When it runs, it uses the worker sales channel and the suite’s `TestDataService`, which deletes the customer and product afterwards. It never submits a payment.

1. Write PAY. config for this worker’s sales channel with test mode on, the uniform method off, and the Pay.Parts credit-card widget on. Install payment methods for that sales channel only.
2. Pick the first installed card method in this PAY. id order: `11`, `706`, `3141`, `3138`, `708`, `2268`, `710`, `711`, `712`, `715`, `1705`, `1939`, `1945`. Activate it and set it as that sales channel’s payment method.
3. Create a customer on that sales channel with the card method as `lastPaymentMethodId`. `TestDataService` supplies the address and the password `shopware`. That password, the product’s category, and the later deletion come from `@shopware-ag/acceptance-test-suite` 12.19.0: `getBasicCustomerStruct` uses `shopware`, `getBasicProductStruct` assigns `defaultCategoryId`, the fixture sets that id to the sales channel’s `navigationCategoryId`, and the fixture calls `cleanUp()` unless `ATS_SKIP_CLEANUP` is set. This repository does not assert those three itself.
4. Create an active product in the sales channel’s navigation category, then clear the delayed cache.
5. On the suite storefront, go to `account/login`, accept cookies, log in as that customer, and wait until the login email field disappears.
6. Open `detail/{productId}`, click **Buy**, then **Begin checkout**, and wait for `/checkout/confirm`.
7. The wrapper `.paynl-payparts-card-wrapper` must be visible. Wait up to 60 seconds until `window.PayPartsSDK` exists or `.payparts-cc-gate__ctp` is in the page.
8. If the card error element is visible, reload, accept cookies again, and wait for the SDK once more.
9. The Click to Pay gate must be visible and the error must be hidden.
10. The empty-state text must match “No cards found for {email}.” The word **Or** must be visible.
11. **Use a different e-mail address** must be enabled.
12. **Enter card details manually** must be enabled. Click it. The card form must be visible.
13. **Cardholder name**, **Card number**, **Expiry date**, and **CVC** must be visible text fields. **Pay** must be visible.
14. Type the name `Test Shopper`, the Luhn-invalid number `4111111111111112`, and CVC `123`. Type `1230` into the masked expiry field; the field’s value must be `12/30`. **Pay** must stay disabled.
15. Replace the number with `4111111111111111`. **Pay** must become enabled. The test does not click it.
