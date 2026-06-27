<?php

use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Cms\PageController;
use App\Http\Controllers\Api\V1\Auth\LogoutController;
use App\Http\Controllers\Api\V1\Auth\MeController;
use App\Http\Controllers\Api\V1\Blog\BlogCategoryController;
use App\Http\Controllers\Api\V1\Blog\BlogTagController;
use App\Http\Controllers\Api\V1\Blog\PostController;
use App\Http\Controllers\Api\V1\Cart\CartController;
use App\Http\Controllers\Api\V1\Catalog\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\PackageController;
use App\Http\Controllers\Api\V1\Catalog\ProductController;
use App\Http\Controllers\Api\V1\Catalog\TagController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\Intake\IntakeSchemaController;
use App\Http\Controllers\Api\V1\Leads\LeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes  —  /api/v1/
|--------------------------------------------------------------------------
|
| Token auth: Laravel Sanctum (Bearer tokens).
|
| Ability scopes:
|   frontend:*        Issued to the React/Next.js frontend for a logged-in user.
|   patient:*         Issued to patient self-service portal sessions.
|   integration:*     Issued to 3rd-party integrations (webhooks, CRM, etc.).
|   admin:*           Issued to server-to-server admin tooling.
|
| Public (no auth required):
|   GET  /api/v1/config                      Bootstrap call — brand, theme, contact, provider caps.
|   GET  /api/v1/pages                       All published pages (list, no sections).
|   GET  /api/v1/pages/{slug}               Single published page with typed sections.
|   GET  /api/v1/catalog/categories          Category tree or flat list.
|   GET  /api/v1/catalog/categories/{slug}   Category detail.
|   GET  /api/v1/catalog/products            Paginated product list (filter: category, tag, featured, search).
|   GET  /api/v1/catalog/products/{slug}     Product detail with categories + tags.
|   GET  /api/v1/catalog/packages            Paginated package list with plans.
|   GET  /api/v1/catalog/packages/{slug}     Package detail with products + plans.
|   GET  /api/v1/catalog/tags                All visible tags.
|   GET  /api/v1/blog/posts                  Paginated blog posts.
|   GET  /api/v1/blog/posts/{slug}           Single blog post.
|   GET  /api/v1/blog/categories             Blog categories.
|   GET  /api/v1/blog/tags                   Blog tags.
|   GET  /api/v1/cart                        View cart (X-Cart-Token header).
|   POST /api/v1/cart/items                  Add item to cart.
|   POST /api/v1/leads                       Create lead at checkout start.
|   GET  /api/v1/leads/{uuid}                Retrieve lead by UUID.
|   GET  /api/v1/intake/schema               Resolve intake question schema for cart products.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {

    // ── Public ──────────────────────────────────────────────────────────
    // No auth required. Safe to cache at CDN edge.

    Route::get('config', ConfigController::class)->name('config');

    // ── CMS ──────────────────────────────────────────────────────────────
    // Published pages with typed section blueprints.

    Route::prefix('pages')->name('pages.')->middleware('throttle:api')->group(function (): void {
        Route::get('/', [PageController::class, 'index'])->name('index');
        Route::get('{slug}', [PageController::class, 'show'])->name('show');
    });

    // ── Catalog ──────────────────────────────────────────────────────────
    // Fully public — storefront browse does not require login.

    Route::prefix('catalog')->name('catalog.')->middleware('throttle:api')->group(function (): void {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product:slug}', [ProductController::class, 'show'])->name('products.show');

        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('packages/{package:slug}', [PackageController::class, 'show'])->name('packages.show');

        Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    });

    // ── Blog ─────────────────────────────────────────────────────────────
    // Fully public — no auth required to read published posts.

    Route::prefix('blog')->name('blog.')->middleware('throttle:api')->group(function (): void {
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/{post:slug}', [PostController::class, 'show'])->name('posts.show');
        Route::get('categories', [BlogCategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/{blogCategory:slug}', [BlogCategoryController::class, 'show'])->name('categories.show');
        Route::get('tags', [BlogTagController::class, 'index'])->name('tags.index');
    });

    // ── Leads ────────────────────────────────────────────────────────────
    // Lead creation is public (called before login). Retrieval by UUID is
    // also public — the UUID is opaque and only known from the creation response.

    Route::prefix('leads')->name('leads.')->middleware('throttle:api')->group(function (): void {
        Route::post('/', [LeadController::class, 'store'])->name('store');
        Route::get('{lead:uuid}', [LeadController::class, 'show'])->name('show');
    });

    // ── Intake ────────────────────────────────────────────────────────────
    // Resolves dynamic intake question schema from the configured provider.

    Route::get('intake/schema', IntakeSchemaController::class)
        ->middleware('throttle:api')
        ->name('intake.schema');

    // ── Cart ─────────────────────────────────────────────────────────────
    // Token-identified via X-Cart-Token header. No auth required.
    // A new cart is created automatically when the token is absent or expired.

    Route::prefix('cart')->name('cart.')->middleware('throttle:api')->group(function (): void {
        Route::get('/', [CartController::class, 'show'])->name('show');
        Route::post('items', [CartController::class, 'addItem'])->name('items.add');
        Route::patch('items/{cartItem}', [CartController::class, 'updateItem'])->name('items.update');
        Route::delete('items/{cartItem}', [CartController::class, 'removeItem'])->name('items.remove');
        Route::delete('/', [CartController::class, 'clear'])->name('clear');
    });

    // ── Auth ────────────────────────────────────────────────────────────
    // Token issue / revoke endpoints. Rate-limited to prevent brute-force.

    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function (): void {
        Route::post('login', LoginController::class)->name('login');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', LogoutController::class)->name('logout');
            Route::get('me', MeController::class)->name('me');
        });
    });

    // ── Authenticated (frontend scope) ───────────────────────────────────
    // All routes below require a valid Sanctum token and are rate-limited.

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        // Catalog, products, checkout, patient portal routes — added per module.
    });
});
