<?php

namespace App\Http\Controllers\Course;

use Illuminate\Http\Request;
use App\Enums\CourseAudience;
use App\Enums\CourseBillingModel;
use App\Enums\CourseLevelType;
use App\Enums\CoursePricingType;
use App\Enums\CourseStatusType;
use App\Enums\ExpiryLimitType;
use App\Services\InstructorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Requests\UpdateCourseStatusRequest;
use App\Services\Course\AssignmentSubmissionService;
use App\Services\Course\LessonActivitySubmissionService;
use App\Services\Course\CourseCategoryService;
use App\Services\Course\CourseFinalExamService;
use App\Services\Course\CourseService;
use App\Services\Course\CourseSectionService;
use App\Services\Course\CoursePlayerService;
use App\Services\Course\CourseWishlistService;
use App\Services\Course\CourseReviewService;
use App\Models\Course\Course;
use App\Services\LiveClass\ZoomLiveService;
use App\Services\Payment\CourseStripeSyncService;
use App\Services\Payment\StripeCustomerService;
use App\Services\Payment\SubscriptionAccessService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CourseController extends Controller
{
    public function __construct(
        protected CourseService $courseService,
        protected ZoomLiveService $zoomLiveService,
        protected CourseCategoryService $categoryService,
        protected InstructorService $instructorService,
        protected CourseSectionService $courseSectionService,
        protected CoursePlayerService $coursePlayerService,
        protected CourseWishlistService $wishlistService,
        protected CourseReviewService $reviewService,
        protected AssignmentSubmissionService $submissionService,
        protected LessonActivitySubmissionService $activitySubmissionService,
        protected CourseFinalExamService $courseFinalExamService,
        protected SubscriptionAccessService $subscriptionAccess,
        protected StripeCustomerService $stripeCustomer,
        protected CourseStripeSyncService $courseStripeSync,
    ) {}

    public function index(Request $request)
    {
        $statuses = CourseStatusType::cases();
        $courses = $this->courseService->getCourses($request->all(), Auth::user(), true);

        return Inertia::render('dashboard/courses/index', compact('courses', 'statuses'));
    }

    public function category_courses(Request $request, string $category, ?string $category_child = null)
    {
        // Save original category slug before it gets overwritten
        $originalCategorySlug = $category;

        if (Auth::check()) {
            $query = array_filter([
                ...$request->query(),
            ]);

            return redirect()->route('student.category.courses', array_merge(['category' => $originalCategorySlug], $query));
        }
        
        $user = Auth::user() ? Auth::user() : null;
        $query = [...$request->all(), 'per_page' => 12, 'category' => $category, 'category_child' => $category_child, 'status' => 'approved', 'catalog' => true];

        $levels = CourseLevelType::cases();
        $prices = CoursePricingType::cases();
        $category = $this->categoryService->getCategoryBySlug($category);
        $categoryChild = $this->categoryService->getCategoryChildBySlug($category_child);
        $categories = $this->categoryService->getCategories()['categories'];
        $wishlists = $this->wishlistService->getWishlists(['user_id' => $user ? $user->id : null]);
        $courses = $this->courseService->getCourses($query, $user, true);

        // Generate meta tags for SEO and social sharing
        $system = app('system_settings');
        $siteName = \App\Support\Branding::resolveSiteName($system->fields['name'] ?? null);
        $siteUrl = request()->url();

        // Get course count with proper fallback
        $totalCourses = 0;
        if (isset($courses->total)) {
            $totalCourses = $courses->total;
        } elseif (isset($courses->data) && is_array($courses->data)) {
            $totalCourses = count($courses->data);
        }

        // Get first course thumbnail (prioritize over category image)
        $firstCourseImage = null;
        if (!empty($courses->data) && is_array($courses->data) && count($courses->data) > 0) {
            $firstCourse = $courses->data[0];
            $firstCourseImage = $firstCourse->thumbnail ?? $firstCourse->banner ?? null;
        }

        $pageTitle = 'All Courses';
        $pageDescription = $totalCourses > 0
            ? "Browse $totalCourses+ online courses from expert instructors. Learn new skills with our comprehensive course catalog."
            : "Browse online courses from expert instructors. Learn new skills with our comprehensive course catalog.";
        $pageKeywords = 'online courses, learning platform, education, skills, training, e-learning';
        $ogTitle = 'Online Courses';
        $categoryImage = null;

        if ($category && $categoryChild) {
            $pageTitle = $categoryChild->title . ' Courses in ' . $category->title;
            $ogTitle = $categoryChild->title . ' - ' . $category->title . ' Courses';
            $pageDescription = $totalCourses > 0
                ? "Learn " . strtolower($categoryChild->title) . " with $totalCourses specialized courses in " . strtolower($category->title) . ". Expert instructors, practical projects, and industry-relevant curriculum."
                : "Learn " . strtolower($categoryChild->title) . " with specialized courses in " . strtolower($category->title) . ". Expert instructors, practical projects, and industry-relevant curriculum.";
            $pageKeywords = strtolower($categoryChild->title) . ', ' . strtolower($category->title) . ', courses, training, ' . $categoryChild->title . ' certification, ' . $category->title . ' skills';
            $categoryImage = $category->thumbnail ?? null;
        } else if ($category) {
            $pageTitle = $category->title . ' Courses';
            $ogTitle = $category->title . ' Courses';
            $pageDescription = $totalCourses > 0
                ? "Explore $totalCourses " . strtolower($category->title) . " courses taught by industry experts. Master " . strtolower($category->title) . " skills with hands-on projects and comprehensive curriculum."
                : "Explore " . strtolower($category->title) . " courses taught by industry experts. Master " . strtolower($category->title) . " skills with hands-on projects and comprehensive curriculum.";
            $pageKeywords = strtolower($category->title) . ', courses, training, online learning, ' . $category->title . ' certification, ' . $category->title . ' skills';
            $categoryImage = $category->thumbnail ?? null;
        }

        $fullTitle = $pageTitle . ' | ' . $siteName;

        // Prioritize first course image over category image, then fallback to system banner
        $ogImage = $firstCourseImage ?? $categoryImage ?? $system->fields['banner'] ?? '';

        $response = Inertia::render('courses/index', compact(
            'levels',
            'prices',
            'courses',
            'categories',
            'category',
            'categoryChild',
            'wishlists'
        ))->withViewData([
            'metaTitle' => $fullTitle,
            'metaDescription' => $pageDescription,
            'metaKeywords' => $pageKeywords,
            'ogTitle' => $ogTitle,
            'ogDescription' => $pageDescription,
            'ogImage' => $ogImage,
            'ogUrl' => $siteUrl,
            'ogType' => 'website',
            'twitterCard' => 'summary_large_image',
            'twitterTitle' => $ogTitle,
            'twitterDescription' => $pageDescription,
            'twitterImage' => $ogImage,
        ]);

        return $response;
    }

    public function create()
    {
        $labels = CourseLevelType::cases();
        $prices = CoursePricingType::cases();
        $audiences = CourseAudience::cases();
        $expiries = ExpiryLimitType::cases();
        $categories = $this->categoryService->getCategories()['categories'];
        $instructors = $this->instructorService->getInstructors(['status' => 'approved'], false);

        return Inertia::render('dashboard/courses/create', compact(
            'labels',
            'prices',
            'audiences',
            'expiries',
            'categories',
            'instructors'
        ));
    }

    public function store(StoreCourseRequest $request)
    {
        $this->courseService->createCourse($request->validated());

        return redirect(route('courses.index'))->with('success', 'Course added successfully');
    }

    public function show(Request $request, $slug, $id)
    {
        // validate slug
        if (empty($slug)) {
            return redirect()->back();
        }

        $user = Auth::user() ? Auth::user() : null;

        // course details
        $course = $this->courseService->getGuestCourseById($id);

        if (!$course || !$course->isVisibleToUser($user)) {
            abort(404);
        }

        $enrollment = $this->courseService->getCourseEnroll($course->id);
        $wishlists = $this->wishlistService->getWishlists(['user_id' => $user ? $user->id : null]);
        $watchHistory = $this->coursePlayerService->getWatchHistory($course->id, Auth::user() ? Auth::user()->id : null);
        $approvalStatus = $this->courseService->validateCourseForApproval($course);
        $reviews = $this->reviewService->getReviews(['course_id' => $course->id, ...$request->all()], true);
        $totalReviews = $this->reviewService->totalReviews($course->id);
        $subscriptionAccess = $user
            ? $this->subscriptionAccess->toFrontendPayload($user, $course, $enrollment)
            : null;

        if ($course->exists()) {
            // Generate meta tags for SEO and social sharing
            $system = app('system_settings');
            $siteName = \App\Support\Branding::resolveSiteName($system->fields['name'] ?? null);
            $pageTitle = $course->meta_title ?? ($course->title . ' | ' . $siteName);
            $pageDescription = $course->meta_description ?? $course->short_description ?? $course->description ?? 'Learn with our comprehensive course';
            $pageKeywords = $course->meta_keywords ?? ($course->title . ', online course, learning, ' . ($system->fields['keywords'] ?? 'LMS'));
            $ogTitle = $course->og_title ?? $course->title;
            $ogDescription = $course->og_description ?? $pageDescription;

            // Prioritize course images: thumbnail > banner > system banner
            $courseImage = $course->thumbnail ?? $course->banner ?? $system->fields['banner'] ?? '';
            $siteUrl = request()->url();

            return Inertia::render(
                'courses/show',
                [
                    'course' => $course,
                    'enrollment' => $enrollment,
                    'watchHistory' => $watchHistory,
                    'approvalStatus' => $approvalStatus,
                    'wishlists' => $wishlists,
                    'reviews' => $reviews,
                    'totalReviews' => $totalReviews,
                    'subscriptionAccess' => $subscriptionAccess,
                ]
            )->withViewData([
                'metaTitle' => $pageTitle,
                'metaDescription' => $pageDescription,
                'metaKeywords' => $pageKeywords,
                'ogTitle' => $ogTitle,
                'ogDescription' => $ogDescription,
                'ogImage' => $courseImage,
                'ogUrl' => $siteUrl,
                'ogType' => 'article',
                'twitterCard' => 'summary_large_image',
                'twitterTitle' => $ogTitle,
                'twitterDescription' => $ogDescription,
                'twitterImage' => $courseImage,
            ]);
        } else {
            return redirect()->back();
        }
    }

    public function edit(Request $request, string $id)
    {
        $user = Auth::user();

        $tab = $request->tab;
        $assignment = $request->assignment;

        $statuses = CourseStatusType::cases();
        $labels = CourseLevelType::cases();
        $prices = CoursePricingType::cases();
        $audiences = CourseAudience::cases();
        $expiries = ExpiryLimitType::cases();
        $course = $this->courseService->getUserCourseById($id, $user);
        $watchHistory = $this->coursePlayerService->getWatchHistory($course->id, $user->id);
        $approvalStatus = $this->courseService->validateCourseForApproval($course);
        $lastSortValues = $this->courseService->lastSectionLessonSort($course);
        $categories = $this->categoryService->getCategories();
        $categories = $categories['categories'];
        $zoomConfig = $this->zoomLiveService->zoomConfig;
        $instructors = isAdmin() ? $this->instructorService->getInstructors(['status' => 'approved'], false) : null;
        $instructorExams = $this->courseFinalExamService->getSelectableExams($user, $course);

        $totalSubmissions = $course->assignments->sum(function ($assignment) {
            return $assignment->submissions->count();
        });
        $totalEnrollments = $course->enrollments->count();

        $submissions = null;
        if ($assignment) {
            $submissions = $this->submissionService->getSubmissions($assignment, $request->all());
        }

        $activitySubmissions = $tab === 'activity-reviews'
            ? $this->activitySubmissionService->getSubmissionsForCourse((int) $course->id, $request->all())
            : null;

        return Inertia::render(
            'dashboard/courses/update',
            [
                ...$lastSortValues,
                'tab' => $tab,
                'assignment' => $assignment,
                'prices' => $prices,
                'audiences' => $audiences,
                'course' => $course,
                'statuses' => $statuses,
                'labels' => $labels,
                'expiries' => $expiries,
                'categories' => $categories,
                'submissions' => $submissions,
                'activitySubmissions' => $activitySubmissions,
                'watchHistory' => $watchHistory,
                'approvalStatus' => $approvalStatus,
                'zoomConfig' => $zoomConfig,
                'instructors' => $instructors,
                'instructorExams' => $instructorExams,
                'totalSubmissions' => $totalSubmissions,
                'totalEnrollments' => $totalEnrollments,
                'billingModels' => collect(CourseBillingModel::cases())
                    ->map(fn (CourseBillingModel $model) => [
                        'value' => $model->value,
                        'label' => $model->getLabel(),
                    ])
                    ->values(),
                'stripeActive' => $this->stripeCustomer->isStripeActive(),
                'stripeSynced' => $this->courseStripeSync->isSynced($course),
            ]
        );
    }

    public function update(UpdateCourseRequest $request, $id)
    {
        $this->courseService->updateCourse($id, $request->validated());

        return back()->with('success', "Course $request->tab updated successfully");
    }

    public function status(UpdateCourseStatusRequest $request, $id)
    {
        $validated = $request->validated();

        // Trainers and admins can publish/approve a course directly. There is no
        // separate admin-approval step and no pre-publish content requirement.
        $this->courseService->updateCourse($id, [...$validated, 'tab' => 'status']);

        return back()->with('success', 'Course status changed successfully');
    }

    public function destroy($id)
    {
        $this->courseService->deleteCourse($id);

        return redirect(route('courses.index'))->with('success', 'Course deleted successfully');
    }
}
