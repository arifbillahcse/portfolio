@props(['path', 'alt' => '', 'loading' => 'lazy', 'sizes' => '(min-width: 1280px) 1100px, calc(100vw - 48px)'])
@if($media = \App\Support\MediaSeo::image($path, $alt))
@php($variants = app(\App\Support\ResponsiveImages::class)->candidates($path))
@if($variants)<picture class="responsive-picture"><source type="image/webp" srcset="{{ collect($variants)->map(fn ($variant) => $variant['url'].' '.$variant['width'].'w')->implode(', ') }}" sizes="{{ $sizes }}">@endif
<img src="{{ $media['url'] }}" alt="{{ $media['alt'] }}" @if(isset($media['width'])) width="{{ $media['width'] }}" height="{{ $media['height'] }}" @endif loading="{{ $loading }}" decoding="async" {{ $attributes }}>
@if($variants)</picture>@endif
@endif
