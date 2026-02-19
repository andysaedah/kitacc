#!/usr/bin/env php
<?php
/**
 * KiTAcc - Asset Build Script
 * Minifies CSS and JS for production deployment.
 *
 * Usage:  php build.php
 * Output: css/styles.min.css, js/app.min.js
 *
 * The minified files are auto-detected by header.php / footer.php
 * when APP_ENV=production in .env
 */

echo "KiTAcc Asset Builder\n";
echo str_repeat('=', 40) . "\n\n";

$basePath = __DIR__;

// ========================================
// CSS Minification (regex-based, no deps)
// ========================================
function minifyCss(string $css): string
{
    // Remove comments
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    // Remove whitespace around selectors/braces
    $css = preg_replace('/\s*([{}:;,>~+])\s*/', '$1', $css);
    // Collapse remaining whitespace
    $css = preg_replace('/\s{2,}/', ' ', $css);
    // Remove leading/trailing whitespace per line
    $css = preg_replace('/^\s+/m', '', $css);
    // Remove last semicolons before closing brace
    $css = str_replace(';}', '}', $css);
    // Trim
    return trim($css);
}

// ========================================
// JS Minification (safe: whitespace + comments only)
// ========================================
function minifyJs(string $js): string
{
    // Remove single-line comments (but not URLs like http://)
    $js = preg_replace('#(?<!:)//(?!/).*$#m', '', $js);
    // Remove multi-line comments
    $js = preg_replace('!/\*.*?\*/!s', '', $js);
    // Collapse multiple blank lines
    $js = preg_replace('/\n{2,}/', "\n", $js);
    // Remove leading whitespace per line
    $js = preg_replace('/^\s+/m', '', $js);
    // Remove trailing whitespace per line
    $js = preg_replace('/\s+$/m', '', $js);
    // Trim
    return trim($js);
}

// ---- Process CSS ----
$cssSource = $basePath . '/css/styles.css';
$cssDest = $basePath . '/css/styles.min.css';

if (file_exists($cssSource)) {
    $original = file_get_contents($cssSource);
    $minified = minifyCss($original);
    file_put_contents($cssDest, $minified);

    $origSize = strlen($original);
    $minSize = strlen($minified);
    $saved = round((1 - $minSize / $origSize) * 100, 1);
    echo "CSS: " . number_format($origSize) . " → " . number_format($minSize) . " bytes ({$saved}% smaller)\n";
    echo "  ✓ {$cssDest}\n\n";
} else {
    echo "CSS: source not found ({$cssSource})\n\n";
}

// ---- Process JS ----
$jsSource = $basePath . '/js/app.js';
$jsDest = $basePath . '/js/app.min.js';

if (file_exists($jsSource)) {
    $original = file_get_contents($jsSource);
    $minified = minifyJs($original);
    file_put_contents($jsDest, $minified);

    $origSize = strlen($original);
    $minSize = strlen($minified);
    $saved = round((1 - $minSize / $origSize) * 100, 1);
    echo "JS:  " . number_format($origSize) . " → " . number_format($minSize) . " bytes ({$saved}% smaller)\n";
    echo "  ✓ {$jsDest}\n\n";
} else {
    echo "JS:  source not found ({$jsSource})\n\n";
}

echo "Done! Set APP_ENV=production in .env to serve minified assets.\n";
