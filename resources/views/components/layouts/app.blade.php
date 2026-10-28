@props(['title', 'description', 'page' => 'home', 'robots' => null, 'site', 'profile', 'contactLinks', 'seo' => []])
@php
    $title = $seo['title'] ?? $title;
    $description = $seo['description'] ?? $description;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ $description }}">
    @if(empty($seo['is_preview']) && filled($site->google_site_verification))
    <meta name="google-site-verification" content="{{ $site->google_site_verification }}">
    @endif
    @php($effectiveRobots = !config('portfolio.indexable') ? 'noindex, nofollow' : (($seo['robots'] ?? null) ?: $robots))
    @if ($effectiveRobots)
        <meta name="robots" content="{{ $effectiveRobots }}">
    @endif
    <title>{{ $title }}</title>
    @if($seo)
    <link rel="canonical" href="{{ $seo['canonical'] }}">
    <meta property="og:site_name" content="{{ $site->brand_name }}">
    <meta property="og:type" content="{{ $seo['type'] }}">
    <meta property="og:title" content="{{ $seo['social_title'] ?? $title }}">
    <meta property="og:description" content="{{ $seo['social_description'] ?? $description }}">
    <meta property="og:url" content="{{ $seo['canonical'] }}">
    <meta property="og:image" content="{{ $seo['image'] }}">
    <meta property="og:image:alt" content="{{ $seo['image_alt'] ?? $title }}">
    @if(!empty($seo['image_dimensions']))
    <meta property="og:image:width" content="{{ $seo['image_dimensions']['width'] }}">
    <meta property="og:image:height" content="{{ $seo['image_dimensions']['height'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seo['social_title'] ?? $title }}">
    <meta name="twitter:description" content="{{ $seo['social_description'] ?? $description }}">
    <meta name="twitter:image" content="{{ $seo['image'] }}">
    <meta name="twitter:image:alt" content="{{ $seo['image_alt'] ?? $title }}">
    @if(!empty($seo['schema']))
    <script type="application/ld+json">{!! json_encode($seo['schema'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    @php($stylesheetVersion = is_file(public_path('assets/css/styles.css')) ? filemtime(public_path('assets/css/styles.css')) : '1')
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}?v={{ $stylesheetVersion }}">
</head>
<body class="{{ $page === 'home' ? 'portfolio ' : '' }}page-{{ $page }}" data-page="{{ $page }}" data-whatsapp="{{ preg_replace('/[^0-9]/', '', $site->whatsapp_number ?? '') }}">
    <a class="skip-link" href="#main">Skip to content</a>
    @include('partials.header')

    {{ $slot }}

    @include('partials.footer', ['page' => $page])

    @php($scriptVersion = is_file(public_path('assets/js/script.js')) ? filemtime(public_path('assets/js/script.js')) : '1')
    <script src="{{ asset('assets/js/script.js') }}?v={{ $scriptVersion }}" defer></script>
    @if(empty($seo['is_preview']) && $site->chat_widget_enabled && filled($site->chat_widget_code))
        {!! $site->chat_widget_code !!}
    @endif
</body>
</html>
