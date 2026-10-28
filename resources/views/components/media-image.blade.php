@props(['path', 'alt' => '', 'loading' => 'lazy'])
@if($media = \App\Support\MediaSeo::image($path, $alt))
<img src="{{ $media['url'] }}" alt="{{ $media['alt'] }}" @if(isset($media['width'])) width="{{ $media['width'] }}" height="{{ $media['height'] }}" @endif loading="{{ $loading }}" decoding="async" {{ $attributes }}>
@endif
