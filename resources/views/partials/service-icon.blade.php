<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($icon)
@case('cloud')<path d="M6 18a5 5 0 0 1-1-9 7 7 0 0 1 13-1 5 5 0 0 1 0 10H6Z"/>@break
@case('server')<rect x="3" y="3" width="18" height="7" rx="2"/><rect x="3" y="14" width="18" height="7" rx="2"/><path d="M7 6h.01M7 17h.01m5-11h5m-5 11h5"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>@break
@case('store')<path d="m3 9 2-6h14l2 6M5 12v9h14v-9M9 21v-7h6v7"/><path d="M3 9c0 4 6 4 6 0 0 4 6 4 6 0 0 4 6 4 6 0"/>@break
@case('link')<path d="m10 13 4-4M8 16l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0m2 1 1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0"/>@break
@default<path d="m8 6-6 6 6 6m8-12 6 6-6 6m-3-15-2 18"/>
@endswitch
</svg>
