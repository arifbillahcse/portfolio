<section aria-label="Content SEO analysis" style="padding:18px;border:1px solid #8886;border-radius:10px">
    <h3 style="font-weight:600">Content SEO review</h3>
    <p style="font-size:13px;margin:10px 0">Editorial checks only. No ranking score, density target or minimum word count. Recommendations do not block publishing.</p>
    @if($analysis['scope'] === 'body')
        <p style="font-size:13px">{{ $analysis['stats']['words'] }} estimated prose words · {{ $analysis['stats']['headings'] }} body headings · {{ $analysis['stats']['internal_links'] }} contextual internal links · {{ $analysis['stats']['external_links'] }} external links · {{ $analysis['stats']['code_blocks'] }} code blocks</p>
        <p style="font-size:12px;margin-top:6px">The template provides the main H1. Code is excluded from prose counts. Link availability and cross-page comparisons are in the saved site-wide Content audit.</p>
    @else
        <p style="font-size:13px">Main-page metadata review only. Static layouts, navigation and listing content are outside this editor analysis.</p>
    @endif
    <ul style="display:grid;gap:10px;margin-top:14px">
        @forelse($analysis['checks'] as $check)
        <li><strong>{{ $check['status'] === 'review' ? 'Review' : 'Note' }}:</strong> {{ $check['message'] }}</li>
        @empty<li>No issues found by these automated checks. Human review is still needed.</li>@endforelse
    </ul>
    @if($analysis['outline'])
    <details style="margin-top:16px"><summary>Body heading outline ({{ $analysis['outline_total'] }})</summary>
        <ul style="list-style:disc;padding-left:22px;margin-top:10px">
            @foreach($analysis['outline'] as $heading)<li>H{{ $heading['level'] }} — {{ $heading['text'] }}</li>@endforeach
        </ul>
        @if($analysis['outline_total'] > 20)<p>Showing the first 20 headings.</p>@endif
    </details>
    @endif
</section>
