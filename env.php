<?php
function loadEnv(string $path): void
{
    if (!is_readable($path)) {
        error_log('.env file not found or unreadable');
        http_response_code(500);
        die('Configuration error.');
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // remove surrounding quotes: DB_PASS="abc" -> abc
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[$key] = $value;
    }
}

function env(string $key, $default = null)
{
    return $_ENV[$key] ?? $default;
}