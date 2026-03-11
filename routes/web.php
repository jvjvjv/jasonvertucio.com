<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\HomeController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\FacebookCallbackController;
use App\Http\Controllers\WordpressController;
use App\Http\Controllers\Auth\LoginMethodsController;
use App\Http\Controllers\ResumeController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ResumeShareCodeController;
use App\Http\Controllers\Admin\ResumeEditorController;
use App\Http\Controllers\Admin\AiToolsController;
use App\Http\Controllers\Admin\AiSystemController;
use App\Http\Controllers\Admin\CoverLetterController;
use App\Http\Controllers\Admin\TargetedResumeController;
use App\Http\Controllers\Admin\MailPreviewController;
use App\Http\Controllers\Admin\SiteSettingsController;
use App\Http\Middleware\ResumeShareCodeMiddleware;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Keystone/Fortify authentication routes
Route::get('/login', function () {
    return view('auth.login');
})->name('login')->middleware('guest');

Route::post('/login/check-email', [LoginMethodsController::class, 'check'])
    ->middleware(['web', 'guest'])
    ->name('login.check-email');

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/');
})->name('logout.get');

// Fortify auto-registers these routes:
// POST /login - Login handler
// POST /logout - Logout handler
// GET/POST /password/reset - Password reset
// POST /two-factor-challenge - 2FA challenge

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about/{any?}', function () {
    return redirect('/');
});

Route::view('paper', 'paper')->name('paper');

Route::group(['prefix' => 'blog'], function ($route) {
    $route->get('/', [BlogController::class, 'index'])->name('blog');

    $route->get('/topics', [BlogController::class, 'topics'])->name('topics');
    $route->get('/tags', [BlogController::class, 'tags'])->name('tags');

    $route->get('/topics/{slug}', [BlogController::class, 'topicList'])->name('topicList');
    $route->get('/tags/{slug}', [BlogController::class, 'tagList'])->name('tagList');

    $route->get("/{slug}", [BlogController::class, 'post'])->name('post');
});

Route::any('/mlopnadjs22tn', [FacebookCallbackController::class, 'index']);

// Admin routes - requires auth + manage-unauthenticated-viewers permission
Route::middleware(['auth', 'can:manage-unauthenticated-viewers'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');

        // Resume management hub
        Route::get('/resume', [AdminController::class, 'resumeHub'])->name('resume.index');

        // Share codes management
        Route::get('/resume/codes', [ResumeShareCodeController::class, 'index'])->name('resume.codes.index');
        Route::post('/resume/codes', [ResumeShareCodeController::class, 'store'])->name('resume.codes.store');
        Route::delete('/resume/codes/{code}', [ResumeShareCodeController::class, 'destroy'])->name('resume.codes.destroy');

        // Cover letter management
        Route::get('/cover-letters', [CoverLetterController::class, 'index'])->name('cover-letters.index');
        Route::get('/cover-letters/new', [CoverLetterController::class, 'create'])->name('cover-letters.create');
        Route::post('/cover-letters', [CoverLetterController::class, 'store'])->name('cover-letters.store');
        Route::get('/cover-letters/{coverLetter}', [CoverLetterController::class, 'edit'])->name('cover-letters.edit');
        Route::put('/cover-letters/{coverLetter}', [CoverLetterController::class, 'update'])->name('cover-letters.update');
        Route::delete('/cover-letters/{coverLetter}', [CoverLetterController::class, 'destroy'])->name('cover-letters.destroy');
        Route::get('/cover-letters/{coverLetter}/preview', [CoverLetterController::class, 'preview'])->name('cover-letters.preview');
        Route::get('/cover-letters/{coverLetter}/download/docx', [CoverLetterController::class, 'downloadDocx'])->name('cover-letters.download.docx');
        Route::get('/cover-letters/{coverLetter}/download/pdf', [CoverLetterController::class, 'downloadPdf'])->name('cover-letters.download.pdf');

        // Mail preview routes
        Route::get('/mail-preview', [MailPreviewController::class, 'index'])->name('mail-preview.index');
        Route::get('/mail-preview/{mailable}', [MailPreviewController::class, 'show'])->name('mail-preview.show');
        Route::get('/mail-preview/{mailable}/preview', [MailPreviewController::class, 'preview'])->name('mail-preview.preview');

        // Site settings (navigation links)
        Route::get('/site-settings', [SiteSettingsController::class, 'edit'])->name('site-settings.edit');
        Route::post('/site-settings', [SiteSettingsController::class, 'update'])->name('site-settings.update');
    });

// AI Tools routes - requires auth + manage-ai-tools permission
Route::middleware(['auth', 'can:manage-ai-tools'])
    ->prefix('admin/ai')
    ->name('admin.ai.')
    ->group(function () {
        Route::get('/', [AiToolsController::class, 'index'])->name('index');

        // AI Systems CRUD
        Route::get('/systems', [AiSystemController::class, 'index'])->name('systems.index');
        Route::get('/systems/new', [AiSystemController::class, 'create'])->name('systems.create');
        Route::post('/systems', [AiSystemController::class, 'store'])->name('systems.store');
        Route::get('/systems/{aiSystem}', [AiSystemController::class, 'edit'])->name('systems.edit');
        Route::put('/systems/{aiSystem}', [AiSystemController::class, 'update'])->name('systems.update');
        Route::delete('/systems/{aiSystem}', [AiSystemController::class, 'destroy'])->name('systems.destroy');
        Route::get('/systems/{aiSystem}/logs', [AiSystemController::class, 'logs'])->name('systems.logs');
    });

// Resume editor routes - requires auth + edit-resume permission
Route::middleware(['auth', 'can:edit-resume'])
    ->prefix('admin/resume')
    ->name('admin.resume.')
    ->group(function () {
        Route::get('/editor', [ResumeEditorController::class, 'edit'])->name('editor');
        Route::post('/editor', [ResumeEditorController::class, 'update'])->name('editor.save');

        // Targeted Resume Builder
        Route::get('/targeted-builder', [TargetedResumeController::class, 'index'])->name('targeted.index');
        Route::get('/targeted-builder/new', [TargetedResumeController::class, 'create'])->name('targeted.create');
        Route::post('/targeted-builder/start', [TargetedResumeController::class, 'start'])->name('targeted.start');
        Route::get('/targeted-builder/{conversation}', [TargetedResumeController::class, 'show'])->name('targeted.show');
        Route::post('/targeted-builder/{conversation}/chat', [TargetedResumeController::class, 'chat'])->name('targeted.chat');
        Route::post('/targeted-builder/{conversation}/finalize', [TargetedResumeController::class, 'finalize'])->name('targeted.finalize');
        Route::get('/targeted-resume/{targetedResume}/download/{format}', [TargetedResumeController::class, 'download'])->name('targeted.download');
    });

// Manual code entry page for unauthenticated users
Route::get('/resume/enter-code', [ResumeController::class, 'enterCode'])->name('resume.enter-code');

// Resume routes - supports both authenticated users and share codes
Route::middleware([ResumeShareCodeMiddleware::class])->prefix('resume')->group(function () {
    Route::get('/', [ResumeController::class, 'index'])->name('resume.index');

    // Direct download of pre-generated DOCX
    Route::prefix('/download')->name('resume.download.')->group(function () {
        Route::get('/', [ResumeController::class, 'download'])->name('index');
        Route::get('/docx', [ResumeController::class, 'downloadDocx'])->name('docx');
        Route::get('/pdf', [ResumeController::class, 'downloadPdf'])->name('pdf');
    });
});

// Honeypots
Route::get('/wp-admin/load-styles.php', function () {
    return response()->view('wp-load_styles')->header('Content-Type', 'text/css');
});
Route::get('/wp-login.php', [WordpressController::class, 'index']);
Route::post('/wp-login.php', [WordpressController::class, 'ban']);
Route::redirect('/wp-admin', '/wp-login.php');
