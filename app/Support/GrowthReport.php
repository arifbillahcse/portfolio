<?php

namespace App\Support;

use App\Models\Inquiry;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Throwable;

class GrowthReport
{
    public const REPORT_PATH = 'growth/report.json';

    public function __construct(private GoogleReporting $google, private SeoContentAudit $audit) {}

    /** @return array<string, mixed> */
    public function build(int $days = 28): array
    {
        $days = max(7, min(90, $days));
        $site = SiteSetting::query()->where('key', 'main')->first();
        $errors = [];
        $search = null;
        $analytics = null;

        if ($this->google->searchConsoleConfigured($site)) {
            try {
                $search = $this->google->searchConsole($site, $days);
            } catch (Throwable $exception) {
                $errors['search_console'] = $exception->getMessage();
            }
        }
        if ($this->google->analyticsConfigured($site)) {
            try {
                $analytics = $this->google->analytics($site, $days);
            } catch (Throwable $exception) {
                $errors['analytics'] = $exception->getMessage();
            }
        }

        $leads = $this->leads($days);
        $sessions = $analytics['sessions'] ?? 0;
        $audit = $this->audit->read();

        return [
            'version' => 1,
            'generated_at' => now()->toIso8601String(),
            'days' => $days,
            'leads' => $leads,
            'conversion_rate' => $sessions > 0 ? round($leads['total'] / $sessions * 100, 2) : null,
            'search_console' => $search,
            'analytics' => $analytics,
            'errors' => $errors,
            'connections' => [
                'tracking_enabled' => (bool) $site?->analytics_enabled && filled($site?->ga4_measurement_id),
                'search_console' => $this->google->searchConsoleConfigured($site),
                'analytics' => $this->google->analyticsConfigured($site),
            ],
            'content' => $audit ? ['generated_at' => $audit['generated_at'], 'pages' => $audit['totals']['pages'], 'with_reviews' => $audit['totals']['with_reviews']] : null,
            'plan' => [
                'next_review_on' => $site?->next_content_review_on?->toDateString(),
                'overdue' => $site?->next_content_review_on?->isPast() && ! $site->next_content_review_on->isToday(),
                'notes' => $site?->content_plan,
            ],
        ];
    }

    /**
     * @return array{total: int, attributed: int, sources: list<array{name: string, count: int}>, forms: array<string, int>, statuses: array<string, int>}
     */
    private function leads(int $days): array
    {
        $inquiries = Inquiry::query()->where('created_at', '>=', now()->subDays($days))
            ->get(['source', 'status', 'utm_source', 'referrer_host']);
        $sources = $inquiries->countBy(fn (Inquiry $inquiry): string => $inquiry->utm_source ?: $inquiry->referrer_host ?: 'Direct / unknown')
            ->sortDesc()->map(fn (int $count, string $name): array => ['name' => $name, 'count' => $count])->values()->all();

        return [
            'total' => $inquiries->count(),
            'attributed' => $inquiries->filter(fn (Inquiry $inquiry): bool => filled($inquiry->utm_source) || filled($inquiry->referrer_host))->count(),
            'sources' => $sources,
            'forms' => $inquiries->countBy('source')->all(),
            'statuses' => $inquiries->countBy('status')->all(),
        ];
    }

    /** @param array<string, mixed> $report */
    public function save(array $report): string
    {
        $path = Storage::disk('local')->path(self::REPORT_PATH);
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 0600);

        return $path;
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::REPORT_PATH) || $disk->size(self::REPORT_PATH) > 5000000) {
            return null;
        }
        try {
            $report = json_decode($disk->get(self::REPORT_PATH), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($report) && ($report['version'] ?? null) === 1 && isset($report['generated_at'], $report['leads']['total']) ? $report : null;
    }
}
