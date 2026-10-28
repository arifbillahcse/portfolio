<x-layouts.app :seo="$seo" :title="'Services | '.$profile->name" description="Development, integrations, cloud infrastructure, and ongoing server care." page="services" :site="$site" :profile="$profile" :contact-links="$contactLinks">
<main id="main" tabindex="-1"><section class="service-intro wrap"><span class="eyebrow">Development · Cloud · Infrastructure</span><h1>From your first idea<br>to a <span>secure, running platform.</span></h1><p>{{ $profile->home_intro }}</p><div class="intro-bottom"><span class="service-count">{{ $services->count() }} services. One connected approach.</span><a class="text-link" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener">Discuss your requirements ↗</a></div></section>
@php($groups = ['development' => 'Build your platform', 'integrations' => 'Connect & automate', 'infrastructure' => 'Deploy on solid foundations', 'care' => 'Secure, optimize & maintain'])
<nav class="category-nav wrap" aria-label="Service categories">@foreach($groups as $key => $heading)<a href="#{{ $key }}"><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $heading }}</a>@endforeach</nav>
@php($number = 0)
@foreach($groups as $key => $heading)
<section class="service-section wrap" id="{{ $key }}" aria-labelledby="{{ $key }}-title"><div class="group-heading"><span class="group-index">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><div><h2 id="{{ $key }}-title">{{ $heading }}</h2></div></div><div class="service-grid">
@forelse($services->where('group', $key) as $service)
<article class="service-card"><div class="card-top"><span class="service-number">{{ str_pad(++$number, 2, '0', STR_PAD_LEFT) }}</span>@include('partials.service-icon', ['icon' => $service->icon])</div><h3><a href="{{ route('services.show', $service->slug) }}">{{ $service->title }}</a></h3><p>{{ $service->summary }}</p>@if($service->offerings)<ul class="service-offerings">@foreach($service->offerings as $offering)<li>{{ $offering }}</li>@endforeach</ul>@endif<a href="{{ route('services.show', $service->slug) }}">Explore service <span aria-hidden="true">↗</span></a></article>
@empty <p>Services in this area will be added soon. <a href="{{ route('contact') }}">Discuss your requirements</a>.</p> @endforelse
</div></section>
@endforeach
<section class="service-cta"><div class="wrap"><span class="eyebrow">Let’s plan your next step</span><h2>One person.<br>The whole stack.</h2><p>Share your project requirements, and I’ll suggest an approach and a realistic scope before we begin.</p><a class="button" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener">Message on WhatsApp ↗</a></div></section></main>
</x-layouts.app>
