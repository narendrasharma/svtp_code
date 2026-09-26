<?php

namespace App\Http\Controllers\Admin;

use App\AI\Support\AIException;
use App\Http\Controllers\Controller;
use App\Services\AiContentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiContentController extends Controller
{
    public function draftItinerary(Request $request, AiContentService $ai): JsonResponse
    {
        $validated = $request->validate(['notes' => ['required', 'string', 'max:2000']]);

        try {
            return response()->json(['draft' => $ai->generateItineraryDraft($validated['notes'])]);
        } catch (AIException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }
    }
}
