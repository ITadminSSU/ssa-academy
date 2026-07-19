<?php

use App\Http\Controllers\HomeController;
use App\Services\AuthService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        using: function () {
            // Fallback for serving public storage files when the
            // public/storage symlink is missing (e.g. shared hosting).
            // If the symlink exists, the web server serves the file directly
            // and this route is never reached.
            Route::get('storage/{path}', [\App\Http\Controllers\StorageFileController::class, 'show'])
                ->where('path', '.*')
                ->name('storage.public');

            // Web Routes
            Route::middleware(['web', 'installed', 'appConfig'])->group(function () {
                // Public routes
                require base_path('routes/web.php');

                // Auth routes
                Route::middleware(['smtpConfig'])->group(base_path('routes/auth.php'));

                // Role dashboard entry routes
                require base_path('routes/dashboards.php');

                // Admin routes
                Route::middleware(['auth', 'role:admin'])->group(base_path('routes/admin.php'));

                // Instructor routes
                Route::middleware(['auth', 'verified', 'legalAgreement', 'role:admin,instructor'])->group(base_path('routes/instructor.php'));

                // Student routes
                Route::middleware(['auth', 'legalAgreement', 'role:student,instructor,admin'])->group(base_path('routes/student.php'));

                Route::get('/{slug}', [HomeController::class, 'inner_page'])->name('inner.page');
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(function (Request $request) {
            return app(AuthService::class)->homeUrlFor($request->user());
        });

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->encryptCookies(except: ['appearance']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->preventRequestsDuringMaintenance(except: [
            'system/*',
            'install/refresh',
        ]);

        $middleware->alias([
            'customize' => \App\Http\Middleware\IntroCustomize::class,
            'appConfig' => \App\Http\Middleware\AppConfig::class,
            'authConfig' => \App\Http\Middleware\AuthConfig::class,
            'smtpConfig' => \App\Http\Middleware\SmtpConfig::class,
            'checkSmtp' => \App\Http\Middleware\SmtpConfigCheck::class,
            'checkEnroll' => \App\Http\Middleware\CheckEnroll::class,
            'checkCourseCreation' => \App\Http\Middleware\CheckCourseCreation::class,
            'ip.detector' => \App\Http\Middleware\IpDetectorMiddleware::class,
            'verifiedAccess' => \App\Http\Middleware\VerifiedAccess::class,
            'role' => \App\Http\Middleware\CheckRole::class,
            'learnerDashboard' => \App\Http\Middleware\EnsureLearnerDashboard::class,
            'legalAgreement' => \App\Http\Middleware\EnsureLegalAgreementAccepted::class,
            'feature' => \App\Http\Middleware\EnsureFeatureEnabled::class,
            'installed' => \Modules\Installer\Http\Middleware\InstalledRoutes::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $inertiaLocation = function (Request $request, string $url) {
            if ($request->header('X-Inertia')) {
                return response('', 409)->header('X-Inertia-Location', $url);
            }

            return redirect($url);
        };

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($inertiaLocation) {
            if ($request->header('X-Inertia')) {
                return $inertiaLocation($request, route('login'));
            }
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($inertiaLocation) {
            if ($request->header('X-Inertia')) {
                return $inertiaLocation($request, route('login'));
            }
        });
    })->create();
