# Acceptance test steps

These tests run one at a time in Chromium against a Shopware shop with PaynlPaymentShopware6 installed and active. Before the suite, setup installs and activates the plugin and prepares PAY. test payment methods. No live payment is made: the PAY. sandbox uses the sales-location secret in `PAY_SANDBOX_SECRET`.

Two tests are skipped and do not run:

- `admin refund of a paid payment refunds it at PAY.`
- `Pay.Parts credit card form is shown on checkout confirm`

## Shared guest checkout

The checkout tests and the capture/refund tests all reach the confirm page the same way.

1. Through the Admin API, make sure the sales channel has both the `https` and `http` versions of the storefront URL as domain aliases, so the shop recognises the tunnel.
2. Open the storefront home page. Hide the Symfony profiler, and accept the cookie banner if it is shown.
3. Open the fixture product’s detail page, set quantity to 1, and click **Add to cart**.
4. In the off-canvas cart, click **Go to checkout**.
5. If the shop shows the register page, turn guest checkout on (uncheck **Create a customer account**, or check the guest option). The Shopware suite then fills and submits the guest registration form. The test waits until the URL is `/checkout/confirm`.

Placing the order then reads the grand total, parses the amount and currency, ticks the terms checkbox when it is visible, and clicks **Submit order**.

## Shared PAY. sandbox

After submit, the browser must land on a PAY. host (`pay.nl`, `achterelkebetaling.nl`, `payments.nl`, or `ext.pay.wero.ideal.nl`).

1. If an iDEAL issuer page or a Cloudflare challenge is in the way, go back.
2. Click the **Sandbox** payment method. The URL must match `https://checkout.<pay-host>/<locale>/sandbox/<payment-uuid>`.
3. Switch the sandbox page to American English (`/en-us/sandbox/...`).
4. Type `PAY_SANDBOX_SECRET` into `#secret` (the field is switched to a password input first).
5. Select the requested status: **Authorised** (`#authorised`) or **Captured** (`#captured`). Fill that status’s amount with the checkout total when the amount field is present.
6. Click **Update status**. An “invalid secret” message fails the test immediately.
7. Wait until the shop returns to `/checkout/finish` or `/PaynlPayment/finalize-transaction`. **Authorised** stays on the sandbox (its continue link opens a new tab), so the helper then selects **Captured** and submits again in the same tab before waiting for the shop.

---

## `checkout-pay.spec.ts` — PAY. checkout

### Guest checkout reaches paid after PAY. sandbox payment

Timeout: 3 minutes.

1. Write PAY. plugin config through the Admin API: test mode on, **one** payment method on (`useSinglePaymentMethod`), logging on, payment screen language English. Credentials come from `PAY_TOKEN_CODE`, `PAY_API_TOKEN`, and `PAY_SERVICE_ID`. The shop cache is cleared.
2. Install PAY. payment methods if they are missing or the config changed. Activate **Pay by PAY.** (also accepted as *Mit PAY. bezahlen* or *Betalen met PAY.*) and set it as the sales channel’s payment method.
3. Run the shared guest checkout until the confirm page. The confirm headline must be visible.
4. Select that payment method by its radio `input[name="paymentMethodId"]`. If the radio is missing, click the method name.
5. Accept the terms and submit the order.
6. Complete the PAY. sandbox as **Captured** (the default status).
7. On the thank-you page, the heading must contain “thank you”, “successful”, “danke”, “bedankt”, or “payment successful”.
8. Read the order id, or the order number if the id is missing.
9. Poll the Admin API for up to 2 minutes (every 3 seconds) until the order transaction state is `paid`.
10. Assert the order currency and both the order total and the transaction amount match the amount read on the confirm page, and that the transaction state is `paid`.

### iDEAL checkout reaches the thank you page

Timeout: 5 minutes.

1. Write PAY. config with test mode on and the uniform method **off**, then install the individual methods.
2. Find the active method whose PAY. id is `10` (iDEAL). Activate it and set it as the sales channel’s only assigned payment method.
3. Run the shared guest checkout until confirm. The headline must be visible.
4. Find the single `.payment-method` block that contains `input[data-paynlid="10"]`. It must be visible.
5. Check that radio if it is not already checked, and wait for the page to settle.
6. If a **change payment method** control (`.paynl-change-payment-method`) is visible inside that block, click it.
7. Accept the terms and submit the order.
8. Complete the PAY. sandbox as **Captured**.
9. The URL must match `/checkout/finish` within 30 seconds.
10. Run the same paid-order checks as the guest checkout test: thank-you heading, then Admin API assertions that the transaction is `paid` and the amount and currency match the confirm page.

### Payment methods with a logo show it on checkout confirm

Timeout: 5 minutes. This test stops on the confirm page and does not pay.

1. Turn the uniform method off, install the individual PAY. methods, and assign every active installed method to the sales channel. The first one becomes the default. The placeholder id `123456789` (the uniform “Pay by PAY.” method) is excluded.
2. At least one individual method must be installed.
3. Run the shared guest checkout. The URL must contain `/checkout/confirm` and the headline must be visible.
4. At least one of those methods must have a `mediaId` (a logo).
5. For every installed method, the label `label.payment-method-label[for="paymentMethod{id}"]` must be visible.
6. If the method has no logo, that label must contain no direct `img`.
7. If it has a logo, the label must contain exactly one direct `img`. Scroll it into view. It must be visible, its `src` must be non-empty, and the image must finish loading with a natural width greater than 0.

---

## `admin-capture-refund.spec.ts` — PAY. admin capture and refund

Both tests place an order with the shared guest checkout and the uniform **Pay by PAY.** method (same Admin API setup as the first checkout test). The thank-you page must expose an order id or number.

### Admin Paid on an authorised payment captures it

Timeout: 5 minutes.

1. Place the order and complete the sandbox as **Authorised**, which the sandbox helper then finishes as **Captured** so the browser can return to the shop.
2. Poll the Admin API until the Shopware transaction state is `paid` (up to 2 minutes).
3. Load the `paynl-transactions` row for that order. It must have a PAY. transaction id, and its `stateId` must be `100` (PAY. paid).

### Admin refund of a paid payment refunds it at PAY.

Skipped (`test.skip`). Timeout would be 5 minutes. It opens a separate admin browser, separate from the storefront session.

1. Open `/admin/#/login`, sign in with `SHOPWARE_ADMIN_USERNAME` / `SHOPWARE_ADMIN_PASSWORD` (defaults `admin` / `shopware`), and wait until the URL hash is no longer the login screen.
2. Dismiss the Shopware usage-data dialog (**Reject all**) and the “new Shopware version” dialog (**Cancel**), and hide the profiler.
3. Open **Extensions → PaynlPaymentShopware6** config (`#/sw/extension/config/PaynlPaymentShopware6`).
4. Turn on **Allow refunds** and **Allow Shopware native refunds** (English, German, and Dutch labels are accepted). If either was off, click **Save** and wait for a successful `POST /_action/system-config`. Both checkboxes must end up checked. If both were already on, nothing is saved.
5. In the storefront, place an order and complete the sandbox as **Captured**.
6. Wait until the Shopware transaction is `paid`, then assert the PAY. transaction `stateId` is `100`.
7. Open the plugin refund screen `#/paynl/refund/page/view/{orderId}`.
8. `GET /paynl/get-refund-data` must succeed, with an empty error message and `availableForRefund` greater than 0.
9. If **Allow shipping refund** is visible, click it.
10. Click the refund button (`.sw-order-detail__smart-bar-save-button`) once it is enabled.
11. `POST /paynl/refund` must succeed. The first message must have type `success`. A message containing “forbidden” fails the test (the token cannot refund).
12. Read `paynl-transactions` again. `stateId` must be one of `-72`, `-81`, or `-82`.
13. Close the admin browser context.

---

## `plugin-config.spec.ts` — PAY. plugin config

### Test API Keys connects with the sales location

Timeout: 3 minutes. Requires `PAY_TOKEN_CODE`, `PAY_API_TOKEN`, and `PAY_SERVICE_ID`.

1. Open a new admin browser and log in the same way as the refund test, including dismissing consent and update popups.
2. Open `#/sw/extension/config/PaynlPaymentShopware6`. The Token-Code field must be visible within 60 seconds.
3. Fill **Token-Code**, **API-token**, and **Service-ID** from those environment variables.
4. Dismiss popups again, then click **Test API Keys** (English, German, or Dutch).
5. Wait for `POST` to a URL containing `/paynl/test-api-keys`. The response must be HTTP OK.
6. The JSON body must have `success: true` and `message` equal to `paynlValidation.messages.correctCredentials`.
7. The admin notification area (`.sw-notifications`) must be visible and contain the success text (“correct credentials”, or the German or Dutch equivalent).
8. Close the admin browser context.

---

## `credit-card-form.spec.ts`

### Pay.Parts credit card form is shown on checkout confirm

Skipped (`test.skip`). It builds its own customer and product, and it never submits a payment.

1. Search sales-channel domains and pick the one whose URL matches `APP_URL`. It must be a storefront sales channel. Load that channel’s country. For Belgium, Germany, France, Great Britain, the Netherlands, or the United States, a street address is predefined; any other country fails the test. If the country forces a state, the first country state is loaded.
2. Write PAY. config with test mode on, the uniform method off, and the Pay.Parts credit-card widget on. Install payment methods.
3. Pick the first installed card method in this PAY. id order: `11`, `706`, `3141`, `3138`, `708`, `2268`, `710`, `711`, `712`, `715`, `1705`, `1939`, `1945`. Activate it and set it as the sales channel payment method.
4. Create a customer `payparts-{id}@example.com` with password `shopware`, name Pay Parts, that billing and shipping address, and the card method as the default payment method.
5. Create an active product “PayParts card {id}” at gross 10 / net 8.40, stock 10, in the sales channel’s navigation category, then clear the delayed cache.
6. Open a new browser context on `APP_URL`, add `ngrok-skip-browser-warning` on requests to that host, and hide the profiler.
7. Go to `account/login`, accept cookies, log in as that customer, and wait until the login email field disappears.
8. Open `detail/{productId}`, click **Buy**, then **Begin checkout**, and wait for `/checkout/confirm`.
9. The wrapper `.paynl-payparts-card-wrapper` must be visible. Wait up to 60 seconds until `window.PayPartsSDK` exists or `.payparts-cc-gate__ctp` is in the page.
10. If the card error element is visible, reload, accept cookies again, and wait for the SDK once more.
11. The Click to Pay gate must be visible and the error must be hidden. The expanded slot and `.payparts-ctp-wrapper[data-method-id="1"]` must be visible.
12. `#click-to-pay-form` must be hidden (classes `is-hidden` and `hidden`). `#click-to-pay-cards` must be hidden.
13. The empty-state text must match “No cards found for {email}.” The word **Or** must be visible.
14. **Use a different e-mail address** must be enabled (clicked as a trial only, so it is not actually activated).
15. **Enter card details manually** must be enabled. A trial click checks it, then a real click opens the form.
16. `form.cc-form` must be visible and have `novalidate`.
17. **Cardholder name**: visible, placeholder “Full name on card”, autocomplete `cc-name`.
18. **Card number**: visible, placeholder `1234 5678 9012 3456`, maxlength `19`, input mode `numeric`.
19. Brand icons must be in the form for postepay, carte-bleue-high-risk, maestro, amex, dankort, nexi, mastercard, and visa.
20. **Expiry date**: visible, placeholder `MM / YY`, maxlength `9`.
21. **CVC**: visible, placeholder `CVC`, maxlength `4`.
22. The pay button `#cc-submit-btn` must be visible, contain “Pay”, and have the class `cc-pay-disabled`. A spinner element must be attached.
23. Delete the product and the customer.
