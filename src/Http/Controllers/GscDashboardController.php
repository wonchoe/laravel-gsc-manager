<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscCredential;
use Wonchoe\GscManager\Models\GscSearchAnalytic;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Models\GscSyncLog;

class GscDashboardController extends Controller
{
    use RespondsWithGscJson;

    public function dashboard()
    {
        $analytics = GscSearchAnalytic::query();

        return $this->ok([
            'total_credentials' => GscCredential::query()->count(),
            'total_sites' => GscSite::query()->count(),
            'approved_sites' => GscSite::query()->where('status', 'approved')->count(),
            'missing_access_sites' => GscSite::query()->where('status', 'missing_access')->count(),
            'last_sync_logs' => GscSyncLog::query()
                ->with(['credential:id,file_name,client_email,project_id', 'site:id,site_url'])
                ->latest('id')
                ->limit(10)
                ->get(),
            'total_by_type' => (clone $analytics)
                ->selectRaw('type, SUM(clicks) as clicks, SUM(impressions) as impressions, AVG(ctr) as ctr, AVG(position) as position')
                ->groupBy('type')
                ->get(),
            'top_pages' => GscSearchAnalytic::query()
                ->whereNotNull('page')
                ->selectRaw('page, SUM(clicks) as clicks, SUM(impressions) as impressions')
                ->groupBy('page')
                ->orderByDesc('clicks')
                ->limit(10)
                ->get(),
            'top_queries' => GscSearchAnalytic::query()
                ->whereNotNull('query')
                ->selectRaw('query, SUM(clicks) as clicks, SUM(impressions) as impressions')
                ->groupBy('query')
                ->orderByDesc('clicks')
                ->limit(10)
                ->get(),
            'image_search_summary' => $this->typeSummary('image'),
            'video_search_summary' => $this->typeSummary('video'),
        ]);
    }

    /**
     * @return array<string, int|float>
     */
    private function typeSummary(string $type): array
    {
        $query = GscSearchAnalytic::query()->where('type', $type);

        return [
            'clicks' => (int) (clone $query)->sum('clicks'),
            'impressions' => (int) (clone $query)->sum('impressions'),
            'avg_ctr' => round((float) (clone $query)->avg('ctr'), 8),
            'avg_position' => round((float) (clone $query)->avg('position'), 4),
        ];
    }
}
