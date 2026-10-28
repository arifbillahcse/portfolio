@php echo '<?xml version="1.0" encoding="UTF-8"?>'; @endphp
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
@foreach($entries as $entry)<url><loc>{{ $entry['url'] }}</loc>@if($entry['modified'])<lastmod>{{ $entry['modified'] }}</lastmod>@endif @foreach($entry['images'] ?? [] as $image)<image:image><image:loc>{{ $image }}</image:loc></image:image>@endforeach</url>@endforeach
</urlset>
