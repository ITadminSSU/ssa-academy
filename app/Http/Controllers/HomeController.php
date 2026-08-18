<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Requests\UpdatePageSectionRequest;
use App\Models\Page;
use App\Models\Setting;
use App\Services\Course\CourseCategoryService;
use App\Services\JobCircularService;
use App\Services\AuthService;
use App\Services\PageService;
use App\Services\TeamMemberService;
use App\Support\LandingOverlay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class HomeController extends Controller
{
   public function __construct(
      protected PageService $pageService,
      protected JobCircularService $jobCircularService,
      protected CourseCategoryService $categoryService,
      protected AuthService $authService,
      protected TeamMemberService $teamMemberService,
   ) {}

   public function index(Request $request)
   {
      $user = Auth::user();
      $previewOverlay = $request->boolean('preview_overlay')
         && $user
         && $user->canManagePlatformSettings();

      if ($user && ! $previewOverlay) {
         return redirect($this->authService->homeUrlFor($user));
      }

      $page = app('intro_page');

      if (!$page || $page->slug !== 'ssu-home') {
         $page = Page::where('slug', 'ssu-home')
            ->with(['sections' => function ($query) {
               $query->orderBy('sort', 'asc');
            }])
            ->first();
      }

      if (!$page) {
         return redirect()->route('category.courses', ['category' => 'all']);
      }

      $sections = $this->pageService->getPageSections($request->all(), $page);
      $homeSetting = Setting::where('type', 'home_page')->first();
      $homeFields = is_array($homeSetting?->fields) ? $homeSetting->fields : [];

      return Inertia::render('intro/ssu-home', [
         'page' => $page,
         'type' => 'intro',
         'landingOverlay' => LandingOverlay::publicPayload($homeFields, $previewOverlay),
         'landingOverlayForce' => $previewOverlay,
         ...$sections,
      ]);
   }

   public function about(Request $request)
   {
      return Inertia::render('intro/ssu-about', [
         'type' => 'intro',
         'teamMembers' => $this->teamMemberService->listForPublic(),
      ]);
   }

   public function faqs(Request $request)
   {
      return Inertia::render('intro/ssu-faqs', [
         'type' => 'intro',
      ]);
   }

   public function demo(Request $request, string $slug)
   {
      return redirect()->route('home');
   }

   /**
    * Update the specified section in storage.
    */
   public function update_section(UpdatePageSectionRequest $request, string $id)
   {
      // dd($request->all());
      $section = $this->pageService->updatePageSection($id, $request->validated());

      return back()->with('success', "Section '{$section->name}' has been updated successfully");
   }

   /**
    * Update the specified section in storage.
    */
   public function sort_section(Request $request)
   {
      $this->pageService->sortPageSections($request->sortedData);

      return back()->with('success', "Page sections is sorted successfully");
   }

   public function inner_page(Request $request)
   {
      if ($request->slug === 'confirm-email-change') {
         return app(EmailVerificationNotificationController::class)->save($request);
      }

      if ($request->slug === 'contact-us') {
         return redirect()->away('https://smartsourcingusa.com/contact');
      }

      $innerPages = Page::where('type', 'inner_page')
         ->where('active', true)
         ->select(['slug'])
         ->get();
      $validSlugs = $innerPages->pluck('slug')->toArray();

      // Check if the requested slug exists in inner pages
      if (!in_array($request->slug, $validSlugs)) {
         // abort(404);
         return Inertia::render('404');
      }

      $innerPage = $this->pageService->getCustomPageBySlug($request->slug);
      $sections = $request->slug === 'careers' ? [] : $this->pageService->getPageSections($request->all(), $innerPage);
      $jobCirculars = $request->slug === 'careers' ? $this->jobCircularService->getActiveJobCirculars($request->all()) : null;

      return Inertia::render('inner/index', [
         'innerPage' => $innerPage,
         'jobCirculars' => $jobCirculars,
         ...$sections
      ]);
   }

   public function seeder()
   {
      ini_set('max_execution_time', 600);

      try {
         Artisan::call('migrate:fresh', ['--force' => true, '--seed' => true]);

         Artisan::call('storage:link');

         Artisan::call('optimize:clear');

         return back()->with('success', 'Installation completed successfully. You can now log in.');
      } catch (\Throwable $th) {
         return back()->with('error', 'Error: ' . $th->getMessage());
      }
   }
}