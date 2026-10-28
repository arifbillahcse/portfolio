<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\InquiryMailSettings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('portfolio:check-production {--before-migrate : Check configuration and database connectivity before migrations}')]
#[Description('Check deployment configuration without printing credentials or changing data')]
class CheckProduction extends Command
{
    private int $failures = 0;

    public function handle(): int
    {
        $this->failures = 0;
        $this->check('Environment', app()->environment('production'), 'Set APP_ENV=production.');
        $this->check('Debug mode', ! config('app.debug'), 'Set APP_DEBUG=false.');
        $this->check('Application URL', $this->publicUrl((string) config('app.url')), 'Set APP_URL to your real HTTPS domain, without a path or query.');
        $key = (string) config('app.key', '');
        $decodedKey = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $this->check('Encryption key', is_string($decodedKey) && Encrypter::supported($decodedKey, config('app.cipher')), 'Set a valid APP_KEY. Keep an existing production key unchanged.');
        $this->check('Secure sessions', config('session.secure') === true && config('session.encrypt') === true
            && config('session.http_only') === true && in_array(config('session.same_site'), ['lax', 'strict'], true), 'Enable secure, encrypted, HTTP-only session cookies with SameSite=lax or strict.');
        $this->check('Persistent sessions', config('session.driver') === 'database', 'Use SESSION_DRIVER=database.');
        $this->check('Persistent cache', config('cache.default') === 'database', 'Use CACHE_STORE=database.');
        $this->check('Background queue', config('queue.default') === 'database', 'Use QUEUE_CONNECTION=database and configure the cPanel mail Cron Job.');

        $mailer = config('mail.default');
        try {
            if (! $this->option('before-migrate') && Schema::hasColumn('site_settings', 'smtp_enabled')) {
                $mailer = app(InquiryMailSettings::class)->mailer() ?? 'not_configured';
            }
        } catch (Throwable) {
            $mailer = 'not_configured';
        }
        $this->check('Mail transport', in_array($mailer, ['smtp', 'portfolio_smtp'], true), 'Configure SMTP in Site Settings or set MAIL_MAILER=smtp.');
        if (in_array($mailer, ['smtp', 'portfolio_smtp'], true)) {
            $host = (string) config('mail.mailers.'.$mailer.'.host', '');
            $this->check('SMTP endpoint', $this->realHost($host) && filter_var(config('mail.mailers.'.$mailer.'.port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) !== false,
                'Set the real SMTP host and port.');
        }
        $email = (string) config($mailer === 'portfolio_smtp' ? 'mail.mailers.portfolio_smtp.from.address' : 'mail.from.address', '');
        $this->check('Mail sender', filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $this->realHost(substr(strrchr($email, '@') ?: '', 1)), 'Set the sender in Site Settings or MAIL_FROM_ADDRESS to an address authorized by your mail provider.');

        foreach (['ctype', 'curl', 'dom', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo', 'session', 'tokenizer', 'xml', 'zip'] as $extension) {
            $this->check('PHP '.$extension, extension_loaded($extension), 'Enable the '.$extension.' PHP extension for both the website and CLI.');
        }
        $this->check('PHP version', PHP_VERSION_ID >= 80300, 'Use PHP 8.3 or later for both the domain and Cron Jobs.');
        $this->check('Writable runtime folders', is_writable(storage_path()) && is_writable(storage_path('framework')) && is_writable(storage_path('logs')) && is_writable(base_path('bootstrap/cache')),
            'Grant your cPanel user write access to storage and bootstrap/cache.');
        $this->check('Public assets', is_file(public_path('assets/css/styles.css')) && is_file(public_path('assets/js/script.js'))
            && is_file(public_path('assets/images/arif-formal.png')) && is_file(public_path('css/filament/filament/app.css')),
            'Upload the public assets and run php artisan filament:assets.');
        $this->check('Upload link', is_dir(public_path('storage')) && realpath(public_path('storage')) === realpath(storage_path('app/public')),
            'Run php artisan storage:link; public/storage must resolve to storage/app/public.');
        $this->check('Public file boundary', ! is_file(public_path('.env')) && ! is_file(public_path('composer.json')) && ! is_file(public_path('artisan')),
            'Point the domain document root to Laravel public/, keeping application files outside it.');
        $this->check('Laravel front controller', is_file(public_path('index.php')) && ! is_file(public_path('index.html')),
            'Install the Laravel index.php in the document root and relocate any old static index.html.');

        try {
            $connection = DB::connection();
            $connection->select('select 1');
            $database = $connection->getDatabaseName();
            $this->check('Durable database', $database !== ':memory:' && $database !== '', 'Use a persistent MySQL database, or a durable database file.');
            $this->check('Database connection', true, '');
            if (! $this->option('before-migrate')) {
                $migrator = app('migrator');
                $files = $migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()));
                $repository = $migrator->getRepository();
                $migrated = $repository->repositoryExists() && array_diff(array_keys($files), $repository->getRan()) === [];
                $this->check('Database migrations', $migrated, 'Run php artisan migrate --force.');
                if ($migrated) {
                    $tables = collect(['sessions', 'cache', 'cache_locks', 'jobs', 'failed_jobs'])->every(fn (string $table): bool => Schema::hasTable($table));
                    $this->check('Runtime tables', $tables, 'Create the database session, cache, and queue tables.');
                    $this->check('Admin account', User::query()->where('is_admin', true)->exists(), 'Create your administrator with php artisan portfolio:admin.');
                    $settings = SiteSetting::query()->where('key', 'main')->first();
                    $this->check('Profile and recipient', Profile::query()->where('key', 'main')->exists()
                        && $settings && app(InquiryMailSettings::class)->enabled() && app(InquiryMailSettings::class)->recipient(),
                        'Initialize the portfolio content and enable lead notifications with a recipient email in Site Settings.');
                }
            }
        } catch (Throwable) {
            $this->check('Database checks', false, 'Database checks could not complete. Check connectivity, credentials, and migrations.');
        }

        if (! config('portfolio.indexable')) {
            $this->warn('Search indexing is OFF. Set SITE_INDEXABLE=true only when the live website is ready.');
        }
        $this->line('This checks configuration, not DNS, HTTPS certificates, Cron execution, backups, or actual email delivery.');
        if ($this->failures > 0) {
            $this->error('Deployment checks failed. Resolve the FAIL items before launch.');

            return self::FAILURE;
        }
        $this->info('Deployment configuration checks passed.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $passed, string $resolution): void
    {
        $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
        if (! $passed) {
            $this->failures++;
            $this->line('  '.$resolution);
        }
    }

    private function publicUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! $parts || ! filter_var($url, FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return false;
        }

        return $this->realHost($parts['host'] ?? '');
    }

    private function realHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));
        if ($host === '' || ! str_contains($host, '.') || in_array($host, ['localhost', 'example.com', 'example.net', 'example.org'], true)
            || preg_match('/\.(localhost|test|example|invalid|example\.com|example\.net|example\.org)$/', $host)) {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }

        return filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}
