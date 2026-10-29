<x-filament-panels::page>
    <div style="padding:18px;border:1px solid #8886;border-radius:10px;overflow-wrap:anywhere">
        <p>Generate or refresh this private snapshot in your application terminal:</p>
        <code>php artisan portfolio:seo-content-audit --save</code>
        <p style="margin-top:8px">Add <code>--all</code> to include drafts, scheduled and sample content. Rerun after content, links, publication or SEO settings change. Reading this page does not scan the website.</p>
    </div>
    @if($report)
        <p>Snapshot: {{ $report['generated_at'] }} · Scope: {{ $report['scope'] }} · {{ $report['totals']['pages'] }} pages · {{ $report['totals']['with_reviews'] }} with review items.</p>
        @if(!$report['indexing_enabled'])<p><strong>Global indexing was disabled when this snapshot was generated.</strong> Content feedback does not enable indexing.</p>@endif
        <p style="font-size:13px">{{ $report['method'] }}</p>
        <div style="display:flex;gap:16px;flex-wrap:wrap">
            <label>Search <input wire:model.live.debounce.350ms="search" maxlength="200" type="search" style="border:1px solid #8886;padding:8px;border-radius:6px;background:transparent"></label>
            <label>Section <select wire:model.live="kind" style="border:1px solid #8886;padding:8px;background:transparent"><option value="all">All sections</option><option value="pages">Main pages</option><option value="blog">Posts</option><option value="projects">Projects</option><option value="services">Services</option></select></label>
            <label>Show <select wire:model.live="status" style="border:1px solid #8886;padding:8px;background:transparent"><option value="all">All pages</option><option value="review">Needs review</option></select></label>
        </div>
        @error('search')<p role="alert">{{ $message }}</p>@enderror
        @error('kind')<p role="alert">{{ $message }}</p>@enderror
        @error('status')<p role="alert">{{ $message }}</p>@enderror
        @forelse($entries as $entry)
            <article style="padding:20px;border:1px solid #8886;border-radius:10px;overflow-wrap:anywhere">
                <div style="display:flex;justify-content:space-between;gap:20px">
                    <h2 style="font-weight:600">{{ $entry['title'] }}</h2>
                    @if($editUrl = $this->editUrl($entry))<a href="{{ $editUrl }}" style="text-decoration:underline">Edit</a>@endif
                </div>
                <p style="font-size:13px;margin-top:8px">{{ $entry['url'] }} · {{ $entry['state'] }}</p>
                @if($entry['scope'] === 'body')<p style="font-size:13px">{{ $entry['stats']['words'] }} prose words · {{ $entry['incoming_contextual'] }} incoming contextual source pages · {{ $entry['review_count'] }} review items</p>@else<p style="font-size:13px">Metadata checks only</p>@endif
                <ul style="display:grid;gap:8px;margin-top:12px">
                    @forelse($entry['checks'] as $check)<li><strong>{{ $check['status'] === 'review' ? 'Review' : 'Note' }}:</strong> {{ $check['message'] }}</li>@empty<li>No issues found by these checks.</li>@endforelse
                </ul>
                @if($entry['suggestions'])<p style="margin-top:14px;font-weight:600">Potential source pages for a relevant contextual link</p><p style="font-size:12px">Based on shared title/topic words. Review relevance before adding a link.</p><ul style="list-style:disc;padding-left:22px">@foreach($entry['suggestions'] as $suggestion)<li>{{ $suggestion['title'] }} — {{ $suggestion['url'] }}</li>@endforeach</ul>@endif
            </article>
        @empty<p>No pages match these filters.</p>@endforelse
        {{ $entries->links() }}
    @else
        <p>No readable saved report is available. Run the command above. Reports are stored privately and are visible only to administrators.</p>
    @endif
</x-filament-panels::page>
