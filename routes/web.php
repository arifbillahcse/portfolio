<?php

use App\Http\Controllers\InquiryController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\WordPressImportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PortfolioController::class, 'home'])->name('home');
Route::permanentRedirect('/index.html', '/');

foreach (['about', 'services', 'projects', 'blog', 'contact'] as $page) {
    Route::get('/'.$page, [PortfolioController::class, $page])->name($page);
    Route::permanentRedirect('/'.$page.'.html', '/'.$page);
}

Route::get('/projects/{slug}', [PortfolioController::class, 'project'])->name('projects.show');
Route::get('/admin/projects/{project}/preview', [PortfolioController::class, 'previewProject'])
    ->middleware(['auth', 'auth.session'])->name('projects.preview');
Route::get('/blog/{slug}', [PortfolioController::class, 'post'])->name('blog.show');
Route::get('/services/{slug}', [PortfolioController::class, 'service'])->name('services.show');
Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:inquiries')->name('inquiries.store');
Route::post('/admin/wordpress-import/upload', [WordPressImportController::class, 'store'])
    ->middleware(['auth', 'throttle:10,1'])->name('wordpress.import.upload');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'index'])->name('sitemap');
Route::get('/sitemaps/pages.xml', [SeoController::class, 'pages'])->name('sitemap.pages');
Route::get('/sitemaps/{section}/{page}.xml', [SeoController::class, 'section'])
    ->whereIn('section', ['projects', 'blog', 'services'])->where('page', '[1-9][0-9]*')->name('sitemap.section');
