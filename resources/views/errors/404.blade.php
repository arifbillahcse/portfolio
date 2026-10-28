<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, follow">
    <title>Page not found | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}?v={{ is_file(public_path('assets/css/styles.css')) ? filemtime(public_path('assets/css/styles.css')) : '1' }}">
</head>
<body class="page-error">
    <main class="error-content" id="main">
        <a class="logo" href="{{ route('home') }}">{{ config('app.name') }}</a>
        <p class="error-code" aria-hidden="true">404</p>
        <h1>This page couldn’t be found.</h1>
        <p>The link may have changed, or the page is no longer available. You can head back home or get in touch.</p>
        <div class="actions"><a class="btn primary" href="{{ route('home') }}">Back to home</a><a class="btn" href="{{ route('contact') }}">Contact Arif</a></div>
    </main>
</body>
</html>
