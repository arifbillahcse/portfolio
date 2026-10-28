<x-layouts.app :seo="$seo" :title="($entry->seo_title ?: $entry->title).' | '.$site->brand_name" :description="$entry->seo_description ?: ($entry->excerpt ?? $entry->summary ?? '')" :page="$section" :robots="$entry->is_demo ? 'noindex, follow' : null" :site="$site" :profile="$profile" :contact-links="$contactLinks">
<main id="main" class="content-detail wrap">
<nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route($section) }}">{{ ucfirst($section) }}</a></nav>
<header class="detail-heading"><span class="eyebrow">{{ $section === 'services' ? str_replace('_', ' ', $entry->group) : ($entry->category?->name ?? ucfirst($section)) }}</span><h1>{{ $entry->title }}</h1><p>{{ $entry->excerpt ?? $entry->summary }}</p>@if($section === 'blog')<div class="detail-meta"><span>{{ $entry->reading_minutes }} min read</span>@if($entry->published_at)<time datetime="{{ $entry->published_at->toDateString() }}">{{ $entry->published_at->format('F j, Y') }}</time>@endif</div>@endif</header>
@if($entry->is_demo)<p class="demo-notice">This is {{ $section === 'blog' ? 'a sample article' : 'an illustrative project concept' }}.</p>@endif
@if($image = \App\Support\PublicContent::image($entry->cover_image))<img class="detail-cover" src="{{ $image }}" alt="{{ $entry->title }}" width="1200" height="800">@endif
<div class="prose">{!! $bodyHtml !!}</div>
@if($section === 'projects')
<ul class="tech-tags">@foreach($entry->tools ?? [] as $tool)<li>{{ $tool }}</li>@endforeach</ul>
<div class="detail-gallery">@foreach($entry->gallery ?? [] as $path) @if($image = \App\Support\PublicContent::image($path))<img src="{{ $image }}" alt="{{ $entry->title }} — screenshot {{ $loop->iteration }}" loading="lazy" width="1000" height="700">@endif @endforeach</div>
<div class="detail-links">@if($url = \App\Support\PublicContent::link($entry->external_url))<a class="button" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Visit website ↗</a>@endif @if($url = \App\Support\PublicContent::link($entry->repository_url))<a class="button secondary" href="{{ $url }}" target="_blank" rel="noopener noreferrer">View repository ↗</a>@endif</div>
@elseif($section === 'services' && $entry->offerings)<h2>What’s included</h2><ul class="service-offerings">@foreach($entry->offerings as $offering)<li>{{ $offering }}</li>@endforeach</ul>
@endif
<div class="detail-cta"><h2>Let’s talk about your project.</h2><a class="button primary-button" href="{{ $contactLinks['whatsapp'].'?text='.rawurlencode('Hi, I would like to discuss '.$entry->title.'.') }}" target="_blank" rel="noopener">Message on WhatsApp ↗</a><a href="{{ route($section) }}">← Back to {{ $section }}</a></div>
</main>
</x-layouts.app>
