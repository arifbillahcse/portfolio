<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleReporting
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPES = [
        'search_console' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'analytics' => 'https://www.googleapis.com/auth/analytics.readonly',
    ];

    public function hasCredentials(?SiteSetting $site): bool
    {
        return $this->credentials($site) !== null;
    }

    public function searchConsoleConfigured(?SiteSetting $site): bool
    {
        return $this->hasCredentials($site) && filled($site->search_console_property);
    }

    public function analyticsConfigured(?SiteSetting $site): bool
    {
        return $this->hasCredentials($site) && preg_match('/\A[0-9]{3,20}\z/D', (string) $site->ga4_property_id) === 1;
    }

    /**
     * @return array{clicks: int, impressions: int, ctr: float, position: float, queries: list<array<string, mixed>>, pages: list<array<string, mixed>>}
     */
    public function searchConsole(SiteSetting $site, int $days): array
    {
        $range = $this->range($days, 3);
        $totals = $this->searchConsoleQuery($site, $range, []);
        $row = $totals['rows'][0] ?? [];

        return [
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => round((float) ($row['ctr'] ?? 0) * 100, 2),
            'position' => round((float) ($row['position'] ?? 0), 1),
            'queries' => $this->searchConsoleRows($this->searchConsoleQuery($site, $range, ['query'], 10)),
            'pages' => $this->searchConsoleRows($this->searchConsoleQuery($site, $range, ['page'], 10)),
        ];
    }

    /**
     * @return array{sessions: int, users: int, engaged_sessions: int, lead_events: int, channels: list<array<string, mixed>>, pages: list<array<string, mixed>>}
     */
    public function analytics(SiteSetting $site, int $days): array
    {
        $property = 'properties/'.$site->ga4_property_id.':runReport';
        $dateRanges = [['startDate' => $days.'daysAgo', 'endDate' => 'yesterday']];
        $run = fn (array $body): array => $this->post($site, 'analytics', 'https://analyticsdata.googleapis.com/v1beta/'.$property, ['dateRanges' => $dateRanges] + $body);

        $totals = $run(['metrics' => [['name' => 'sessions'], ['name' => 'totalUsers'], ['name' => 'engagedSessions']]]);
        $leads = $run([
            'metrics' => [['name' => 'eventCount']],
            'dimensionFilter' => ['filter' => ['fieldName' => 'eventName', 'stringFilter' => ['matchType' => 'EXACT', 'value' => 'generate_lead']]],
        ]);
        $channels = $run(['dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']], 'limit' => 8,
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]]]);
        $pages = $run(['dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews']], 'limit' => 10,
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]]]);

        $metric = fn (array $report, int $index): int => (int) ($report['rows'][0]['metricValues'][$index]['value'] ?? 0);
        $rows = fn (array $report): array => collect($report['rows'] ?? [])->map(fn (array $row): array => [
            'name' => (string) ($row['dimensionValues'][0]['value'] ?? ''),
            'value' => (int) ($row['metricValues'][0]['value'] ?? 0),
        ])->values()->all();

        return [
            'sessions' => $metric($totals, 0),
            'users' => $metric($totals, 1),
            'engaged_sessions' => $metric($totals, 2),
            'lead_events' => $metric($leads, 0),
            'channels' => $rows($channels),
            'pages' => $rows($pages),
        ];
    }

    /**
     * @param  array{0: string, 1: string}  $range
     * @param  list<string>  $dimensions
     * @return array<string, mixed>
     */
    private function searchConsoleQuery(SiteSetting $site, array $range, array $dimensions, int $limit = 1): array
    {
        $body = ['startDate' => $range[0], 'endDate' => $range[1], 'rowLimit' => $limit];
        if ($dimensions !== []) {
            $body['dimensions'] = $dimensions;
        }

        return $this->post($site, 'search_console',
            'https://www.googleapis.com/webmasters/v3/sites/'.rawurlencode((string) $site->search_console_property).'/searchAnalytics/query', $body);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function searchConsoleRows(array $response): array
    {
        return collect($response['rows'] ?? [])->map(fn (array $row): array => [
            'name' => (string) ($row['keys'][0] ?? ''),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'position' => round((float) ($row['position'] ?? 0), 1),
        ])->values()->all();
    }

    /** @return array{0: string, 1: string} */
    private function range(int $days, int $lagDays): array
    {
        return [now()->subDays($days + $lagDays)->toDateString(), now()->subDays($lagDays)->toDateString()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(SiteSetting $site, string $scope, string $url, array $body): array
    {
        $response = $this->client($site, $scope)->post($url, $body);
        if ($response->failed()) {
            $message = $response->json('error.message');

            throw new RuntimeException('Google API request failed ('.$response->status().')'.(is_string($message) ? ': '.mb_substr($message, 0, 200) : '.'));
        }

        return (array) $response->json();
    }

    private function client(SiteSetting $site, string $scope): PendingRequest
    {
        return Http::withToken($this->accessToken($site, $scope))->acceptJson()->timeout(20)->retry(2, 500, throw: false);
    }

    private function accessToken(SiteSetting $site, string $scope): string
    {
        $credentials = $this->credentials($site) ?? throw new RuntimeException('Google service account is not configured.');

        return Cache::remember('google-reporting-token:'.$scope.':'.sha1($credentials['client_email']), 3000, function () use ($credentials, $scope): string {
            $now = time();
            $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $claims = $this->base64Url(json_encode([
                'iss' => $credentials['client_email'], 'scope' => self::SCOPES[$scope], 'aud' => self::TOKEN_URL,
                'iat' => $now, 'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $signature = '';
            if (! openssl_sign($header.'.'.$claims, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The Google service account private key could not be used.');
            }

            $response = Http::asForm()->acceptJson()->timeout(20)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $header.'.'.$claims.'.'.$this->base64Url($signature),
            ]);
            $token = $response->json('access_token');
            if ($response->failed() || ! is_string($token)) {
                throw new RuntimeException('Google rejected the service account credentials.');
            }

            return $token;
        });
    }

    /** @return array{client_email: string, private_key: string}|null */
    public function credentials(?SiteSetting $site): ?array
    {
        $json = $site?->google_service_account;
        $data = is_string($json) && $json !== '' ? json_decode($json, true) : null;
        if (! is_array($data) || ! is_string($data['client_email'] ?? null) || ! is_string($data['private_key'] ?? null)
            || ! str_contains($data['private_key'], 'PRIVATE KEY')) {
            return null;
        }

        return ['client_email' => $data['client_email'], 'private_key' => $data['private_key']];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
