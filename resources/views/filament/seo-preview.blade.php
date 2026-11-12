<div style="display:grid;gap:18px;overflow-wrap:anywhere" aria-label="SEO preview">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:18px;align-items:start">
        <div style="padding:18px;border:1px solid #8886;border-radius:10px">
            <strong>Search preview</strong>
            <p style="font-size:12px;margin-top:8px">{{ $preview['title_source'] }} &middot; {{ $preview['description_source'] }}</p>
            <p style="font-size:13px;margin-top:12px">{{ $preview['canonical'] }}</p>
            <p style="font-size:20px;font-weight:600;margin-top:6px">{{ $preview['title'] }}</p>
            <p style="margin-top:6px">{{ $preview['description'] }}</p>
            <p style="font-size:12px;margin-top:12px">Title: {{ $preview['title_length'] }} characters · Description: {{ $preview['description_length'] }} characters</p>
            <p style="font-size:12px;margin-top:6px">Length is a guide. Review the final wording, including your title template.</p>
        </div>
        <div style="padding:18px;border:1px solid #8886;border-radius:10px">
            <strong>Social share preview</strong>
            <p style="font-size:12px;margin-top:8px">Blank social text fields use the search title and description.</p>
            @if($preview['image'])<img src="{{ $preview['image'] }}" alt="{{ $preview['image_alt'] }}" style="display:block;max-width:320px;width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-top:12px" loading="lazy">@endif
            <p style="font-weight:600;margin-top:12px">{{ $preview['social_title'] }}</p>
            <p style="margin-top:6px">{{ $preview['social_description'] }}</p>
        </div>
    </div>
    <p style="font-size:13px">Approximate previews. Search engines and social platforms may show different text or crops.</p>
    @include('filament.content-analysis', ['analysis' => $preview['analysis'], 'additionalWarnings' => $preview['warnings']])
</div>
