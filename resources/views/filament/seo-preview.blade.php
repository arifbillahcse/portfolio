<div style="display:grid;gap:18px;overflow-wrap:anywhere" aria-label="SEO preview">
    <div style="padding:18px;border:1px solid #8886;border-radius:10px">
        <strong>Search preview</strong>
        <p style="font-size:13px;margin-top:12px">{{ $preview['canonical'] }}</p>
        <p style="font-size:20px;font-weight:600;margin-top:6px">{{ $preview['title'] }}</p>
        <p style="margin-top:6px">{{ $preview['description'] }}</p>
        <p style="font-size:12px;margin-top:12px">Title: {{ $preview['title_length'] }} characters · Description: {{ $preview['description_length'] }} characters</p>
    </div>
    <div style="padding:18px;border:1px solid #8886;border-radius:10px">
        <strong>Social share preview</strong>
        @if($preview['image'])<img src="{{ $preview['image'] }}" alt="{{ $preview['image_alt'] }}" style="display:block;max-width:320px;width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-top:12px" loading="lazy">@endif
        <p style="font-weight:600;margin-top:12px">{{ $preview['social_title'] }}</p>
        <p style="margin-top:6px">{{ $preview['social_description'] }}</p>
    </div>
    <p style="font-size:13px">Approximate previews. Search engines and social platforms may show different text or crops.</p>
    @if($preview['warnings'])
        <ul style="list-style:disc;padding-left:22px">@foreach($preview['warnings'] as $warning)<li>{{ $warning }}</li>@endforeach</ul>
    @endif
</div>
