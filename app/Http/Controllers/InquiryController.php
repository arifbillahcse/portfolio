<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInquiryRequest;
use App\Models\Inquiry;
use App\Support\InquiryNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class InquiryController extends Controller
{
    public function store(StoreInquiryRequest $request, InquiryNotifier $notifier): JsonResponse|RedirectResponse
    {
        $inquiry = Inquiry::query()->create($request->safe()->only(['name', 'email', 'phone', 'message', 'source']));
        $notifier->queue($inquiry);
        $message = 'Thanks! Your inquiry has been received. I’ll get back to you.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 201);
        }

        return redirect()->route('contact')->with('inquiry_success', $message);
    }
}
