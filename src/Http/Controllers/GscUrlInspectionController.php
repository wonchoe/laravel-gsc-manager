<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscSite;
use Wonchoe\GscManager\Services\GscUrlInspectionService;

class GscUrlInspectionController extends Controller
{
    use RespondsWithGscJson;

    public function inspect(Request $request, GscSite $site, GscUrlInspectionService $inspection)
    {
        $validated = $request->validate([
            'inspectionUrl' => ['required', 'url'],
            'languageCode' => ['nullable', 'string', 'max:20'],
        ]);

        abort_unless($site->status === 'approved' && $site->active, 422, 'Site must be approved and active.');

        $result = $inspection->inspect($site->loadMissing('credential'), $validated['inspectionUrl'], $validated['languageCode'] ?? null);

        if ($result->last_error) {
            return $this->ok($result, 'URL inspection failed.', $result->last_error);
        }

        return $this->ok($result, 'URL inspected.');
    }

    public function index(GscSite $site)
    {
        return $this->ok($site->urlInspections()->latest('inspected_at')->paginate((int) request('per_page', 50)));
    }
}
