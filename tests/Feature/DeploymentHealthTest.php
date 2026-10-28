<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Exceptions;
use InvalidArgumentException;
use Tests\TestCase;

class DeploymentHealthTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);

        parent::tearDown();
    }

    public function test_health_check_reports_a_working_database_and_is_not_indexable(): void
    {
        $this->getJson('/up')->assertOk()->assertExactJson(['status' => 'up'])->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_health_check_reports_database_failure_without_exposing_details(): void
    {
        config(['app.debug' => false, 'database.default' => 'missing-production-connection']);
        Exceptions::fake();
        $this->getJson('/up')->assertStatus(500)->assertExactJson(['status' => 'down'])->assertDontSee('missing-production-connection');
        Exceptions::assertReported(InvalidArgumentException::class);
    }

    public function test_production_rejects_requests_for_an_unconfigured_hostname(): void
    {
        config(['app.url' => 'https://portfolio.company', 'app.debug' => false]);
        $this->app->instance('env', 'production');
        $this->get('https://attacker.invalid/contact')->assertStatus(400);
    }

    public function test_production_allows_the_configured_hostname(): void
    {
        config(['app.url' => 'https://portfolio.company']);
        $this->app->instance('env', 'production');
        $this->get('https://portfolio.company/contact')->assertOk()->assertSee('href="https://portfolio.company/contact"', false);
    }
}
