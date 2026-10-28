<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\SeoRedirect;
use App\Support\SeoRedirects;
use Illuminate\Database\Seeder;

class SeoRedirectSeeder extends Seeder
{
    public function run(): void
    {
        $redirects = app(SeoRedirects::class);
        Post::query()->published()->whereNotNull('wordpress_path')->each(function (Post $post) use ($redirects): void {
            $source = $redirects->path($post->wordpress_path);
            $destination = '/blog/'.$post->slug;
            if ($source && $source !== '/' && $source !== $destination && $redirects->isPublicDestination($destination)) {
                SeoRedirect::query()->firstOrCreate(['source_path' => $source], ['destination_path' => $destination, 'status_code' => 301, 'enabled' => false, 'notes' => 'Historical WordPress path suggestion. Review before enabling.']);
            }
        });
    }
}
