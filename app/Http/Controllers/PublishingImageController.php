<?php

namespace App\Http\Controllers;

use App\Support\PublishingMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublishingImageController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, PublishingMedia $media): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096']]);

        return response()->json($media->store($request->file('image')->get()), 201);
    }
}
