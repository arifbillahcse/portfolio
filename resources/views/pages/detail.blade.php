<x-layouts.app :seo="$seo" :title="($entry->seo_title ?: $entry->title).' | '.$site->brand_name" :description="$entry->seo_description ?: ($entry->excerpt ?? $entry->summary ?? '')" :page="$section" :robots="$entry->is_demo ? 'noindex, follow' : null" :site="$site" :profile="$profile" :contact-links="$contactLinks">
<main id="main" tabindex="-1" class="content-detail wrap">
@if($isPreview)<aside class="project-preview-note"><div><strong>Administrator preview</strong><p>Saved changes are shown here. This preview is visible only to administrators.</p></div><a href="{{ route('filament.admin.resources.projects.edit', ['record' => $entry]) }}">Back to edit project</a></aside>@endif
@php($video = $section === 'projects' ? \App\Support\PublicContent::video($entry->video_url) : null)
<nav class="detail-breadcrumb" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a><span aria-hidden="true">/</span><a href="{{ route($section) }}">{{ ucfirst($section) }}</a></nav>
<header class="detail-heading"><span class="eyebrow">{{ $section === 'services' ? str_replace('_', ' ', $entry->group) : ($entry->category?->name ?? ucfirst($section)) }}</span><h1>{{ $entry->title }}</h1><p>{{ $entry->excerpt ?? $entry->summary }}</p>@if($section === 'blog')<div class="detail-meta"><span>{{ $entry->reading_minutes }} min read</span>@if($entry->published_at)<time datetime="{{ $entry->published_at->toDateString() }}">{{ $entry->published_at->format('F j, Y') }}</time>@endif</div>@endif</header>
@if($entry->is_demo)<p class="demo-notice">This is {{ $section === 'blog' ? 'a sample article' : 'an illustrative project concept' }}.</p>@endif
@if($video)
<section class="project-video" id="project-video" aria-labelledby="project-video-title">
    <h2 id="project-video-title">{{ $entry->video_name ?: "Project demonstration" }}</h2>
    @if($entry->video_description)<p>{{ $entry->video_description }}</p>@endif
    @if($entry->video_uploaded_at)<p><time datetime="{{ $entry->video_uploaded_at->toIso8601String() }}">Video uploaded {{ $entry->video_uploaded_at->format("F j, Y") }}</time></p>@endif
    <div class="project-video-frame"><iframe src="{{ $video['embed_url'] }}" title="{{ $entry->video_name ?: ("Video demonstration: ".$entry->title) }}" width="960" height="540" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe></div>
    <a class="project-video-link" href="{{ $video['watch_url'] }}" target="_blank" rel="noopener noreferrer">Watch on YouTube <span aria-hidden="true">↗</span></a>
</section>
@elseif($image = \App\Support\PublicContent::image($entry->cover_image))<figure class="detail-media"><x-media-image class="detail-cover" :path="$entry->cover_image" :alt="\App\Support\MediaSeo::text($entry->cover_image_alt, $entry->title)" loading="eager" fetchpriority="high" />@if($entry->cover_image_caption)<figcaption>{{ $entry->cover_image_caption }}</figcaption>@endif</figure>@endif
<div class="prose">{!! $bodyHtml !!}</div>
@if($section === 'projects')
<ul class="tech-tags">@foreach($entry->tools ?? [] as $tool)<li>{{ $tool }}</li>@endforeach</ul>
<div class="detail-gallery">@foreach($entry->gallery ?? [] as $path) @if($image = \App\Support\PublicContent::image($path))@php($details = \App\Support\MediaSeo::gallery($entry, $path, $loop->iteration))<figure class="detail-media"><x-media-image :path="$path" :alt="$details['alt']" />@if($details['caption'])<figcaption>{{ $details['caption'] }}</figcaption>@endif</figure>@endif @endforeach</div>
<div class="detail-links">@if($url = \App\Support\PublicContent::link($entry->external_url))<a class="button" href="{{ $url }}" target="_blank" rel="noopener noreferrer">Visit website ↗</a>@endif @if($url = \App\Support\PublicContent::link($entry->repository_url))<a class="button secondary" href="{{ $url }}" target="_blank" rel="noopener noreferrer">View repository ↗</a>@endif</div>
@elseif($section === 'services' && $entry->offerings)<h2>What’s included</h2><ul class="service-offerings">@foreach($entry->offerings as $offering)<li>{{ $offering }}</li>@endforeach</ul>
@endif
<div class="detail-cta"><h2>Let’s talk about your project.</h2><a class="button primary-button" href="{{ $contactLinks['whatsapp'].'?text='.rawurlencode('Hi, I would like to discuss '.$entry->title.'.') }}" target="_blank" rel="noopener">Message on WhatsApp ↗</a><a href="{{ route($section) }}">← Back to {{ $section }}</a></div>
</main>
</x-layouts.app>
