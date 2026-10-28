<x-filament-panels::page>
    <p>Public HTML 404 paths only. Queries, IP addresses and user agents are not stored. Counts are sampled: up to 30 updates per path and 300 total updates per minute; at most 5,000 paths are retained.</p>
    <p>Use <code>php artisan portfolio:prune-seo-404</code> or the daily scheduler to remove entries older than 30 days.</p>
    <label>Search paths <input wire:model.live.debounce.350ms="search" type="search" maxlength="200" style="border:1px solid #8886;border-radius:6px;padding:8px;background:transparent"></label>
    <div style="overflow-x:auto"><table style="width:100%;text-align:left">
        <thead><tr><th>Path</th><th>Sampled hits</th><th>Last seen</th><th>Actions</th></tr></thead>
        <tbody>@forelse($entries as $entry)<tr>
            <td style="padding:12px;overflow-wrap:anywhere">{{ $entry->path }}</td><td>{{ $entry->hits }}</td><td>{{ $entry->last_seen_at }}</td>
            <td><a href="{{ \App\Filament\Resources\SeoRedirects\SeoRedirectResource::getUrl('create',['source'=>$entry->path]) }}">Create redirect</a>
                <button type="button" wire:click="remove({{ $entry->id }})" style="margin-left:12px">Remove log</button></td>
        </tr>@empty<tr><td colspan="4">No matching 404 paths.</td></tr>@endforelse</tbody>
    </table></div>
    {{ $entries->links() }}
</x-filament-panels::page>
