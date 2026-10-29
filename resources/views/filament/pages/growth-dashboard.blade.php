<x-filament-panels::page>
    @php($box = 'padding:18px;border:1px solid #8886;border-radius:10px;overflow-wrap:anywhere')
    <div style="{{ $box }}">
        <p>Connections are set in Settings → Site settings → Analytics & growth. The report refreshes daily through the scheduler, or use Refresh report. Search Console data lags about 3 days. Opening this page does not call Google.</p>
    </div>
    @if($report)
        <p>Generated {{ $report['generated_at'] }} · last {{ $report['days'] }} days.</p>
        @foreach($report['errors'] as $source => $message)
            <p role="alert"><strong>{{ str($source)->replace('_', ' ')->title() }}:</strong> {{ $message }}</p>
        @endforeach
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px">
            <div style="{{ $box }}"><p>Leads</p><strong style="font-size:26px">{{ $report['leads']['total'] }}</strong><p style="font-size:12px">{{ $report['leads']['attributed'] }} with a known source</p></div>
            <div style="{{ $box }}"><p>Sessions</p><strong style="font-size:26px">{{ $report['analytics']['sessions'] ?? '—' }}</strong></div>
            <div style="{{ $box }}"><p>Lead conversion</p><strong style="font-size:26px">{{ $report['conversion_rate'] !== null ? $report['conversion_rate'].'%' : '—' }}</strong></div>
            <div style="{{ $box }}"><p>Search clicks</p><strong style="font-size:26px">{{ $report['search_console']['clicks'] ?? '—' }}</strong><p style="font-size:12px">{{ isset($report['search_console']) ? $report['search_console']['impressions'].' impressions · avg position '.$report['search_console']['position'] : '' }}</p></div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:14px">
            <div style="{{ $box }}"><h2 style="font-weight:600">Leads by source</h2>
                <ul>@forelse($report['leads']['sources'] as $row)<li>{{ $row['name'] }} — {{ $row['count'] }}</li>@empty<li>No leads in this period.</li>@endforelse</ul></div>
            <div style="{{ $box }}"><h2 style="font-weight:600">Top search queries</h2>
                <ul>@forelse($report['search_console']['queries'] ?? [] as $row)<li>{{ $row['name'] }} — {{ $row['clicks'] }} clicks, {{ $row['impressions'] }} impressions, position {{ $row['position'] }}</li>@empty<li>{{ $report['connections']['search_console'] ? 'No query data returned.' : 'Search Console is not connected.' }}</li>@endforelse</ul></div>
            <div style="{{ $box }}"><h2 style="font-weight:600">Top search pages</h2>
                <ul>@forelse($report['search_console']['pages'] ?? [] as $row)<li>{{ $row['name'] }} — {{ $row['clicks'] }} clicks</li>@empty<li>No page data.</li>@endforelse</ul></div>
            <div style="{{ $box }}"><h2 style="font-weight:600">Traffic channels</h2>
                <ul>@forelse($report['analytics']['channels'] ?? [] as $row)<li>{{ $row['name'] }} — {{ $row['value'] }} sessions</li>@empty<li>{{ $report['connections']['analytics'] ? 'No channel data returned.' : 'GA4 reporting is not connected.' }}</li>@endforelse</ul></div>
            <div style="{{ $box }}"><h2 style="font-weight:600">Most viewed pages</h2>
                <ul>@forelse($report['analytics']['pages'] ?? [] as $row)<li>{{ $row['name'] }} — {{ $row['value'] }} views</li>@empty<li>No page data.</li>@endforelse</ul></div>
            <div style="{{ $box }}"><h2 style="font-weight:600">Content plan</h2>
                <p>Next review: {{ $report['plan']['next_review_on'] ?? 'not scheduled' }}@if($report['plan']['overdue']) — <strong>overdue</strong>@endif</p>
                @if($report['content'])<p>Last content audit: {{ $report['content']['with_reviews'] }} of {{ $report['content']['pages'] }} pages need review.</p>@endif
                @if($report['plan']['notes'])<p style="white-space:pre-line">{{ $report['plan']['notes'] }}</p>@endif</div>
        </div>
    @else
        <p>No growth report yet. Use Refresh report or run <code>php artisan portfolio:sync-growth-report</code>.</p>
    @endif
</x-filament-panels::page>
