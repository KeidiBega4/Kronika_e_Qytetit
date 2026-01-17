<?php
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function excerpt_text($text, $max = 180) {
    $text = strip_tags((string)$text);
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if ($text === '') return '';
    if (mb_strlen($text, 'UTF-8') <= $max) return $text;
    return mb_substr($text, 0, $max, 'UTF-8') . '...';
}