# Playwright acceptance tests

These tests start a temporary Shopware shop, install this plugin revision, take a guest through checkout in PAY. test mode, and check that the Shopware transaction becomes **paid**. No live money is moved.

## GitHub Actions

The workflow runs on **pull requests** that touch `src/**`, `tests/Acceptance/**`, or `.github/workflows/playwright.yml`, on a **nightly schedule** (02:00 UTC on `master`), and via **workflow_dispatch**. A pull request that only changes the workflow file runs the suite, so a workflow edit is tested on that pull request. Fork pull requests and Dependabot are skipped: they do not receive repository secrets, and the suite cannot reach PAY. without them. Each ref has its own concurrency group so one branch does not cancel another’s run.

Each run starts Shopware with `APP_ENV=prod` (no Symfony profiler) and sets a **random admin password** before the public HTTPS tunnel opens.

1. In the GitHub repository, open **Settings → Secrets and variables → Actions** and add:

   | Secret | Purpose |
   | --- | --- |
   | `PAY_TOKEN_CODE` | Token code (`AT-xxxx-xxxx`) |
   | `PAY_API_TOKEN` | API token |
   | `PAY_SERVICE_ID` | Sales location (`SL-xxxx-xxxx`) |
   | `PAY_SANDBOX_SECRET` | Sales-location secret from my.pay.nl (Settings → Sales location). Typed into the PAY. sandbox. This is not the API token. |
   | `NGROK_AUTHTOKEN` | Optional. When set, the workflow uses ngrok for the public HTTPS callback URL. Otherwise it uses a Cloudflare quick tunnel. |

   Use a PAY. **test** sales location, not a live one.

2. Open **Actions → Playwright PAY. checkout → Run workflow**.
3. Choose the plugin branch, tag, or SHA to test. Leave the Shopware version empty to run `6.6.10.25` and `6.7.10.0`. Set one version to run only that version. A local `env:up` still defaults to `6.7.10.0`.
4. Each run creates a fresh Shopware container, installs that revision, and tears the shop down afterwards.
5. The HTML report, failure screenshots, traces, videos, and Shopware logs are uploaded as the `playwright-pay-checkout-<shopware-version>` artifact, including when the tests fail. The upload runs only after secret redaction succeeds. A failed redaction skips the upload.

The shop is published on a public HTTPS URL for the duration of the run so PAY. can send payment notifications to `/PaynlPayment/notify`.

## Local

Requirements: Docker, Node.js 24+, and either [cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/) or [ngrok](https://ngrok.com/download). Node 24 is required by `@shopware-ag/acceptance-test-suite`.

```bash
cd tests/Acceptance
cp .env.example .env
# Fill PAY_TOKEN_CODE, PAY_API_TOKEN, PAY_SERVICE_ID, and PAY_SANDBOX_SECRET (sales-location secret, not the API token).
npm ci
npx playwright install chromium
npm run env:up
npm test
npm run env:down
```

`env:up` starts Shopware `6.7.10.0`, opens a public HTTPS tunnel, **installs and activates PaynlPaymentShopware6**, writes PAY. test-mode config, and installs the uniform **Pay by PAY.** method. It also trusts the tunnel proxy headers, turns the storefront HTTP cache on, turns the Pay.Parts credit-card widget on, and sets the shop’s minimum password length to 8.

Playwright also runs that plugin install before the tests (`npm run env:plugin`). If you already have Shopware running:

```bash
npm run env:plugin
npm test
```

To use an existing shop instead of `env:up`, set `APP_URL` and `ADMIN_API_URL` in `.env` to the same public HTTPS URL with a trailing slash. Install the plugin yourself, enable test mode, and install payment methods. PAY. still needs that public HTTPS callback URL. `env:up` writes both values; Playwright reads them from `.env`.

Open the last HTML report with `npm run report` (port 9324). If that port is busy, use `npx playwright show-report --port 9325`.
