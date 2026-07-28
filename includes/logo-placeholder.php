<?php
/**
 * Renders the TravelCore badge as inline SVG. Used everywhere the real
 * assets/images/logo.png hasn't been supplied yet -- drop the actual PNG in
 * place and swap render_logo() calls for <img> tags if you want the real
 * artwork instead; no other code needs to change.
 */
function render_logo(int $size = 44, bool $withText = false): string {
    $gradId = 'logoGrad' . $size . ($withText ? 'T' : '');
    $svg = '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-label="TravelCore logo">'
        . '<defs><linearGradient id="' . $gradId . '" x1="0%" y1="0%" x2="0%" y2="100%">'
        . '<stop offset="0%" stop-color="#2F80ED"/><stop offset="100%" stop-color="#56CCF2"/></linearGradient></defs>'
        . '<circle cx="50" cy="50" r="48" fill="url(#' . $gradId . ')" stroke="#FFFFFF" stroke-width="2"/>'
        . '<path d="M30 45 L55 30 L60 34 L46 46 L68 50 L72 46 L76 49 L68 58 L46 54 L36 62 L30 60 L38 51 Z" fill="#FFFFFF" opacity="0.95"/>'
        . '<circle cx="50" cy="68" r="10" fill="#FFFFFF" opacity="0.85"/>'
        . '<path d="M14 72 Q50 60 86 72 L86 80 Q50 70 14 80 Z" fill="#FFFFFF" opacity="0.9"/>'
        . '</svg>';
    if ($withText) {
        return '<span class="logo-badge">' . $svg . '<span class="logo-text"><strong>TravelCore</strong><small>Travel &amp; Tours</small></span></span>';
    }
    return $svg;
}

function logo_or_image(int $size = 44, bool $withText = false): string {
    $imgPath = __DIR__ . '/../assets/images/logo.png';
    if (file_exists($imgPath)) {
        $img = '<img src="' . BASE_URL . '/assets/images/logo.png" width="' . $size . '" height="' . $size . '" alt="TravelCore logo" style="border-radius:50%;">';
        if ($withText) {
            return '<span class="logo-badge">' . $img . '<span class="logo-text"><strong>TravelCore</strong><small>Travel &amp; Tours</small></span></span>';
        }
        return $img;
    }
    return render_logo($size, $withText);
}
