/**
 * Builds the storefront JS of the plugin: one bundle that runs on Shopware 6.5, 6.6 and 6.7.
 *
 * Usage (in this folder): npm ci && npm run build
 * Commit both files in dist/storefront/js afterwards; shops use them as they are.
 */
import { build } from 'esbuild';
import { copyFileSync } from 'node:fs';

const DIST = 'dist/storefront/js';

// Path read by Shopware 6.6 and 6.7.
const BUNDLE = `${DIST}/paynl-payment-shopware6/paynl-payment-shopware6.js`;

// Path read by Shopware 6.5.
const BUNDLE_65 = `${DIST}/paynl-payment-shopware6.js`;

await build({
    entryPoints: ['src/main.js'],
    outfile: BUNDLE,
    bundle: true,
    minify: true,
    target: 'es2020',
    define: { 'process.env.NODE_ENV': '"production"' },
    logLevel: 'info',
});

copyFileSync(BUNDLE, BUNDLE_65);
console.log(`  copied to ${BUNDLE_65}`);
