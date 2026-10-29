<?php

declare(strict_types=1);

/**
 * Remove PAY. credentials, the Shopware admin password, Bearer tokens,
 * and the admin API refresh token from test artifacts before they are uploaded.
 */

const MIN_SECRET_LENGTH = 2;
const MASK = '***';
const SECRET_KEYS = [
    'PAY_SANDBOX_SECRET',
    'PAY_API_TOKEN',
    'PAY_TOKEN_CODE',
    'PAY_SERVICE_ID',
    'NGROK_AUTHTOKEN',
    'SHOPWARE_ADMIN_PASSWORD',
];
// Shopware admin OAuth access tokens are JWTs. Traces store them as Authorization headers.
const BEARER_TOKEN_PATTERN = '/Bearer\s+([A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,}\.[A-Za-z0-9_-]{16,})/';
// The refresh token is an opaque hex id (league/oauth2-server), not a JWT, and is never a Bearer header.
// Traces store the login body raw, once JSON-escaped, or as refresh_token=<hex>.
const REFRESH_TOKEN_PATTERN = '/refresh_token(?:\\\\?"\s*:\s*\\\\?"|=)([0-9a-fA-F]{32,})/';
// HtmlReporter appends the report zip here. Plaintext search cannot see error text inside it.
const REPORT_PREFIX = '<template id="playwrightReportBase64">data:application/zip;base64,';
const REPORT_SUFFIX = '</template>';

/**
 * @return list<string>
 */
function needlesFor(string $secret): array
{
    $encoded = json_encode($secret, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEscaped = is_string($encoded) ? substr($encoded, 1, -1) : $secret;
    $variants = [
        $secret,
        rawurlencode($secret),
        str_replace('%20', '+', rawurlencode($secret)),
        $jsonEscaped,
    ];

    $needles = [];
    foreach ($variants as $variant) {
        if (strlen($variant) >= MIN_SECRET_LENGTH) {
            $needles[$variant] = $variant;
        }
    }

    return array_values($needles);
}

/**
 * @return list<string>
 */
function collectNeedles(): array
{
    $found = [];
    foreach (SECRET_KEYS as $key) {
        $raw = getenv($key);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        foreach (needlesFor($raw) as $needle) {
            $found[$needle] = $needle;
        }
    }

    $tokenCode = getenv('PAY_TOKEN_CODE');
    $apiToken = getenv('PAY_API_TOKEN');
    if (is_string($tokenCode) && $tokenCode !== '' && is_string($apiToken) && $apiToken !== '') {
        // PayNL\Sdk\AuthAdapter\Basic::getHeaderString() sends base64(username:password).
        foreach (needlesFor(base64_encode($tokenCode . ':' . $apiToken)) as $needle) {
            $found[$needle] = $needle;
        }
    }

    $needles = array_values($found);
    usort($needles, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return $needles;
}

function isScrubbableText(string $data): bool
{
    return !str_contains(substr($data, 0, 8192), "\0") && preg_match('//u', $data) === 1;
}

/**
 * @return list<string>
 */
function bearerTokensIn(string $data): array
{
    if (!isScrubbableText($data) || preg_match_all(BEARER_TOKEN_PATTERN, $data, $matches) < 1) {
        return [];
    }

    $tokens = [];
    foreach ($matches[1] as $token) {
        $tokens[$token] = $token;
    }

    return array_values($tokens);
}

/**
 * @return list<string>
 */
function refreshTokensIn(string $data): array
{
    if (!isScrubbableText($data) || preg_match_all(REFRESH_TOKEN_PATTERN, $data, $matches) < 1) {
        return [];
    }

    $tokens = [];
    foreach ($matches[1] as $token) {
        $tokens[$token] = $token;
    }

    return array_values($tokens);
}

/**
 * @return list<string>
 */
function recordedTokensIn(string $data): array
{
    $tokens = [];
    foreach (bearerTokensIn($data) as $token) {
        $tokens[$token] = $token;
    }
    foreach (refreshTokensIn($data) as $token) {
        $tokens[$token] = $token;
    }

    return array_values($tokens);
}

/**
 * @param list<string> $needles
 * @param list<string> $bearerTokens
 */
function scrubBytes(string $data, array $needles, array $bearerTokens = []): string
{
    if (!isScrubbableText($data)) {
        return $data;
    }

    $updated = $data;
    foreach ($needles as $needle) {
        $updated = str_replace($needle, MASK, $updated);
    }
    foreach ($bearerTokens as $token) {
        $updated = str_replace($token, MASK, $updated);
    }

    return $updated;
}

/**
 * @param list<string> $needles
 */
function payloadHasSecret(string $data, array $needles): bool
{
    foreach ($needles as $needle) {
        if (str_contains($data, $needle)) {
            return true;
        }
    }

    return false;
}

function isZip(string $path): bool
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return false;
    }
    $magic = fread($handle, 4);
    fclose($handle);

    return in_array($magic, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true);
}

/**
 * @param list<string> $needles
 * @param list<string> $bearerTokens
 */
function scrubZip(string $path, array $needles, array $bearerTokens): void
{
    $source = new ZipArchive();
    if ($source->open($path) !== true) {
        return;
    }

    $entries = [];
    $changed = false;
    for ($index = 0; $index < $source->numFiles; $index++) {
        $name = $source->getNameIndex($index);
        if (!is_string($name)) {
            continue;
        }
        $payload = $source->getFromIndex($index);
        if ($payload === false) {
            continue;
        }
        if (str_ends_with($name, '/')) {
            $entries[] = [$name, ''];
            continue;
        }
        $scrubbed = scrubBytes($payload, $needles, $bearerTokens);
        if ($scrubbed !== $payload) {
            $changed = true;
        }
        $entries[] = [$name, $scrubbed];
    }
    $source->close();

    if (!$changed) {
        return;
    }

    $temporary = $path . '.redacting';
    $rewritten = new ZipArchive();
    if ($rewritten->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fwrite(STDERR, "Could not rewrite zip {$path}\n");
        exit(1);
    }
    foreach ($entries as [$name, $payload]) {
        if (str_ends_with($name, '/')) {
            $rewritten->addEmptyDir(rtrim($name, '/'));
            continue;
        }
        $rewritten->addFromString($name, $payload);
    }
    $rewritten->close();
    rename($temporary, $path);
}

/**
 * @param list<string> $needles
 */
function zipHasSecret(string $path, array $needles): bool
{
    $archive = new ZipArchive();
    if ($archive->open($path) !== true) {
        $contents = file_get_contents($path);

        return is_string($contents) && payloadHasCredential($contents, $needles);
    }

    try {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $payload = $archive->getFromIndex($index);
            if (is_string($payload) && payloadHasCredential($payload, $needles)) {
                return true;
            }
        }
    } finally {
        $archive->close();
    }

    return false;
}

/**
 * @param list<string> $needles
 */
function payloadHasCredential(string $data, array $needles): bool
{
    return payloadHasSecret($data, $needles) || recordedTokensIn($data) !== [];
}

/**
 * @return array{zip: string, start: int, end: int}|null
 */
function embeddedReport(string $data): ?array
{
    $start = strpos($data, REPORT_PREFIX);
    if ($start === false) {
        return null;
    }
    $payloadStart = $start + strlen(REPORT_PREFIX);
    $end = strpos($data, REPORT_SUFFIX, $payloadStart);
    if ($end === false) {
        return null;
    }
    $zip = base64_decode(substr($data, $payloadStart, $end - $payloadStart), true);
    if (!is_string($zip) || !str_starts_with($zip, "PK")) {
        return null;
    }

    return ['zip' => $zip, 'start' => $payloadStart, 'end' => $end];
}

/**
 * @template T
 * @param callable(string): T $callback
 * @return T|null
 */
function withZipBytes(string $bytes, callable $callback): mixed
{
    $path = tempnam(sys_get_temp_dir(), 'paynl-zip-');
    if ($path === false) {
        return null;
    }
    file_put_contents($path, $bytes);
    try {
        return $callback($path);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * @param list<string> $needles
 * @param list<string> $bearerTokens
 */
function scrubEmbeddedReport(string $data, array $needles, array $bearerTokens): string
{
    $report = embeddedReport($data);
    if ($report === null) {
        return $data;
    }
    $rewritten = withZipBytes($report['zip'], static function (string $path) use ($needles, $bearerTokens): ?string {
        scrubZip($path, $needles, $bearerTokens);
        $updated = file_get_contents($path);

        return is_string($updated) ? $updated : null;
    });
    if (!is_string($rewritten)) {
        return $data;
    }

    return substr($data, 0, $report['start']) . base64_encode($rewritten) . substr($data, $report['end']);
}

/**
 * @param list<string> $needles
 */
function embeddedReportHasCredential(string $data, array $needles): bool
{
    $report = embeddedReport($data);
    if ($report === null) {
        return false;
    }
    $found = withZipBytes($report['zip'], static fn (string $path): bool => zipHasSecret($path, $needles));

    return $found === null || $found;
}

/**
 * Access tokens are recorded on the login response and again as Authorization: Bearer.
 * The opaque refresh token is recorded on that same response and sent back as refresh_token.
 * Collect both, then replace that exact value everywhere.
 *
 * @return list<string>
 */
function collectBearerTokens(string $root): array
{
    $found = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (isZip($path)) {
            $archive = new ZipArchive();
            if ($archive->open($path) !== true) {
                continue;
            }
            try {
                for ($index = 0; $index < $archive->numFiles; $index++) {
                    $payload = $archive->getFromIndex($index);
                    if (!is_string($payload)) {
                        continue;
                    }
                    foreach (recordedTokensIn($payload) as $token) {
                        $found[$token] = $token;
                    }
                }
            } finally {
                $archive->close();
            }
            continue;
        }

        $payload = file_get_contents($path);
        if (!is_string($payload)) {
            continue;
        }
        foreach (recordedTokensIn($payload) as $token) {
            $found[$token] = $token;
        }
    }

    $tokens = array_values($found);
    usort($tokens, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return $tokens;
}

/**
 * @param list<string> $needles
 *
 * @return list<string>
 */
function scrubTree(string $root, array $needles): array
{
    if (!is_dir($root)) {
        return [];
    }

    $bearerTokens = collectBearerTokens($root);
    $leftovers = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $path = $file->getPathname();
        if (isZip($path)) {
            scrubZip($path, $needles, $bearerTokens);
            if (zipHasSecret($path, $needles)) {
                $leftovers[] = $path;
            }
            continue;
        }

        $original = file_get_contents($path);
        if (!is_string($original)) {
            continue;
        }
        $scrubbed = scrubEmbeddedReport(scrubBytes($original, $needles, $bearerTokens), $needles, $bearerTokens);
        if ($scrubbed !== $original) {
            file_put_contents($path, $scrubbed);
        }
        if (payloadHasCredential($scrubbed, $needles) || embeddedReportHasCredential($scrubbed, $needles)) {
            $leftovers[] = $path;
        }
    }

    return $leftovers;
}

function selfCheck(): int
{
    if (!in_array('SHOPWARE_ADMIN_PASSWORD', SECRET_KEYS, true)) {
        fwrite(STDERR, "self-check: SHOPWARE_ADMIN_PASSWORD missing from SECRET_KEYS\n");

        return 1;
    }

    $secret = 'sandbox/secret value';
    $encoded = rawurlencode($secret);
    $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJhZG1pbiJ9.signaturevaluehere';
    $refresh = str_repeat('0123456789abcdef', 5);
    $decoy = str_repeat('fedcba9876543210', 5);
    $tokenCode = 'AT-0000-0000';
    $apiToken = 'secret-api-token';
    $basic = base64_encode($tokenCode . ':' . $apiToken);
    $savedCode = getenv('PAY_TOKEN_CODE');
    $savedToken = getenv('PAY_API_TOKEN');
    putenv('PAY_TOKEN_CODE=' . $tokenCode);
    putenv('PAY_API_TOKEN=' . $apiToken);
    try {
        if (!in_array($basic, collectNeedles(), true)) {
            fwrite(STDERR, "self-check: basic credential missing from needles\n");

            return 1;
        }
    } finally {
        $savedCode === false ? putenv('PAY_TOKEN_CODE') : putenv('PAY_TOKEN_CODE=' . $savedCode);
        $savedToken === false ? putenv('PAY_API_TOKEN') : putenv('PAY_API_TOKEN=' . $savedToken);
    }
    $needles = array_values(array_unique([...needlesFor($secret), ...needlesFor($basic)]));
    usort($needles, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    $root = sys_get_temp_dir() . '/paynl-redact-' . bin2hex(random_bytes(4));
    mkdir($root);
    try {
        file_put_contents($root . '/trace.network', '{"value":"' . $secret . '"}' . "\n");
        file_put_contents($root . '/note.txt', 'body=' . $encoded . "\n");
        file_put_contents($root . '/pay.log', 'Authorization: Basic ' . $basic . "\n");
        file_put_contents(
            $root . '/login.json',
            '{"refresh_token":"' . $refresh . '","grant_type":"refresh_token"}' . "\n"
        );
        file_put_contents(
            $root . '/keep.txt',
            "Authorization: Bearer \${token}\naaaaaaaab.bbbbbbbb.cccccccc\n"
            . $decoy . "\n"
            . '"refresh_token":"abcd"' . "\n"
        );
        $zipPath = $root . '/trace.zip';
        $archive = new ZipArchive();
        $archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('0-trace.trace', (string) json_encode(['params' => ['value' => $secret]], JSON_UNESCAPED_SLASHES));
        $archive->addFromString('1-trace.network', 'Authorization: Bearer ' . $jwt . "\n");
        $archive->addFromString('1-trace.trace', '{"access_token":"' . $jwt . '"}');
        $archive->addFromString(
            '2-login.network',
            '{"text":"{\"token_type\":\"Bearer\",\"refresh_token\":\"' . $refresh . '\"}"}'
        );
        $archive->addFromString(
            '3-refresh.txt',
            'grant_type=refresh_token&refresh_token=' . $refresh . '&client_id=administration'
        );
        $archive->addFromString('4-basic.txt', 'Authorization: Basic ' . $basic);
        $archive->close();
        file_put_contents($root . '/shot.png', "\x89PNG\x00pixels");

        // Playwright appends the report zip as base64, so the secret is not plaintext in index.html.
        $reportDir = $root . '/playwright-report';
        mkdir($reportDir);
        $reportZip = $reportDir . '/embedded.zip';
        $report = new ZipArchive();
        $report->open($reportZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $report->addFromString(
            'report.json',
            (string) json_encode(
                ['message' => 'Shopware transition failed: 500 ' . $secret],
                JSON_UNESCAPED_SLASHES
            )
        );
        $report->close();
        $encodedReport = base64_encode((string) file_get_contents($reportZip));
        unlink($reportZip);
        $htmlPath = $reportDir . '/index.html';
        $marker = '<template id="playwrightReportBase64">data:application/zip;base64,';
        file_put_contents(
            $htmlPath,
            '<div id="root"></div>' . $marker . $encodedReport . '</template>'
        );
        if (str_contains((string) file_get_contents($htmlPath), $secret)) {
            fwrite(STDERR, "self-check: html fixture contains the plaintext secret\n");

            return 1;
        }

        if (scrubTree($root, $needles) !== []) {
            fwrite(STDERR, "self-check: expected a clean tree\n");

            return 1;
        }
        if (str_contains((string) file_get_contents($root . '/trace.network'), $secret)) {
            fwrite(STDERR, "self-check: secret left in trace.network\n");

            return 1;
        }
        if (str_contains((string) file_get_contents($root . '/note.txt'), $encoded)) {
            fwrite(STDERR, "self-check: encoded secret left in note.txt\n");

            return 1;
        }
        $check = new ZipArchive();
        $check->open($zipPath);
        $trace = $check->getFromName('0-trace.trace');
        $header = $check->getFromName('1-trace.network');
        $body = $check->getFromName('1-trace.trace');
        $escapedLogin = $check->getFromName('2-login.network');
        $refreshBody = $check->getFromName('3-refresh.txt');
        $basicBody = $check->getFromName('4-basic.txt');
        $check->close();
        if (!is_string($trace) || str_contains($trace, $secret)) {
            fwrite(STDERR, "self-check: secret left in zip\n");

            return 1;
        }
        if (!is_string($header) || str_contains($header, $jwt) || !is_string($body) || str_contains($body, $jwt)) {
            fwrite(STDERR, "self-check: bearer token left in zip\n");

            return 1;
        }
        if (
            !is_string($escapedLogin)
            || str_contains($escapedLogin, $refresh)
            || !is_string($refreshBody)
            || str_contains($refreshBody, $refresh)
            || str_contains((string) file_get_contents($root . '/login.json'), $refresh)
        ) {
            fwrite(STDERR, "self-check: refresh token left in artifacts\n");

            return 1;
        }
        if (
            str_contains((string) file_get_contents($root . '/pay.log'), $basic)
            || !is_string($basicBody)
            || str_contains($basicBody, $basic)
        ) {
            fwrite(STDERR, "self-check: basic credential left in artifacts\n");

            return 1;
        }
        $htmlReport = embeddedReport((string) file_get_contents($htmlPath));
        $entry = is_array($htmlReport)
            ? withZipBytes($htmlReport['zip'], static function (string $path): string|false {
                $decoded = new ZipArchive();
                if ($decoded->open($path) !== true) {
                    return false;
                }
                $payload = $decoded->getFromName('report.json');
                $decoded->close();

                return $payload;
            })
            : false;
        if (
            !is_string($entry)
            || str_contains($entry, $secret)
            || !str_contains($entry, 'Shopware transition failed')
        ) {
            fwrite(STDERR, "self-check: secret left in html report\n");

            return 1;
        }
        $kept = (string) file_get_contents($root . '/keep.txt');
        if (
            !str_contains($kept, 'Bearer ${token}')
            || !str_contains($kept, 'aaaaaaaab.bbbbbbbb.cccccccc')
            || !str_contains($kept, $decoy)
            || !str_contains($kept, '"refresh_token":"abcd"')
        ) {
            fwrite(STDERR, "self-check: non-token text was rewritten\n");

            return 1;
        }
        if (file_get_contents($root . '/shot.png') !== "\x89PNG\x00pixels") {
            fwrite(STDERR, "self-check: binary file was rewritten\n");

            return 1;
        }

        file_put_contents($root . '/leak.bin', "\0" . $secret);
        $leftovers = scrubTree($root, $needles);
        $names = array_map(static fn (string $path): string => basename($path), $leftovers);
        if ($names !== ['leak.bin']) {
            fwrite(STDERR, "self-check: expected leak.bin to remain, got " . implode(', ', $names) . "\n");

            return 1;
        }
    } finally {
        $cleanup = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($cleanup as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($root);
    }

    fwrite(STDOUT, "redact self-check ok\n");

    return 0;
}

if (!extension_loaded('zip')) {
    fwrite(STDERR, "The zip PHP extension is required to redact traces\n");
    exit(1);
}

if (in_array('--self-check', $argv, true)) {
    exit(selfCheck());
}

$needles = collectNeedles();
$leftovers = [];
foreach (array_slice($argv, 1) as $root) {
    if ($root === '--self-check') {
        continue;
    }
    array_push($leftovers, ...scrubTree($root, $needles));
}

if ($leftovers !== []) {
    fwrite(STDERR, "Refusing to leave credentials in artifacts:\n");
    foreach ($leftovers as $path) {
        fwrite(STDERR, "  {$path}\n");
    }
    exit(1);
}

fwrite(STDOUT, "Redacted credentials from test artifacts\n");
exit(0);
