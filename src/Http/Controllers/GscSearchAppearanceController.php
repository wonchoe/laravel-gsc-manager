<?php

namespace Wonchoe\GscManager\Http\Controllers;

use Illuminate\Routing\Controller;
use Wonchoe\GscManager\Http\Controllers\Concerns\RespondsWithGscJson;
use Wonchoe\GscManager\Models\GscSite;

class GscSearchAppearanceController extends Controller
{
    use RespondsWithGscJson;

    public function index(GscSite $site)
    {
        return $this->ok($site->searchAppearances()->orderBy('type')->orderBy('search_appearance')->get());
    }
}
