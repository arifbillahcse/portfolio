<x-filament-panels::page>
    <x-filament::section heading="Search visibility">
        @if(config('portfolio.indexable'))
            <p>Global indexing is enabled. Each page keeps its own visibility settings. Drafts and private previews remain excluded.</p>
        @else
            <p><strong>Global indexing is disabled.</strong> Public pages currently request noindex. An individual page cannot override this restriction.</p>
            <p>On the production server, review <code>SITE_INDEXABLE</code> and the cached configuration. Enable it only when the intended public content is ready.</p>
        @endif
        <p>Edit post, service and project metadata in their content editors. Home fields below override the existing Site Settings home metadata only when filled.</p>
    </x-filament::section>
    <form wire:submit="save" style="display:grid;gap:24px">
        {{ $this->form }}
        <div><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">Save SEO settings</x-filament::button></div>
    </form>
</x-filament-panels::page>
