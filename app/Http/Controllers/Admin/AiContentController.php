<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AiContentService;
use Illuminate\Http\Request;

class AiContentController extends Controller
{
    public function draftItinerary(Request $request, AiContentService $ai)
    {
        $request->validate(['notes' => ['required', 'string', 'max:2000']]);

        // Draft only — admin reviews and edits before saving to the package.
        return response()->json(['draft' => $ai->generateItineraryDraft($request->notes)]);
    }
}
