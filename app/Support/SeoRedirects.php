<?php

namespace App\Support;

use App\Models\Post;
use App\Models\Project;
use App\Models\SeoRedirect;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class SeoRedirects
{
    /** @return array{created: int, skipped: int} */
    public function import(mixed $entries, bool $dryRun = false): array
    {
        $data = Validator::make(['entries' => $entries], [
            'entries' => ['required', 'array', 'list', 'min:1', 'max:500'],
            'entries.*' => ['required', 'array:source_path,destination_path,status_code,enabled,notes'],
            'entries.*.source_path' => ['required', 'string', 'max:2048'],
            'entries.*.destination_path' => ['required', 'string', 'max:2048'],
            'entries.*.status_code' => ['required', 'integer', 'in:301,302'],
            'entries.*.enabled' => ['required', 'boolean'],
            'entries.*.notes' => ['nullable', 'string', 'max:1000'],
        ])->validate()['entries'];
        $pending = [];
        $sources = [];
        $skipped = 0;
        foreach ($data as $entry) {
            $source = $this->path($entry['source_path']);
            if ($source && in_array($source, $sources, true)) {
                throw ValidationException::withMessages(['json' => 'Duplicate normalized source in the import catalogue.']);
            }
            $sources[] = $source;
            if ($source && SeoRedirect::query()->where('source_path', $source)->exists()) {
                $skipped++;

                continue;
            }
            $record = new SeoRedirect($entry);
            $this->validateRule($record);
            $pending[] = $record;
        }
        if (! $dryRun) {
            DB::transaction(function () use ($pending): void {
                foreach ($pending as $record) {
                    $record->save();
                }
            });
        }

        return ['created' => count($pending), 'skipped' => $skipped];
    }

    public function path(mixed $input): ?string
    {
        if (! is_string($input) || strlen($input) > 2048 || ! str_starts_with($input, '/') || str_starts_with($input, '//') || preg_match('/[\s\\\\#]/', $input)) {
            return null;
        }
        $parts = parse_url($input);
        if ($parts === false || isset($parts['host']) || isset($parts['scheme'])) {
            return null;
        }
        $path = rawurldecode($parts['path'] ?? '/');
        if (strlen($path) > 512 || preg_match('/[\x00-\x20\x7f\\\\?#]/', $path) || preg_match('~(?:^|/)\.{1,2}(?:/|$)|%2[ef]|%5c~i', $path) || str_starts_with($path, '//') || str_contains($path, '//')) {
            return null;
        }
        $path = '/'.trim($path, '/');
        if (preg_match('~^/(?:admin|livewire|api|inquiries|up|storage|assets|build|vendor|app|bootstrap|config|database|resources|routes|tests|wp-admin|wp-content)(?:/|$)|^/\.|/\.[^/]|^/(?:wp-login\.php|robots\.txt|sitemap\.xml|sitemaps)(?:/|$)~i', $path)) {
            return null;
        }
        if (isset($parts['query'])) {
            if ($path !== '/' || ! preg_match('/\A(p|page_id)=([1-9][0-9]{0,9})\z/D', $parts['query'], $match)) {
                return null;
            }

            return '/?'.$match[1].'='.$match[2];
        }

        return $path;
    }

    public function requestPath(Request $request): ?string
    {
        $path = $request->getPathInfo();
        if ($path === '/') {
            foreach (['p', 'page_id'] as $key) {
                if ($request->query->has($key)) {
                    $value = $request->query($key);

                    return is_scalar($value) ? $this->path('/?'.$key.'='.$value) : null;
                }
            }
        }

        return $this->path($path);
    }

    public function isPublicDestination(string $path): bool
    {
        if ($this->path($path) !== $path || str_contains($path, '?')) {
            return false;
        }
        if (in_array($path, ['/', '/about', '/services', '/projects', '/blog', '/contact'], true)) {
            return true;
        }
        if (! preg_match('~\A/(blog|projects|services)/([^/]+)\z~u', $path, $match)) {
            return false;
        }
        $model = match ($match[1]) {
            'blog' => Post::class,'projects' => Project::class,default => Service::class
        };

        return $model::query()->published()->where('slug', $match[2])->exists();
    }

    /** @param list<string|null> $seen */
    public function destination(string $path, array $seen = [], ?int $ignoreId = null): ?string
    {
        for ($step = 0; $step < 10; $step++) {
            $path = $this->path($path);
            if (! $path || in_array($path, $seen, true)) {
                return null;
            }
            $seen[] = $path;
            $rule = SeoRedirect::query()->where('source_path', $path)->where('enabled', true)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->first();
            if (! $rule) {
                return $this->isPublicDestination($path) ? $path : null;
            }
            $path = $rule->destination_path;
        }

        return null;
    }

    public function validateRule(SeoRedirect $redirect): void
    {
        $source = $this->path($redirect->source_path);
        $target = $this->path($redirect->destination_path);
        $errors = [];
        if (! $source || $source === '/') {
            $errors['source_path'] = 'Use a public path or an exact /?p=123 or /?page_id=123 source. Private, asset and system paths are excluded.';
        }
        if (! in_array($redirect->status_code, [301, 302], true)) {
            $errors['status_code'] = 'Choose 301 or 302.';
        }
        $checkTarget = $redirect->enabled || ! $redirect->exists || $redirect->isDirty('destination_path');
        if (! $target || $target === $source || ($checkTarget && ! $this->destination($target, [$source], $redirect->id))) {
            $errors['destination_path'] = 'Choose an existing published page on this website. Self redirects, loops, private targets and missing destinations are not allowed.';
        }
        if ($source && SeoRedirect::query()->where('source_path', $source)->when($redirect->exists, fn ($query) => $query->whereKeyNot($redirect->id))->exists()) {
            $errors['source_path'] = 'A redirect already exists for this source.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $redirect->source_path = $source;
        $redirect->destination_path = $target;
    }

    public function suggest(Model $record): void
    {
        if (! $record->wasChanged('slug') || ! $record->is_published || ($record->published_at && $record->published_at->isFuture())) {
            return;
        }
        $section = match (true) {
            $record instanceof Post => 'blog',$record instanceof Project => 'projects',default => 'services'
        };
        $old = '/'.$section.'/'.$record->getRawOriginal('slug');
        $new = '/'.$section.'/'.$record->slug;
        if ($this->path($old) && $this->isPublicDestination($new)) {
            try {
                SeoRedirect::query()->firstOrCreate(['source_path' => $old], ['destination_path' => $new, 'status_code' => 301, 'enabled' => false, 'notes' => 'Suggested after a published slug changed. Review and enable this redirect.']);
            } catch (ValidationException) {
                return;
            }
        }
    }

    public function record404(Request $request): void
    {
        $path = $this->path($request->getPathInfo());
        if (! $path || $path === '/' || preg_match('/\.(?:css|js|map|png|jpe?g|gif|webp|ico|svg|woff2?|php|phar)$/i', $path) || ! config('seo.log_not_found')) {
            return;
        }
        $hash = hash('sha256', $path);
        if (RateLimiter::tooManyAttempts('seo-404-global', 300) || RateLimiter::tooManyAttempts('seo-404:'.$hash, 30)) {
            return;
        }
        RateLimiter::hit('seo-404-global', 60);
        RateLimiter::hit('seo-404:'.$hash, 60);
        try {
            $existing = DB::table('seo_not_found')->where('path_hash', $hash)->first();
            if ($existing) {
                DB::table('seo_not_found')->where('id', $existing->id)->update(['hits' => DB::raw('hits + 1'), 'last_seen_at' => now()]);
            } elseif (DB::table('seo_not_found')->count() < 5000) {
                DB::table('seo_not_found')->insertOrIgnore(['path_hash' => $hash, 'path' => $path, 'hits' => 1, 'first_seen_at' => now(), 'last_seen_at' => now()]);
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
