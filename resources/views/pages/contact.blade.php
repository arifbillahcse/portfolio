<x-layouts.app :seo="$seo" :site="$site" :profile="$profile" :contact-links="$contactLinks" :title="'Contact | '.$profile->name" description="Contact Arif Billah to discuss web development, WordPress, Laravel, Linux servers, and cloud infrastructure. Connect through WhatsApp, email, Facebook, or LinkedIn." page="contact">
<main id="main">
    <section class="contact-intro wrap" aria-labelledby="contactTitle">
      <span class="eyebrow">Let’s start a conversation</span>
      <h1 id="contactTitle">Your next project.<br><span>Our first conversation.</span></h1>
      <p>Have a website to build, an application to improve, or a server that needs attention? Tell me what you have in mind. I’ll help you define an approach and a realistic scope.</p>
    </section>
    <section class="contact-layout wrap" aria-label="Get in touch">
      <div class="contact-details">
        <div class="profile"><img src="{{ $portrait }}" width="96" height="96" alt="Arif Billah"><div><h2>{{ $profile->name }}</h2><p>{{ $site->tagline }}</p><span class="location">Based in Bangladesh · Working remotely</span></div></div>
        <h3 class="connect-title">Choose how we connect.</h3>
        <div class="contact-methods">
          <a class="contact-method" href="{{ $contactLinks['whatsapp'] }}" target="_blank" rel="noopener"><span class="method-icon whatsapp-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4l-4.4 1.4 1.4-4.3a8.5 8.5 0 1 1 15.6-4.5Z"/><path d="M8.5 8c.2 3.7 3.2 6.5 7 7l1-1.7-2.5-1.1-.8 1c-1.6-.6-2.8-1.7-3.5-3.2l.9-.8L9.6 7.7 8.5 8Z"/></svg></span><span class="method-text"><strong>WhatsApp</strong><span>{{ $site->whatsapp_number }}</span></span><span class="method-arrow" aria-hidden="true">↗</span></a>
          <a class="contact-method" href="{{ $contactLinks['email'] }}"><span class="method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg></span><span class="method-text"><strong>Email</strong><span>{{ $site->contact_email }}</span></span><span class="method-arrow" aria-hidden="true">↗</span></a>
          @if($contactLinks['facebook'])<a class="contact-method" href="{{ $contactLinks['facebook'] }}" target="_blank" rel="noopener"><span class="method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 8.5V7c0-.8.5-1 1-1h2V2.5h-3c-3.2 0-4.5 1.9-4.5 4.3v1.7H7v4h2.5V22H14v-9.5h3l.5-4H14Z"/></svg></span><span class="method-text"><strong>Facebook</strong><span>View profile</span></span><span class="method-arrow" aria-hidden="true">↗</span></a>@endif
          @if($contactLinks['linkedin'])<a class="contact-method" href="{{ $contactLinks['linkedin'] }}" target="_blank" rel="noopener"><span class="method-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5.2 7.5A2.3 2.3 0 1 0 5.2 3a2.3 2.3 0 0 0 0 4.5ZM3.3 9h3.8v12H3.3V9Zm6.2 0h3.6v1.6h.1c.5-1 1.8-2 3.7-2 4 0 4.8 2.6 4.8 6V21h-3.8v-5.7c0-1.4 0-3.2-2-3.2s-2.3 1.5-2.3 3V21H9.5V9Z"/></svg></span><span class="method-text"><strong>LinkedIn</strong><span>View profile</span></span><span class="method-arrow" aria-hidden="true">↗</span></a>@endif
        </div>
        <div class="conversation-note"><span class="eyebrow">A helpful starting point</span><p>Share your project goals, current website, preferred timeline, and any technical challenges. It’s okay if you’re still figuring out the details.</p></div>
      </div>
      <div class="form-panel">
        <span class="eyebrow">Project inquiry</span><h2>Tell me what you’re building.</h2><p class="form-intro">A few details will help us start with the right questions.</p>
        <form id="contactForm" action="{{ route('inquiries.store') }}" method="post" data-server-inquiry>
          @csrf
          <input type="hidden" name="source" value="contact">
          @include('partials.inquiry-errors')
          <div class="inquiry-trap" aria-hidden="true"><label for="contactWebsite">Leave this field empty</label><input id="contactWebsite" name="website" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="form-grid">
            <div class="field"><label for="clientName">Your name <span aria-hidden="true">*</span></label><input id="clientName" name="name" type="text" autocomplete="name" placeholder="Your full name" maxlength="120" required value="{{ old('name') }}"></div>
            <div class="field"><label for="clientEmail">Email address <span aria-hidden="true">*</span></label><input id="clientEmail" name="email" type="email" autocomplete="email" placeholder="you@example.com" maxlength="254" required value="{{ old('email') }}"></div>
            <div class="field full"><label for="clientPhone">Phone number <span class="optional">(optional)</span></label><input id="clientPhone" name="phone" type="tel" autocomplete="tel" placeholder="Include your country code" maxlength="40" value="{{ old('phone') }}"></div>
            <div class="field full"><label for="clientMessage">Your message <span aria-hidden="true">*</span></label><textarea id="clientMessage" name="message" placeholder="What would you like to build or improve?" rows="6" minlength="10" maxlength="3000" required>{{ old('message') }}</textarea></div>
          </div>
          <p class="form-note" id="formNote">Send your requirements directly. You can also contact me through WhatsApp or email.</p>
          <button class="submit-button" type="submit" aria-describedby="formNote">Send inquiry <span aria-hidden="true">↗</span></button>
          <p class="form-status" id="formStatus" role="status" aria-live="polite" tabindex="-1">{{ session('inquiry_success') }}</p>
          <noscript><p>To send an inquiry, <a href="{{ $contactLinks['whatsapp'] }}">message me directly on WhatsApp</a> or <a href="{{ $contactLinks['email'] }}">send an email</a>.</p></noscript>
        </form>
      </div>
    </section>
    <section class="next-steps" aria-labelledby="nextTitle"><div class="wrap"><div class="next-heading"><span class="eyebrow">What happens next</span><h2 id="nextTitle">A clear start.<br>A practical way forward.</h2></div><ol class="next-grid"><li><span class="step-number">01</span><h3>Share your requirements</h3><p>Tell me your goals and what you need help with.</p></li><li><span class="step-number">02</span><h3>Discuss the approach</h3><p>We clarify the requirements, priorities, and a realistic scope.</p></li><li><span class="step-number">03</span><h3>Agree on the next step</h3><p>We align on deliverables and the plan before work begins.</p></li></ol></div></section>
  </main>
</x-layouts.app>
