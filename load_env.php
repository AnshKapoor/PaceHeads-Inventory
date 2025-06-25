<?php
// load_env.php

function loadEnv($path) {
    if (!file_exists($path)) {
        // If .env file doesn't exist, we assume it's a production environment
        // where variables are set directly by the hosting provider.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignore comments
        if (str_starts_with(trim($line), '#')) {
            continue;
        }

        // Parse line (e.g., KEY=VALUE)
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Remove quotes if present
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = trim($value, '"');
        } elseif (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            $value = trim($value, "'");
        }

        // Set the environment variable
        // putenv() makes it available to getenv() and $_ENV
        putenv(sprintf('%s=%s', $key, $value));
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value; // Also set in $_SERVER for consistency
    }
}

// Call the function to load your .env file
loadEnv(__DIR__ . '/.env');
