<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Services\GscErrorFormatter;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscSitemapService;

class GscSitemapController extends Controller
{
    use RespondsWithGscJson;

    public function index(GscSite $site)
    {
        return $this->ok($site->sitemaps()->with('contents')->latest('id')->paginate((int) request('per_page', 50)));
    }

    public function submit(Request $request, GscSite $site, GscSitemapService $sitemaps)
    {
        $validated = $request->validate(['sitemapUrl' => ['required', 'url']]);
        $this->assertApproved($site);

        try {
            $sitemaps->submitSitemap($site->loadMissing('credential'), $validated['sitemapUrl']);
        } catch (\Throwable $exception) {
            return $this->fail('Sitemap submission failed.', app(GscErrorFormatter::class)->format($exception));
        }

        return $this->ok(null, 'Sitemap submitted.');
    }

    public function delete(Request $request, GscSite $site, GscSitemapService $sitemaps)
    {
        $validated = $request->validate(['sitemapUrl' => ['required', 'url']]);
        $this->assertApproved($site);

        try {
            $sitemaps->deleteSitemap($site->loadMissing('credential'), $validated['sitemapUrl']);
        } catch (\Throwable $exception) {
            return $this->fail('Sitemap deletion failed.', app(GscErrorFormatter::class)->format($exception));
        }

        return $this->ok(null, 'Sitemap deleted.');
    }

    private function assertApproved(GscSite $site): void
    {
        abort_unless($site->status === 'approved' && $site->active, 422, 'Site must be approved and active.');
    }
}
