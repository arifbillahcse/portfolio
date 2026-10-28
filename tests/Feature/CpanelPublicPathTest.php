<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CpanelPublicPathTest extends TestCase
{
    public static function cacheModes(): array
    {
        return ['uncached configuration' => [false], 'cached configuration' => [true]];
    }

    #[DataProvider('cacheModes')]
    public function test_cpanel_public_html_is_used_for_cli_upload_links(bool $cached): void
    {
        $disk = Storage::fake($cached ? 'cpanel-public-cached' : 'cpanel-public-direct');
        $disk->makeDirectory('public_html');
        $environment = [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
            'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
            'APP_CONFIG_CACHE' => substr(str_replace('\\', '/', $disk->path('config.php')), strlen(str_replace('\\', '/', base_path())) + 1),
            'APP_PUBLIC_PATH' => $disk->path('public_html'),
            'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        ];
        if ($cached) {
            $cache = new Process([PHP_BINARY, base_path('artisan'), 'config:cache', '--no-interaction'], base_path(), $environment);
            $cache->mustRun();
        }
        $link = new Process([PHP_BINARY, base_path('artisan'), 'storage:link', '--no-interaction'], base_path(), $environment);
        $link->mustRun();

        $this->assertDirectoryExists($disk->path('public_html/storage'));
        $this->assertSame(realpath(storage_path('app/public')), realpath($disk->path('public_html/storage')));
    }
}
