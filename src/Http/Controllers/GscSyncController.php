<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscSearchAnalytic;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;
use Wonchoe\GscManager\Services\GscAnalyticsService;
use Wonchoe\GscManager\Services\GscSearchAppearanceService;
use Wonchoe\GscManager\Services\GscSitemapService;
use Wonchoe\GscManager\Support\GscDateRange;

class GscSyncController extends Controller
{
    use RespondsWithGscJson;

    public function syncAnalytics(Request $request, GscSite $site, GscAnalyticsService $analytics)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::in(['web', 'image', 'video', 'news', 'discover', 'googleNews'])],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*' => [Rule::in(['date', 'hour', 'query', 'page', 'country', 'device', 'searchAppearance'])],
            'filters' => ['nullable', 'array'],
            'filters.*.dimension' => [Rule::in(['country', 'device', 'page', 'query', 'searchAppearance'])],
            'filters.*.operator' => [Rule::in(['contains', 'equals', 'notContains', 'notEquals', 'includingRegex', 'excludingRegex'])],
            'filters.*.expression' => ['required_with:filters.*.dimension', 'string'],
            'data_state' => ['nullable', Rule::in(['final', 'all', 'hourly_all'])],
            'aggregation_type' => ['nullable', Rule::in(['auto', 'byPage', 'byProperty'])],
        ]);

        $this->assertApproved($site);
        config([
            'gsc-manager.analytics.data_state' => $validated['data_state'] ?? config('gsc-manager.analytics.data_state'),
            'gsc-manager.analytics.aggregation_type' => $validated['aggregation_type'] ?? config('gsc-manager.analytics.aggregation_type'),
        ]);

        $stats = $analytics->syncSite(
            $site->loadMissing('credential'),
            GscDateRange::fromOptions($validated),
            $validated['type'] ?? [],
            $validated['dimensions'] ?? [],
            $validated['filters'] ?? [],
        );

        return $this->ok($stats, 'Analytics sync completed.');
    }

    public function syncSitemaps(GscSite $site, GscSitemapService $sitemaps)
    {
        $this->assertApproved($site);

        return $this->ok($sitemaps->listSitemaps($site->loadMissing('credential')), 'Sitemap sync completed.');
    }

    public function discoverSearchAppearances(Request $request, GscSite $site, GscSearchAppearanceService $appearances)
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::in(['web', 'image', 'video', 'news', 'discover', 'googleNews'])],
        ]);

        $this->assertApproved($site);

        return $this->ok(
            $appearances->discover($site->loadMissing('credential'), GscDateRange::fromOptions(['days' => $validated['days'] ?? 30]), $validated['type'] ?? []),
            'Search appearances discovered.',
        );
    }

    public function analytics(Request $request, GscSite $site)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(['web', 'image', 'video', 'news', 'discover', 'googleNews'])],
            'country' => ['nullable', 'string'],
            'device' => ['nullable', Rule::in(['DESKTOP', 'MOBILE', 'TABLET'])],
            'page' => ['nullable', 'string'],
            'query' => ['nullable', 'string'],
            'search_appearance' => ['nullable', 'string'],
        ]);

        $rows = GscSearchAnalytic::query()
            ->where('gsc_site_id', $site->id)
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->where('date', '<=', $to))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($validated['country'] ?? null, fn ($query, $country) => $query->where('country', $country))
            ->when($validated['device'] ?? null, fn ($query, $device) => $query->where('device', $device))
            ->when($validated['page'] ?? null, fn ($query, $page) => $query->where('page', 'like', '%' . $page . '%'))
            ->when($validated['query'] ?? null, fn ($query, $term) => $query->where('query', 'like', '%' . $term . '%'))
            ->when($validated['search_appearance'] ?? null, fn ($query, $value) => $query->where('search_appearance', $value))
            ->latest('date')
            ->paginate((int) $request->integer('per_page', 50));

        return $this->ok($rows);
    }

    public function analyticsSummary(Request $request, GscSite $site)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'type' => ['nullable', Rule::in(['web', 'image', 'video', 'news', 'discover', 'googleNews'])],
        ]);

        $base = GscSearchAnalytic::query()
            ->where('gsc_site_id', $site->id)
            ->when($validated['from'] ?? null, fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, $to) => $query->where('date', '<=', $to))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type));

        return $this->ok([
            'clicks' => (int) (clone $base)->sum('clicks'),
            'impressions' => (int) (clone $base)->sum('impressions'),
            'avg_ctr' => round((float) (clone $base)->avg('ctr'), 8),
            'avg_position' => round((float) (clone $base)->avg('position'), 4),
            'by_type' => (clone $base)
                ->selectRaw('type, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
                ->groupBy('type')
                ->get(),
        ]);
    }

    public function syncLogs()
    {
        return $this->ok(GscSyncLog::query()->with(['credential:id,file_name,client_email,project_id', 'site:id,site_url'])->latest('id')->paginate((int) request('per_page', 50)));
    }

    private function assertApproved(GscSite $site): void
    {
        abort_unless($site->status === 'approved' && $site->active, 422, 'Site must be approved and active.');
    }
}
