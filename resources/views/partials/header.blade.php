<header class="site-header">
    <nav class="site-nav" aria-label="Main navigation">
        <a class="logo" href="{{ route('home') }}" aria-label="{{ $site->brand_name }} home">{{ $site->brand_name }}<span>.</span></a>
        <div class="nav-links" id="nav">
            @foreach (['home' => 'Home', 'about' => 'About', 'services' => 'Services', 'projects' => 'Projects', 'blog' => 'Blog', 'contact' => 'Contact'] as $name => $label)
                <a href="{{ route($name) }}" @if (request()->routeIs($name, $name.'.*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </div>
        <div class="nav-actions">
            <button class="round" id="theme" type="button" aria-label="Switch to dark theme" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8"/><path d="M12 4a8 8 0 0 1 0 16Z" fill="currentColor" stroke="none"/></svg></button>
            <button class="round menu" id="menu" type="button" aria-label="Open navigation menu" aria-controls="nav" aria-expanded="false">☰</button>
        </div>
    </nav>
</header>
