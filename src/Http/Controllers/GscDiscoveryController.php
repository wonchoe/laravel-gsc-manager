<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Services\GscAccessCheckService;
use Wonchoe\GscManager\Services\GscCredentialScanner;
use Wonchoe\GscManager\Services\GscDiscoveryService;

class GscDiscoveryController extends Controller
{
    use RespondsWithGscJson;

    public function discover(Request $request, GscCredentialScanner $scanner, GscDiscoveryService $discovery)
    {
        $validated = $request->validate(['credential' => ['nullable', 'string']]);
        $fileName = $validated['credential'] ?? null;
        $scan = $scanner->scan($fileName);
        $credential = $fileName ? $scan['credentials']->first() : null;

        if ($fileName && ! $credential) {
            return $this->ok(['scan' => $scan, 'discovery' => ['credentials_processed' => 0, 'sites_created' => 0, 'sites_updated' => 0, 'errors' => $scan['errors']]], 'No valid credential found.');
        }

        return $this->ok(['scan' => $scan, 'discovery' => $discovery->discover($credential)], 'Discovery completed.');
    }

    public function accessCheck(Request $request, GscCredentialScanner $scanner, GscAccessCheckService $access)
    {
        $validated = $request->validate(['credential' => ['nullable', 'string']]);
        $fileName = $validated['credential'] ?? null;
        $scan = $scanner->scan($fileName);
        $credential = $fileName ? $scan['credentials']->first() : null;

        if ($fileName && ! $credential) {
            return $this->ok(['credentials_processed' => 0, 'missing_access' => 0, 'discovered' => 0, 'errors' => $scan['errors']], 'No valid credential found.');
        }

        return $this->ok($access->check($credential), 'Access check completed.');
    }
}
