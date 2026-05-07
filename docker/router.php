<?php
/**
 * PHP built-in server router for Symfony.
 *
 * PHP's built-in server calls this script for EVERY request, including
 * static files. We must return false for existing static files so PHP
 * serves them directly; otherwise route everything through Symfony.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Resolve the physical path inside the document root (public/)
$docRoot = __DIR__ . '/../public';
$filePath = $docRoot . $uri;

// If the file exists and is not a directory, let PHP serve it directly
if ($uri !== '/' && is_file($filePath)) {
    return false;
}

// Everything else goes through Symfony
require $docRoot . '/index.php';
