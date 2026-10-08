<?php

use App\Http\Controllers\Api\AssistantChatController;
use App\Http\Controllers\Api\AssistantFollowUpController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthorController;
use App\Http\Controllers\Api\CampusEventController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\ContentAnalyticsController;
use App\Http\Controllers\Api\PublicCmsPageController;
use App\Http\Controllers\Api\ProgramPageController;
use App\Http\Controllers\Api\PublicSdgController;
use App\Http\Controllers\Api\ResearchPublicationController;
use App\Http\Controllers\Api\Cms\AccountController;
use App\Http\Controllers\Api\Cms\AdminArticleController;
use App\Http\Controllers\Api\Cms\AdminProgramPageController;
use App\Http\Controllers\Api\Cms\AdminResearchPublicationController;
use App\Http\Controllers\Api\Cms\AdminAssistantTicketController;
use App\Http\Controllers\Api\Cms\AdminAssistantVisitorController;
use App\Http\Controllers\Api\Cms\AdminUserController;
use App\Http\Controllers\Api\Cms\AuthController as CmsAuthController;
use App\Http\Controllers\Api\Cms\CmsAnalyticsController;
use App\Http\Controllers\Api\Cms\CmsPageAdminController;
use App\Http\Controllers\Api\Cms\CmsSdgCoverageController;
use App\Http\Controllers\Api\Cms\MediaController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\SitemapController;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/api/vote.php';

Route::prefix('v1')->group(function (): void {
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/news', [ArticleController::class, 'index']);
    Route::get('/news/{slug}', [ArticleController::class, 'show']);
    Route::get('/authors/{user}', [AuthorController::class, 'show']);
    Route::get('/research/stats', [ResearchPublicationController::class, 'stats']);
    Route::get('/research', [ResearchPublicationController::class, 'index']);
    Route::get('/research/{slug}', [ResearchPublicationController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/program-pages', [ProgramPageController::class, 'index']);
    Route::get('/program-pages/{slug}', [ProgramPageController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
    Route::get('/events', [CampusEventController::class, 'index']);
    Route::get('/events/{slug}', [CampusEventController::class, 'show']);
    Route::get('/sdgs', [PublicSdgController::class, 'index']);
    Route::get('/sdgs/{goal}', [PublicSdgController::class, 'show'])->whereNumber('goal');
    Route::post('/contact', [ContactMessageController::class, 'store'])
        ->middleware('throttle:contact');

    Route::post('/assistant/chat', [AssistantChatController::class, 'chat'])
        ->middleware('throttle:30,1');
    Route::post('/assistant/follow-up', [AssistantFollowUpController::class, 'store'])
        ->middleware('throttle:assistant-follow-up');

    Route::post('/analytics/track', [ContentAnalyticsController::class, 'track'])
        ->middleware('throttle:120,1');

    Route::get('/site-pages/{slug}', [PublicCmsPageController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');

    Route::prefix('cms')->group(function (): void {
        Route::post('login', [CmsAuthController::class, 'login'])
            ->middleware('throttle:cms-login');

        Route::middleware(['auth:sanctum', 'abilities:cms:access'])->group(function (): void {
            Route::post('logout', [CmsAuthController::class, 'logout']);
            Route::get('user', [CmsAuthController::class, 'user']);
            Route::put('account', [AccountController::class, 'updateProfile']);
            Route::patch('account', [AccountController::class, 'updateProfile']);
            Route::put('account/password', [AccountController::class, 'updatePassword']);
            Route::patch('account/password', [AccountController::class, 'updatePassword']);

            Route::middleware('cms.module:posts')->group(function (): void {
                Route::get('articles', [AdminArticleController::class, 'index']);
                Route::get('articles/{article}', [AdminArticleController::class, 'show']);
                Route::post('articles', [AdminArticleController::class, 'store']);
                Route::put('articles/{article}', [AdminArticleController::class, 'update']);
                Route::patch('articles/{article}', [AdminArticleController::class, 'update']);
                Route::delete('articles/{article}', [AdminArticleController::class, 'destroy']);
            });

            Route::post('media', [MediaController::class, 'store']);
            Route::post('media/documents', [MediaController::class, 'storeDocument']);

            Route::get('my-dashboard', [CmsAnalyticsController::class, 'author']);

            Route::get('sdg-coverage', [CmsSdgCoverageController::class, 'index']);
            Route::get('sdg-coverage/{goal}', [CmsSdgCoverageController::class, 'show'])
                ->whereNumber('goal');

            Route::middleware('cms.module:research')->group(function (): void {
                Route::get('research-publications', [AdminResearchPublicationController::class, 'index']);
                Route::get('research-publications/{research_publication}', [AdminResearchPublicationController::class, 'show']);
                Route::post('research-publications', [AdminResearchPublicationController::class, 'store']);
                Route::put('research-publications/{research_publication}', [AdminResearchPublicationController::class, 'update']);
                Route::patch('research-publications/{research_publication}', [AdminResearchPublicationController::class, 'update']);
                Route::delete('research-publications/{research_publication}', [AdminResearchPublicationController::class, 'destroy']);
            });

            Route::middleware('cms.module:programs')->group(function (): void {
                Route::get('program-pages', [AdminProgramPageController::class, 'index']);
                Route::get('program-pages/offering/{offering}', [AdminProgramPageController::class, 'showByOffering']);
                Route::get('program-pages/{program_page}', [AdminProgramPageController::class, 'show']);
                Route::post('program-pages', [AdminProgramPageController::class, 'store']);
                Route::put('program-pages/{program_page}', [AdminProgramPageController::class, 'update']);
                Route::patch('program-pages/{program_page}', [AdminProgramPageController::class, 'update']);
                Route::delete('program-pages/{program_page}', [AdminProgramPageController::class, 'destroy']);
            });

            Route::middleware('cms.module:assistant')->group(function (): void {
                Route::get('assistant/visitors', [AdminAssistantVisitorController::class, 'index']);
                Route::get('assistant/visitors/{visitor}', [AdminAssistantVisitorController::class, 'show']);
                Route::get('assistant/tickets', [AdminAssistantTicketController::class, 'index']);
                Route::get('assistant/tickets/{ticket}', [AdminAssistantTicketController::class, 'show']);
                Route::put('assistant/tickets/{ticket}', [AdminAssistantTicketController::class, 'update']);
                Route::patch('assistant/tickets/{ticket}', [AdminAssistantTicketController::class, 'update']);
            });

            Route::middleware('admin')->group(function (): void {
                Route::get('analytics', [CmsAnalyticsController::class, 'index']);

                Route::get('users', [AdminUserController::class, 'index']);
                Route::post('users', [AdminUserController::class, 'store']);
                Route::put('users/{user}', [AdminUserController::class, 'update']);
                Route::patch('users/{user}', [AdminUserController::class, 'update']);
                Route::put('users/{user}/password', [AdminUserController::class, 'resetPassword']);
                Route::patch('users/{user}/password', [AdminUserController::class, 'resetPassword']);
                Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

                Route::get('pages', [CmsPageAdminController::class, 'index']);
                Route::get('pages/{slug}', [CmsPageAdminController::class, 'show'])
                    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
                Route::put('pages/{slug}', [CmsPageAdminController::class, 'update'])
                    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
                Route::patch('pages/{slug}', [CmsPageAdminController::class, 'update'])
                    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*');
            });
        });
    });

    Route::get('/sitemap.xml', [SitemapController::class, '__invoke']);
});
