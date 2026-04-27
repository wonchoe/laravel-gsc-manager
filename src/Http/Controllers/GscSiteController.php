<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscSite;

class GscSiteController extends Controller
{
    use RespondsWithGscJson;

    public function index()
    {
        $sites = GscSite::query()
            ->with(['credential:id,file_name,client_email,project_id,active'])
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate((int) request('per_page', 25));

        return $this->ok($sites);
    }

    public function show(GscSite $site)
    {
        return $this->ok($site->load(['credential:id,file_name,client_email,project_id,active']));
    }

    public function approve(GscSite $site)
    {
        $site->forceFill(['status' => 'approved', 'active' => true, 'last_error' => null])->save();

        return $this->ok($site->fresh(), 'Site approved.');
    }

    public function disable(GscSite $site)
    {
        $site->forceFill(['status' => 'disabled', 'active' => false])->save();

        return $this->ok($site->fresh(), 'Site disabled.');
    }
}
