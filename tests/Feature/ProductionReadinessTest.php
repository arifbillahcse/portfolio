<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    private function prepareProduction(bool $migrate = true): void
    {
        $disk = Storage::fake('production-readiness');
        $disk->put('production.sqlite', '');
        config([
            'app.env' => 'production', 'app.debug' => false, 'app.url' => 'https://portfolio.company',
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'session.secure' => true, 'session.encrypt' => true, 'session.http_only' => true, 'session.same_site' => 'lax',
            'session.driver' => 'database', 'cache.default' => 'database', 'queue.default' => 'database',
            'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.portfolio.company', 'mail.mailers.smtp.port' => 587,
            'mail.from.address' => 'hello@portfolio.company', 'portfolio.indexable' => true,
            'database.default' => 'deployment-test',
            'database.connections.deployment-test' => array_replace(config('database.connections.sqlite'), ['database' => $disk->path('production.sqlite')]),
        ]);
        $this->app->instance('env', 'production');
        DB::purge('deployment-test');
        if ($migrate) {
            $this->artisan('migrate', ['--database' => 'deployment-test', '--force' => true])->assertSuccessful();
            Profile::factory()->create();
            SiteSetting::factory()->create(['contact_email' => 'hello@portfolio.company']);
            User::factory()->create(['is_admin' => true]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('deployment-test');

        parent::tearDown();
    }

    public function test_valid_production_configuration_passes_without_printing_credentials(): void
    {
        $this->prepareProduction();
        config(['mail.mailers.smtp.password' => 'secret-smtp-password', 'database.connections.deployment-test.password' => 'secret-database-password']);
        $this->artisan('portfolio:check-production')->expectsOutput('Deployment configuration checks passed.')
            ->doesntExpectOutputToContain('secret-smtp-password')->doesntExpectOutputToContain('secret-database-password')
            ->doesntExpectOutputToContain(config('app.key'))->assertSuccessful();
    }

    public function test_before_migrate_checks_work_on_a_new_database_but_final_checks_require_migrations(): void
    {
        $this->prepareProduction(false);
        $this->artisan('portfolio:check-production', ['--before-migrate' => true])->assertSuccessful();
        $this->artisan('portfolio:check-production')->expectsOutput('FAIL Database migrations')->assertFailed();
    }

    public function test_admin_smtp_settings_are_recognized_without_environment_mail_configuration(): void
    {
        $this->prepareProduction();
        config(['mail.default' => 'log', 'mail.from.address' => 'hello@example.com']);
        SiteSetting::query()->where('key', 'main')->firstOrFail()->update([
            'smtp_enabled' => true, 'smtp_host' => 'mail.portfolio.company', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
            'smtp_username' => 'hello@portfolio.company', 'smtp_password' => 'private-smtp-password', 'smtp_from_address' => 'hello@portfolio.company',
            'lead_notifications_enabled' => true, 'lead_notification_email' => 'leads@portfolio.company',
        ]);

        $this->artisan('portfolio:check-production')->expectsOutput('PASS Mail transport')->expectsOutput('PASS Mail sender')
            ->expectsOutput('PASS Profile and recipient')->doesntExpectOutputToContain('private-smtp-password')->assertSuccessful();
    }

    public static function unsafeConfiguration(): array
    {
        return [
            'debug on' => ['app.debug', true, 'Debug mode'],
            'http URL' => ['app.url', 'http://portfolio.company', 'Application URL'],
            'placeholder domain' => ['app.url', 'https://your-domain.example', 'Application URL'],
            'private IP' => ['app.url', 'https://192.168.1.20', 'Application URL'],
            'URL credentials' => ['app.url', 'https://user:password@portfolio.company', 'Application URL'],
            'URL query' => ['app.url', 'https://portfolio.company?tracking=x', 'Application URL'],
            'weak key' => ['app.key', 'not-a-valid-encryption-key', 'Encryption key'],
            'unencrypted session' => ['session.encrypt', false, 'Secure sessions'],
            'insecure cookie' => ['session.secure', false, 'Secure sessions'],
            'synchronous queue' => ['queue.default', 'sync', 'Background queue'],
            'log mail' => ['mail.default', 'log', 'Mail transport'],
            'placeholder sender' => ['mail.from.address', 'hello@example.com', 'Mail sender'],
            'placeholder SMTP' => ['mail.mailers.smtp.host', 'mail.example.com', 'SMTP endpoint'],
            'bad SMTP port' => ['mail.mailers.smtp.port', 0, 'SMTP endpoint'],
        ];
    }

    #[DataProvider('unsafeConfiguration')]
    public function test_unsafe_configuration_is_rejected(string $key, mixed $value, string $label): void
    {
        $this->prepareProduction();
        config([$key => $value]);
        $this->artisan('portfolio:check-production')->expectsOutput('FAIL '.$label)->assertFailed();
    }

    public function test_final_checks_require_an_admin_and_an_actual_recipient(): void
    {
        $this->prepareProduction();
        User::query()->delete();
        SiteSetting::query()->delete();
        $this->artisan('portfolio:check-production')->expectsOutput('FAIL Admin account')->expectsOutput('FAIL Profile and recipient')->assertFailed();
    }

    public function test_staging_configuration_warns_that_indexing_is_disabled(): void
    {
        $this->prepareProduction();
        config(['portfolio.indexable' => false]);
        $this->artisan('portfolio:check-production')->expectsOutputToContain('Search indexing is OFF')->assertSuccessful();
    }

    public function test_database_failure_is_reported_without_exposing_connection_details(): void
    {
        $this->prepareProduction(false);
        DB::purge('deployment-test');
        config(['database.connections.deployment-test.database' => '/missing/secret-database-path.sqlite']);
        $this->artisan('portfolio:check-production')->expectsOutput('FAIL Database checks')->doesntExpectOutputToContain('secret-database-path')->assertFailed();
    }

    public function test_a_temporary_in_memory_database_cannot_pass_production_checks(): void
    {
        $this->prepareProduction(false);
        DB::purge('deployment-test');
        config(['database.connections.deployment-test.database' => ':memory:']);
        $this->artisan('portfolio:check-production', ['--before-migrate' => true])->expectsOutput('FAIL Durable database')->assertFailed();
    }
}
