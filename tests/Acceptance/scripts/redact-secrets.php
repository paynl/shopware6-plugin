<?php

declare(strict_types=1);

/**
 * Remove PAY. credentials from test artifacts before they are uploaded.
 */

const MIN_SECRET_LENGTH = 8;
const MASK = '***';
const SECRET_KEYS = [
    'PAY_SANDBOX_SECRET',
    'PAY_API_TOKEN',
    'PAY_TOKEN_CODE',
    'PAY_SERVICE_ID',
    'NGROK_AUTHTOKEN',
];

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

    $needles = array_values($found);
    usort($needles, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    return $needles;
}

/**
 * @param list<string> $needles
 */
function scrubBytes(string $data, array $needles): string
{
    if (str_contains(substr($data, 0, 8192), "\0") || preg_match('//u', $data) !== 1) {
        return $data;
    }

    $updated = $data;
    foreach ($needles as $needle) {
        $updated = str_replace($needle, MASK, $updated);
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
 */
function scrubZip(string $path, array $needles): void
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
        $scrubbed = scrubBytes($payload, $needles);
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

        return is_string($contents) && payloadHasSecret($contents, $needles);
    }

    try {
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $payload = $archive->getFromIndex($index);
            if (is_string($payload) && payloadHasSecret($payload, $needles)) {
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
 *
 * @return list<string>
 */
function scrubTree(string $root, array $needles): array
{
    if (!is_dir($root)) {
        return [];
    }

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
            scrubZip($path, $needles);
            if (zipHasSecret($path, $needles)) {
                $leftovers[] = $path;
            }
            continue;
        }

        $original = file_get_contents($path);
        if (!is_string($original)) {
            continue;
        }
        $scrubbed = scrubBytes($original, $needles);
        if ($scrubbed !== $original) {
            file_put_contents($path, $scrubbed);
        }
        if (payloadHasSecret($scrubbed, $needles)) {
            $leftovers[] = $path;
        }
    }

    return $leftovers;
}

function selfCheck(): int
{
    $secret = 'sandbox/secret value';
    $encoded = rawurlencode($secret);
    $needles = needlesFor($secret);
    usort($needles, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

    $root = sys_get_temp_dir() . '/paynl-redact-' . bin2hex(random_bytes(4));
    mkdir($root);
    try {
        file_put_contents($root . '/trace.network', '{"value":"' . $secret . '"}' . "\n");
        file_put_contents($root . '/note.txt', 'body=' . $encoded . "\n");
        $zipPath = $root . '/trace.zip';
        $archive = new ZipArchive();
        $archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $archive->addFromString('0-trace.trace', (string) json_encode(['params' => ['value' => $secret]], JSON_UNESCAPED_SLASHES));
        $archive->close();
        file_put_contents($root . '/shot.png', "\x89PNG\x00pixels");

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
        $check->close();
        if (!is_string($trace) || str_contains($trace, $secret)) {
            fwrite(STDERR, "self-check: secret left in zip\n");

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
if ($needles === []) {
    fwrite(STDOUT, "No secrets set; nothing to redact\n");
    exit(0);
}

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
