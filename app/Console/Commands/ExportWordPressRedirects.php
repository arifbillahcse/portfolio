<?php

namespace App\Console\Commands;

use App\Models\Post;
use App\Support\SeoMetadata;
use App\Support\SeoRedirects;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('portfolio:export-wordpress-redirects {--dry-run : Check published destinations without writing a file}')]
#[Description('Export exact Apache rules for the old WordPress host; never applies them to the server')]
class ExportWordPressRedirects extends Command
{
    public function handle(SeoRedirects $redirects): int
    {
        $origin = rtrim(config('app.url'), '/');
        $host = config('wordpress.source_host');
        if (! SeoMetadata::validCanonical($origin) || ! preg_match('/\A[a-z0-9.-]+\z/i', $host) || parse_url($origin, PHP_URL_HOST) === $host) {
            $this->error('Configure distinct valid canonical and old WordPress hosts first.');

            return self::FAILURE;
        }
        $lines = ['# Install on '.$host.' only, before the WordPress rules. Do not replace media or WordPress configuration.', '<IfModule mod_rewrite.c>', 'RewriteEngine On'];
        $base = ['RewriteCond %{HTTP_HOST} ^'.preg_quote($host, '~').'$ [NC]', 'RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$'];
        $count = 0;
        foreach (Post::query()->published()->whereNotNull('wordpress_id')->orderBy('wordpress_id')->get() as $post) {
            $path = '/blog/'.$post->slug;
            if (! $redirects->isPublicDestination($path)) {
                continue;
            }
            $target = $origin.'/blog/'.rawurlencode($post->slug);
            if (! SeoMetadata::validCanonical($target)) {
                continue;
            }
            $lines = [...$lines, ...$base, 'RewriteCond %{QUERY_STRING} ^p='.$post->wordpress_id.'$', 'RewriteRule ^$ '.$target.' [R=301,L,QSD,NE]'];
            $old = $post->wordpress_path;
            if ($old && $old !== '/' && $redirects->path($old) === $old && ! str_contains($old, '?') && $old !== $path) {
                $lines = [...$lines, ...$base, 'RewriteRule ^'.preg_quote(ltrim($old, '/'), '~').'/?$ '.$target.' [R=301,L,QSD,NE]'];
            }
            $count++;
        }
        foreach (['3730' => 'about', '3761' => 'services'] as $id => $page) {
            $lines = [...$lines, ...$base, 'RewriteCond %{QUERY_STRING} ^page_id='.$id.'$', 'RewriteRule ^$ '.$origin.'/'.$page.' [R=301,L,QSD,NE]'];
        }
        $lines = [...$lines, ...$base, 'RewriteCond %{QUERY_STRING} ^$', 'RewriteRule ^$ '.$origin.'/ [R=301,L,NE]', '</IfModule>'];
        $this->info('Prepared '.$count.' published post mappings and verified Home/About/Services mappings. Draft and missing post IDs are excluded.');
        if ($this->option('dry-run')) {
            $this->line('No file was written.');

            return self::SUCCESS;
        }
        $file = 'seo/old-wordpress-redirects-'.now()->format('Ymd-His').'.htaccess';
        if (Storage::disk('local')->exists($file)) {
            $this->error('An export already exists for this timestamp. Retry without replacing it.');

            return self::FAILURE;
        }
        if (! Storage::disk('local')->put($file, implode("\n", $lines)."\n")) {
            $this->error('Could not write the private redirect export.');

            return self::FAILURE;
        }
        $this->line('Saved private export: '.Storage::disk('local')->path($file));
        $this->line('Review and install on the old host only. No server rules have been changed.');

        return self::SUCCESS;
    }
}
