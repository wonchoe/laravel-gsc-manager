<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscCredential;

class GscCredentialController extends Controller
{
    use RespondsWithGscJson;

    public function index()
    {
        $credentials = GscCredential::query()
            ->select(['id', 'file_name', 'client_email', 'project_id', 'active', 'last_discovered_at', 'last_synced_at', 'created_at', 'updated_at'])
            ->latest('id')
            ->paginate((int) request('per_page', 25));

        return $this->ok($credentials);
    }

    public function show(GscCredential $credential)
    {
        return $this->ok($credential->makeHidden(['file_path', 'last_error']));
    }
}
