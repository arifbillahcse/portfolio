<x-filament-panels::page>
    <x-filament::section heading="Search visibility">
        @if(config('portfolio.indexable'))
            <p>Global indexing is enabled. Each page keeps its own visibility settings. Drafts and private previews remain excluded.</p>
        @else
            <p><strong>Global indexing is disabled.</strong> All public pages currently request noindex and are excluded from the sitemap. Page settings cannot override this restriction.</p>
            <details style="margin-top:12px"><summary style="cursor:pointer">Server indexing configuration</summary>
                <p style="margin-top:8px">On the production server, review <code>SITE_INDEXABLE</code> and the cached configuration. Enable indexing when the intended public content is ready.</p>
            </details>
        @endif
        <p style="margin-top:12px">Choose Edit SEO in the overview or select a page tab below. Blank fields use defaults; previews follow your edits. Save SEO settings to apply changes.</p>
        <p style="font-size:13px;margin-top:8px">Post, service and project detail pages have SEO fields in their own content editors.</p>
    </x-filament::section>
    <x-filament::section heading="Page overview">
        <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px" aria-label="SEO overview totals">
            <x-filament::badge color="gray">{{ count($pages) }} main pages</x-filament::badge>
            <x-filament::badge :color="$reviewCount ? 'warning' : 'success'">{{ $reviewCount }} need metadata review</x-filament::badge>
            <x-filament::badge :color="$noindexCount ? 'warning' : 'gray'">{{ $noindexCount }} request noindex</x-filament::badge>
        </div>
        <p style="font-size:13px;margin-bottom:16px">This overview follows your current edits. Save to apply changes. Default metadata is valid; custom text is optional. These checks do not measure rankings or confirm Google indexing.</p>
        <div style="overflow-x:auto">
            <table style="width:100%;min-width:720px;border-collapse:collapse;text-align:left">
                <caption style="text-align:left;font-weight:600;padding-bottom:12px">Main-page search appearance and visibility</caption>
                <thead><tr>
                    <th scope="col" style="padding:12px">Page</th>
                    <th scope="col" style="padding:12px">Search appearance</th>
                    <th scope="col" style="padding:12px">Visibility</th>
                    <th scope="col" style="padding:12px">Metadata review</th>
                    <th scope="col" style="padding:12px">Actions</th>
                </tr></thead>
                <tbody>
                    @foreach($pages as $key => $item)
                        <tr wire:key="seo-overview-{{ $key }}" style="border-top:1px solid #8884;vertical-align:top">
                            <th scope="row" style="padding:12px">{{ $item['label'] }}</th>
                            <td style="padding:12px;max-width:360px;overflow-wrap:anywhere">
                                <p style="font-weight:600">{{ $item['title'] ?: 'Missing title' }}</p>
                                <p style="font-size:12px;margin-top:4px">{{ $item['custom_title'] ? 'Custom title' : 'Default title' }}</p>
                                <p style="font-size:13px;margin-top:8px">{{ \Illuminate\Support\Str::limit($item['description'], 150) ?: 'Missing description' }}</p>
                                <p style="font-size:12px;margin-top:4px">{{ $item['custom_description'] ? 'Custom description' : 'Default description' }}</p>
                            </td>
                            <td style="padding:12px;max-width:240px">
                                <x-filament::badge :color="str_contains($item['robots'], 'noindex') ? 'warning' : 'success'">{{ $item['robots'] }}</x-filament::badge>
                                @if(config('portfolio.indexable'))
                                    <p style="font-size:12px;margin-top:8px">{{ $item['visibility_reason'] }}</p>
                                    <p style="font-size:12px;margin-top:8px">{{ $item['sitemap'] ? 'Included in sitemap' : 'Excluded from sitemap' }}</p>
                                @endif
                                @if($item['canonical_elsewhere'])<p style="font-size:12px;margin-top:4px">A different preferred URL is set in Advanced.</p>@endif
                            </td>
                            <td style="padding:12px;max-width:240px">
                                @if($item['reviews'])
                                    <details><summary style="cursor:pointer">{{ count($item['reviews']) }} {{ count($item['reviews']) === 1 ? 'item' : 'items' }} to review</summary>
                                        <ul style="list-style:disc;padding-left:18px;font-size:13px;margin-top:8px">@foreach($item['reviews'] as $review)<li>{{ $review }}</li>@endforeach</ul>
                                    </details>
                                @else
                                    <x-filament::badge color="success">Metadata ready</x-filament::badge>
                                @endif
                            </td>
                            <td style="padding:12px;white-space:nowrap">
                                <a href="{{ route('filament.admin.pages.seo-settings', ['page' => $key]) }}#page-seo-editor" wire:click.prevent="$set('activePage', '{{ $key }}')" x-on:click="document.getElementById('page-seo-editor').scrollIntoView({ behavior: 'smooth', block: 'start' })" style="text-decoration:underline" aria-label="Edit {{ $item['label'] }} SEO">Edit SEO</a>
                                <p style="margin-top:12px"><a href="{{ $item['url'] }}" target="_blank" rel="noopener" style="text-decoration:underline" aria-label="View {{ $item['label'] }} page (opens in a new tab)">View page</a></p>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
    <form id="page-seo-editor" wire:submit="save" style="display:grid;gap:24px;scroll-margin-top:90px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap">
            <h2 style="font-size:18px;font-weight:600" aria-live="polite">Editing {{ $activePageLabel }} SEO</h2>
            <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">Save SEO settings</x-filament::button>
        </div>
        {{ $this->form }}
    </form>
</x-filament-panels::page>
