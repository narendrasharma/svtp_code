<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EditorUploadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'upload' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],
        ]);

        $path = $request
            ->file('upload')
            ->store('package-content', 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
