<?php
declare(strict_types=1);

defined('FA') || exit;

function page_head(string $title, string $bodyClass, array $bodyData = [], array $extraCss = [], string $description = ''): void
{
    $attrs = '';
    foreach ($bodyData as $k => $v) {
        $attrs .= ' data-' . e($k) . '="' . e($v) . '"';
    }
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#121212" media="(prefers-color-scheme: dark)">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="robots" content="<?= $description !== '' ? 'index, follow' : 'noindex' ?>">
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
<title><?= e($title) ?></title>
<link rel="icon" href="<?= e(asset('logo.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap">
<?php foreach ($extraCss as $css): ?><link rel="stylesheet" href="<?= e(str_starts_with($css, 'vendor/') ? url('assets/' . $css) . '?v=' . FA_ASSET_VER : asset($css)) ?>">
<?php endforeach; ?>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>" data-base="<?= e(base_path()) ?>"<?= $attrs ?>>
<?php
}

function page_foot(array $scripts): void
{
    foreach ($scripts as $s) {
        echo '<script src="' . e(asset($s)) . '" defer></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}

function flash_html(): string
{
    $html = '';
    foreach (take_flashes() as $f) {
        $html .= '<div class="flash flash-' . e($f['kind']) . '" role="status">' . e($f['msg']) . '</div>';
    }
    return $html;
}

function logo_svg(string $class = 'logo'): string
{
    return '<svg class="' . e($class) . '" viewBox="0 0 48 48" aria-hidden="true">'
        . '<rect width="48" height="48" rx="13" fill="#00209F"/>'
        . '<path d="M14 31c3-6 7-9 10-9s7 3 10 9" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"/>'
        . '<circle cx="14" cy="17" r="4" fill="#F5C518"/><circle cx="24" cy="13" r="4" fill="#fff"/><circle cx="34" cy="17" r="4" fill="#D21034"/>'
        . '<path d="M17 37h14" stroke="#F5C518" stroke-width="3" stroke-linecap="round"/>'
        . '</svg>';
}

function brand_html(): string
{
    return '<a class="brand" href="' . e(url('index.php')) . '">' . logo_svg() . '<span>Konbit</span></a>';
}
