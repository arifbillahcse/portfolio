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
    @include('partials.analytics')
    <link rel="preload" href="{{ asset('assets/fonts/manrope-latin-8c2def024c1b.woff2') }}" as="font" type="font/woff2" crossorigin>
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
        @if(in_array($site->chat_widget_load_mode, ['idle', 'interaction'], true))
        <script id="chat-widget-config" type="application/json">{!! json_encode(['mode' => $site->chat_widget_load_mode, 'code' => $site->chat_widget_code], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) !!}</script>
        <div class="chat-launcher"><button type="button" id="loadChat">Open chat</button><a id="chatFallback" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener" hidden>Message on WhatsApp</a><span id="chatLoadStatus" role="status" aria-live="polite"></span></div>
        <noscript><p>JavaScript is required for this chat widget. <a href="{{ $contactLinks['whatsapp'] }}">Message on WhatsApp</a>.</p></noscript>
        @else
        {!! $site->chat_widget_code !!}
        @endif
    @endif
</body>
</html>
