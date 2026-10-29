<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Http\Request;

class AnalyticsTracking
{
    public const SESSION_KEY = 'lead_attribution';

    public const MEASUREMENT_ID_PATTERN = '/\AG-[A-Z0-9]{4,20}\z/D';

    /** @param array<string, mixed> $seo */
    public function shouldRender(?SiteSetting $site, array $seo = []): bool
    {
        return $site !== null
            && config('portfolio.indexable')
            && empty($seo['is_preview'])
            && $site->analytics_enabled
            && is_string($site->ga4_measurement_id)
            && preg_match(self::MEASUREMENT_ID_PATTERN, $site->ga4_measurement_id) === 1;
    }

    /**
     * Remember the first marketing touch of a visit so a later inquiry can be attributed to it.
     */
    public function remember(Request $request): void
    {
        if (! $request->isMethod('GET') || $request->hasSession() === false || $request->session()->has(self::SESSION_KEY)) {
            return;
        }

        $touch = [
            'landing_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
            'referrer_host' => $this->externalReferrerHost($request),
            'utm_source' => $this->utm($request, 'utm_source'),
            'utm_medium' => $this->utm($request, 'utm_medium'),
            'utm_campaign' => $this->utm($request, 'utm_campaign'),
        ];

        if ($touch['referrer_host'] === null && $touch['utm_source'] === null) {
            return;
        }

        $request->session()->put(self::SESSION_KEY, $touch);
    }

    /**
     * @return array{landing_path: ?string, referrer_host: ?string, utm_source: ?string, utm_medium: ?string, utm_campaign: ?string}
     */
    public function attribution(Request $request): array
    {
        $touch = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        return [
            'landing_path' => is_array($touch) ? ($touch['landing_path'] ?? null) : null,
            'referrer_host' => is_array($touch) ? ($touch['referrer_host'] ?? null) : null,
            'utm_source' => is_array($touch) ? ($touch['utm_source'] ?? null) : null,
            'utm_medium' => is_array($touch) ? ($touch['utm_medium'] ?? null) : null,
            'utm_campaign' => is_array($touch) ? ($touch['utm_campaign'] ?? null) : null,
        ];
    }

    private function utm(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if (! is_string($value)) {
            return null;
        }
        $value = mb_substr(trim(preg_replace('/[^A-Za-z0-9 _.+\-]/', '', $value) ?? ''), 0, 100);

        return $value === '' ? null : $value;
    }

    private function externalReferrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('referer');
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;
        $ownHosts = [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)];
        if (! is_string($host) || $host === '' || in_array(strtolower($host), array_map(fn (mixed $own): string => strtolower((string) $own), $ownHosts), true)) {
            return null;
        }

        return mb_substr(strtolower($host), 0, 255);
    }
}
