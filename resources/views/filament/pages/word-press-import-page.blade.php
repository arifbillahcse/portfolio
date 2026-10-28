<x-filament-panels::page>
    <x-filament::section heading="Bring your WordPress posts into Laravel">
        <div class="space-y-4">
            <p>In WordPress, open Tools → Export → All content. Upload the XML here (up to 8 MB). Images are downloaded automatically from <strong>old.arifbillah.com</strong> into this website’s storage.</p>
            <p>Posts are imported as drafts. Existing imported posts are skipped, preserving your edits. Standard text, links, headings, code, lists and images are converted to Markdown. Review tables, shortcodes and page-builder layouts before publishing. One category per post is supported; tags and comments are not imported.</p>
            <p>The cPanel queue Cron must be running for <code>mail,default</code>. Keep your old WordPress site online until all images have been verified.</p>
            <form wire:submit="startImport" class="space-y-4">
                <label for="wordpress-xml" class="block font-medium">WordPress export (.xml)</label>
                <input id="wordpress-xml" type="file" wire:model="xml" accept=".xml,text/xml,application/xml" required>
                @error('xml') <p role="alert" class="text-danger-600">{{ $message }}</p> @enderror
                <p wire:loading wire:target="xml">Uploading XML…</p>
                <div><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="xml,startImport">Import as drafts</x-filament::button></div>
            </form>
        </div>
    </x-filament::section>

    <div wire:poll.10s>
        @if($runs->isNotEmpty())
            <x-filament::section heading="Import history">
                <div class="flex flex-wrap gap-2">
                    @foreach($runs as $history)
                        <x-filament::button color="gray" wire:click="selectRun({{ $history->id }})">#{{ $history->id }} · {{ $history->filename }}</x-filament::button>
                    @endforeach
                </div>
            </x-filament::section>
        @endif
        @if($run)
            <div class="mt-6">
                <x-filament::section heading="Import #{{ $run->id }} · {{ $run->total }} posts">
                    <div class="space-y-4">
                        <p role="status">Imported: {{ $counts['imported'] ?? 0 }} · With image issues: {{ $counts['warnings'] ?? 0 }} · Skipped: {{ $counts['skipped'] ?? 0 }} · Pending: {{ ($counts['queued'] ?? 0) + ($counts['processing'] ?? 0) }} · Failed: {{ $counts['failed'] ?? 0 }}</p>
                        <x-filament::button color="gray" wire:click="resumeImport" wire:loading.attr="disabled">Resume / retry failed images</x-filament::button>
                        <p>Resume continues unfinished work. Successful downloads are reused. On image retry, your edited title, content, publication status and category are preserved.</p>
                        @foreach($items as $item)
                            <div class="border-t border-gray-200 py-4 dark:border-gray-700">
                                <p><strong>{{ $item->title }}</strong> · {{ ucfirst($item->status) }}</p>
                                @if($item->post_id)
                                    <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\Posts\PostResource::getUrl('edit', ['record' => $item->post_id]) }}">Review post</a>
                                @endif
                                @if($item->error)
                                    <p class="whitespace-pre-line break-words">{{ $item->error }}</p>
                                @endif
                                @foreach($item->payload['notes'] ?? [] as $note)
                                    <p>{{ $note }}</p>
                                @endforeach
                            </div>
                        @endforeach
                        {{ $items->links() }}
                    </div>
                </x-filament::section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
