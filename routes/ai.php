<?php

use App\Http\Controllers\PublishingImageController;
use App\Http\Middleware\AuthenticateBlogPublisher;
use App\Mcp\Servers\BlogServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware([AuthenticateBlogPublisher::class, 'throttle:60,1'])->group(function (): void {
    Mcp::web('/mcp/blog', BlogServer::class)->name('mcp.blog');
    Route::post('/api/integrations/blog/images', PublishingImageController::class)->name('mcp.blog.images');
});
