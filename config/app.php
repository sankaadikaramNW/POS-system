<?php
/**
 * config/app.php — Centralized Application Configuration
 *
 * ALL version/build/environment metadata lives here.
 * To update the version across the ENTIRE system, change APP_VERSION below.
 */

// ── Version & Build ────────────────────────────────────────────────────────
define('APP_NAME',        'DXL Fashion POS');
define('APP_VERSION',     'v0.1');
define('APP_BUILD_DATE',  '2026-06-02');           // ISO 8601 (YYYY-MM-DD)
define('APP_ENVIRONMENT', getenv('APP_ENV') ?: 'Development'); // Override via env var in production

// ── Derived display strings (used in views) ────────────────────────────────
define('APP_VERSION_LABEL',   APP_NAME . ' | Version ' . APP_VERSION);
define('APP_COPYRIGHT',       '&copy; ' . date('Y') . ' ' . APP_NAME . ' | Version ' . APP_VERSION);
define('APP_SHORT_VERSION',   'Version ' . APP_VERSION);   // for login page
