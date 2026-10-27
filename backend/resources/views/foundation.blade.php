<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ config('app.name') }} — Backend</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 32px; background: #f7f8ff; color: #080b45; font: 16px/1.7 system-ui, sans-serif; }
        main { max-width: 760px; margin: 10vh auto; }
        .label { color: #1600f5; font-weight: 700; letter-spacing: .12em; font-size: 12px; text-transform: uppercase; }
        h1 { font-size: clamp(32px, 6vw, 54px); line-height: 1.1; letter-spacing: -.05em; }
        p { color: #62688e; }
        a { display: inline-block; margin-top: 15px; padding: 12px 22px; border-radius: 10px; background: #1600f5; color: white; text-decoration: none; }
    </style>
</head>
<body>
    <main>
        <span class="label">Arif Portfolio · Phase 1</span>
        <h1>Your backend foundation is ready.</h1>
        <p>Laravel, the database, and the administrator sign-in are configured. The public website design remains in the original static pages until the frontend migration phase.</p>
        <a href="{{ url('/admin') }}">Open admin panel ↗</a>
    </main>
</body>
</html>
