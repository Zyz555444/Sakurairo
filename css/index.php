<?php
header("Content-Type: text/css; charset=UTF-8");
header("Cache-Control: public, max-age=86400");
header("Expires: " . gmdate("D, d M Y H:i:s", time() + 86400) . " GMT");

$style_files = [
    '../style.css',
    'shortcodes.css',
    'dark.css',
    'responsive.css',
    'animation.css',
    'templates.css',
    'preload-base.css',
];

if (isset($_GET['sakura_header'])) {
    $style_files[] = 'sakura_header.css';
}
if (isset($_GET['wave'])) {
    $style_files[] = 'wave.css';
}
if (isset($_GET['github'])) {
    $style_files[] = './content-style/github.css';
}
if (isset($_GET['sakura'])) {
    $style_files[] = './content-style/sakura.css';
}

$minify = isset($_GET['minify']);

function compressCSS($css) {
    $css = preg_replace("/\/\*.*?\*\//s", "", $css);
    $css = preg_replace("/\s*([{};:,])\s*/", "$1", $css);
    $css = preg_replace("/;}/", "}", $css);
    return trim($css);
}

$cache_key_parts = array();
foreach ($style_files as $style) {
    $file_path = __DIR__ . '/' . $style;
    if (file_exists($file_path)) {
        $cache_key_parts[] = $style . ':' . filemtime($file_path);
    }
}
$cache_key_parts[] = $minify ? 'minify' : 'raw';
$cache_key = md5(implode('|', $cache_key_parts));

$cache_dir = __DIR__ . '/cache';
$cache_file = $cache_dir . '/' . $cache_key . '.css';

if (is_readable($cache_file)) {
    readfile($cache_file);
    exit;
}

$output = "";
foreach ($style_files as $style) {
    $file_path = __DIR__ . '/' . $style;
    if (file_exists($file_path)) {
        $content = file_get_contents($file_path);
        $output .= "\n/* === " . basename($style) . " === */\n";
        $output .= $minify ? compressCSS($content) : $content;
    }
}

if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0755, true);
}
if (is_dir($cache_dir) && is_writable($cache_dir)) {
    @file_put_contents($cache_file, $output, LOCK_EX);
}

echo $output;
