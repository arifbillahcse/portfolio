@props(['title', 'description', 'page' => 'home', 'robots' => null, 'site', 'profile', 'contactLinks', 'seo' => []])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    @php($effectiveRobots = !config('portfolio.indexable') ? 'noindex, nofollow' : (($seo['robots'] ?? null) ?: $robots))
    @if ($effectiveRobots)
        <meta name="robots" content="{{ $effectiveRobots }}">
    @endif
    <title>{{ $title }}</title>
    @if($seo)
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:site_name" content="{{ $site->brand_name }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:image:alt" content="{{ $title }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
</head>
<body class="{{ $page === 'home' ? 'portfolio ' : '' }}page-{{ $page }}" data-page="{{ $page }}" data-whatsapp="{{ preg_replace('/[^0-9]/', '', $site->whatsapp_number ?? '') }}">
    @include('partials.header')

    {{ $slot }}

    @include('partials.footer', ['page' => $page])

    <script src="{{ asset('assets/js/script.js') }}" defer></script>
</body>
</html>
