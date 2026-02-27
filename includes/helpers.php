<?php
// includes/helpers.php

function h($s): string {
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function clean_name(string $s): string {
    $s = trim($s);
    // allow letters, spaces, dot, dash, apostrophe
    $s = preg_replace("/[^a-zA-Z \.\-']/u", "", $s);
    return trim($s);
}

function clean_phone(string $s): string {
    $s = trim($s);
    $s = preg_replace("/[^0-9+]/", "", $s);
    return $s;
}

function ensure_uploads_dir(string $path): void {
    if (!is_dir($path)) {
        if (!mkdir($path, 0775, true)) {
            http_response_code(500);
            exit("Failed to create uploads folder.");
        }
    }
}

function is_allowed_image_mime(string $mime): bool {
    return in_array($mime, ['image/jpeg','image/png','image/webp'], true);
}