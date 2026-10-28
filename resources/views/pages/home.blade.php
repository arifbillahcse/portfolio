<x-layouts.app :seo="$seo" :site="$site" :profile="$profile" :contact-links="$contactLinks" :title="$site->seo_title ?: $profile->name" :description="$site->seo_description ?: $profile->home_intro" page="home">
<main id="main" tabindex="-1">
    <section class="home-hero" id="home" aria-labelledby="home-title">
        <div class="container hero">
            <div class="hero-copy reveal">
                <p class="kicker">Web development &amp; cloud infrastructure</p>
                <h1 id="home-title">@if(str_contains($profile->home_heading ?? '', 'servers')){{ \Illuminate\Support\Str::before($profile->home_heading, 'servers') }}<span class="accent">servers</span>{{ \Illuminate\Support\Str::after($profile->home_heading, 'servers') }}@else{{ $profile->home_heading ?: $profile->headline }}@endif</h1>
                <p class="copy">{{ $profile->home_intro }}</p>
                <div class="actions">
                    <a class="btn primary" href="#contact">Let’s talk <span aria-hidden="true">↗</span></a>
                    <a class="btn" href="{{ $projects->isNotEmpty() ? '#projects' : route('services') }}">{{ $projects->isNotEmpty() ? 'See my work' : 'Explore services' }} <span aria-hidden="true">→</span></a>
                </div>
                <div class="hero-keywords" aria-label="Core skills">
                    @foreach(collect($profile->skill_groups ?? [])->flatMap(fn ($group) => array_slice($group['skills'] ?? [], 0, 3))->unique()->take(6) as $skill)
                        <span>{{ $skill }}</span>
                    @endforeach
                </div>
            </div>
            <div class="visual reveal">
                <div class="portrait-wrap"><img class="portrait" src="{{ $portrait }}" alt="Arif, full-stack developer and Linux system administrator" width="480" height="520" fetchpriority="high"></div>
                <span class="tech t1">WordPress</span>
                <span class="tech t2">Laravel</span>
                <span class="tech t3">Linux &amp; cloud</span>
            </div>
        </div>
    </section>

    <section class="home-story" id="about" aria-labelledby="story-title">
        <div class="container">
            <div class="home-story-layout">
                <div class="home-section-heading reveal">
                    <span class="label">01 / My story</span>
                    <h2 id="story-title">From code<br>to cloud.</h2>
                    <p class="lead">Two connected disciplines. One person who understands your whole platform.</p>
                    <a class="text-link" href="{{ route('about') }}">Get to know me <span aria-hidden="true">↗</span></a>
                </div>
                <div class="story-timeline reveal">
                    @foreach($profile->journey ?? [] as $point)
                        <article class="story-point">
                            <span class="story-year">{{ $point['label'] ?? '' }}</span>
                            <h3>{{ $point['title'] ?? '' }}</h3>
                            <p>{{ $point['description'] ?? '' }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
            <div class="expertise-band reveal">
                @foreach($profile->skill_groups ?? [] as $group)
                    <article class="expertise-item">
                        <div class="expertise-top"><span class="expertise-num">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $group['title'] ?? '' }}</h3></div>
                        <div class="skill-tags">@foreach($group['skills'] ?? [] as $skill)<span>{{ $skill }}</span>@endforeach</div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <section class="home-services" id="services" aria-labelledby="services-title">
        <div class="container">
            <div class="home-section-top reveal">
                <div class="home-section-heading"><span class="label">02 / How I help</span><h2 id="services-title">Build it. Grow it.<br>Keep it running.</h2></div>
                <div class="home-section-note"><p>From your first idea to a secure, dependable platform. A connected process, with one point of contact.</p><a class="text-link" href="{{ route('services') }}">Explore all services <span aria-hidden="true">↗</span></a></div>
            </div>
            <div class="process reveal">
                <article class="process-step"><span class="step-marker">01</span><span class="step-phase">Build</span><h3>Web &amp; full-stack development</h3><ul class="step-list"><li>Planning and user experience</li><li>Frontend and Laravel development</li><li>WooCommerce and integrations</li></ul></article>
                <article class="process-step"><span class="step-marker">02</span><span class="step-phase">Grow</span><h3>SEO &amp; performance</h3><ul class="step-list"><li>Technical and on-page SEO</li><li>Speed and Core Web Vitals</li><li>Analytics and conversion tracking</li></ul></article>
                <article class="process-step"><span class="step-marker">03</span><span class="step-phase">Launch &amp; maintain</span><h3>Linux &amp; server care</h3><ul class="step-list"><li>Linux server configuration</li><li>Security, monitoring, and backups</li><li>Updates and ongoing maintenance</li></ul></article>
            </div>
        </div>
    </section>

    @if($projects->isNotEmpty())
    <section class="home-projects" id="projects" aria-labelledby="projects-title">
        <div class="container">
            <div class="home-section-top reveal">
                <div class="home-section-heading"><span class="label">03 / Selected work</span><h2 id="projects-title">A few things<br>I’ve helped build.</h2></div>
                <a class="text-link" href="{{ route('projects') }}">Browse all projects <span aria-hidden="true">↗</span></a>
            </div>
            <div class="portfolio-grid reveal">@foreach($projects as $project) @include('partials.project-card') @endforeach</div>
        </div>
    </section>
    @endif

    <section class="contact" id="contact" aria-labelledby="contact-title">
        <div class="container home-contact-layout">
            <div class="home-section-heading reveal">
                <span class="label">Let’s work together</span>
                <h2 id="contact-title">Your next project.<br><span class="accent">Let’s make it happen.</span></h2>
                <p class="lead">Tell me what you’re building or what needs fixing. We’ll find the right next step.</p>
            </div>
            <div class="home-contact-connect reveal">
                <p class="connect-caption">Start a conversation</p>
                <div class="social-connect" aria-label="Connect with Arif"><a class="social-icon whatsapp-icon" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.4-4.3a8.5 8.5 0 1 1 15.6-4.5Z"/><path d="M8.5 8.2c.3 3.7 3.3 6.3 6.9 6.8l1.1-1.7-2.5-1.1-.8 1c-1.6-.6-2.8-1.7-3.5-3.2l.9-.8-1-2.5-1.1 1.5Z"/></svg></a>@if($contactLinks['facebook'])<a class="social-icon" href="{{ $contactLinks['facebook'] }}" target="_blank" rel="noopener" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M14 8.5V7c0-.8.5-1 1-1h2V2.5h-3c-3.2 0-4.5 1.9-4.5 4.3v1.7H7v4h2.5V22H14v-9.5h3l.5-4H14Z"/></svg></a>@endif@if($contactLinks['linkedin'])<a class="social-icon" href="{{ $contactLinks['linkedin'] }}" target="_blank" rel="noopener" aria-label="LinkedIn"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5.2 7.5A2.3 2.3 0 1 0 5.2 3a2.3 2.3 0 0 0 0 4.5ZM3.3 9h3.8v12H3.3V9Zm6.2 0h3.6v1.6h.1c.5-1 1.8-2 3.7-2 4 0 4.8 2.6 4.8 6V21h-3.8v-5.7c0-1.4 0-3.2-2-3.2s-2.3 1.5-2.3 3V21H9.5V9Z"/></svg></a>@endif<a class="social-icon" href="{{ $contactLinks['email'] }}" aria-label="Email"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></a></div>
                <div class="contact-actions"><a class="btn primary whatsapp" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener">Message on WhatsApp <span aria-hidden="true">↗</span></a><button class="btn hire-button" type="button" id="openHire">Hire me</button></div>
            </div>
        </div>
    </section>
</main>
<dialog class="hire-modal" id="hireModal" aria-labelledby="hireTitle"><div class="modal-inner"><div class="modal-head"><div><h3 id="hireTitle">Tell me about your project</h3><p>Share your requirements and I’ll get back to you.</p></div><button class="modal-close" type="button" id="closeHire" aria-label="Close contact form">×</button></div><form class="hire-form" id="hireForm" action="{{ route('inquiries.store') }}" method="post" data-server-inquiry>@csrf<input type="hidden" name="source" value="home">@include('partials.inquiry-errors')<div class="inquiry-trap" aria-hidden="true"><label for="hireWebsite">Leave this field empty</label><input id="hireWebsite" name="website" type="text" tabindex="-1" autocomplete="off"></div><div class="field"><label for="clientName">Name</label><input id="clientName" name="name" type="text" autocomplete="name" maxlength="120" required value="{{ old('name') }}"></div><div class="field"><label for="clientEmail">Email</label><input id="clientEmail" name="email" type="email" autocomplete="email" maxlength="254" required value="{{ old('email') }}"></div><div class="field full"><label for="clientPhone">Phone</label><input id="clientPhone" name="phone" type="tel" autocomplete="tel" maxlength="40" value="{{ old('phone') }}"></div><div class="field full"><label for="clientMessage">Message</label><textarea id="clientMessage" name="message" minlength="10" maxlength="3000" required placeholder="Tell me what you would like to build...">{{ old('message') }}</textarea></div><button class="btn primary form-submit" type="submit">Send inquiry</button><p class="form-status field full" role="status" aria-live="polite" tabindex="-1"></p></form></div></dialog>
</x-layouts.app>
