<?php

it('places reviews and the instructor around the existing course page without a second billing sentence', function () {
    $show = file_get_contents(resource_path('js/pages/courses/show.tsx'));
    $preview = file_get_contents(resource_path('js/pages/courses/partials/course-preview.tsx'));
    $panel = file_get_contents(resource_path('js/components/ssu-enrollment-panel.tsx'));
    $strip = file_get_contents(resource_path('js/pages/courses/partials/course-review-strip.tsx'));
    $card = file_get_contents(resource_path('js/pages/courses/partials/course-instructor-card.tsx'));
    $reviewsTab = file_get_contents(resource_path('js/pages/courses/partials/course-reviews.tsx'));

    expect(strpos($show, '<CourseHero'))->toBeLessThan(strpos($show, '<CourseReviewStrip'))
        ->and(strpos($show, '<CourseReviewStrip'))->toBeLessThan(strpos($show, '<Tabs'))
        ->and(strpos($show, '</Tabs>'))->toBeLessThan(strpos($show, '<CourseInstructorCard'))
        ->and($show)->not->toContain("value: 'instructor'")
        ->and($show)->toContain('showUsExperience')
        ->and($show)->toContain("value: 'reviews'")
        ->and($show)->toContain("value: 'us-experience'");

    expect($strip)->toContain('items.length === 0')
        ->and($strip)->toContain('return null')
        ->and($strip)->toContain('overflow-x-auto')
        ->and($strip)->toContain('review.user')
        ->and($strip)->toContain('review.rating')
        ->and($strip)->toContain('review.review')
        ->and($strip)->toContain('review.created_at');

    expect($card)->toContain("route('instructors.show', instructor.id)")
        ->and($card)->toContain('instructor.designation')
        ->and($card)->toContain('instructor.biography')
        ->and($card)->toContain('designation ? `${name}, ${designation}` : name');

    expect($reviewsTab)->toContain('StudentFeedback')
        ->and($reviewsTab)->toContain('TableFooter');

    expect($panel)->not->toContain('ssu-enrollment-panel')
        ->and($panel)->not->toContain('ssu-kicker')
        ->and($panel)->not->toContain('enrollmentNote')
        ->and($panel)->toContain('<EnrollOrPlayerButton />');

    expect($preview)->not->toContain('Reserve your seat by paying the deposit')
        ->and($preview)->not->toContain('Pay the full upfront price plus the first')
        ->and($preview)->toContain('Reserve your seat for')
        ->and($preview)->toContain('one month of')
        ->and($preview)->toContain('5-day grace period')
        ->and($preview)->toContain('Pay the full price of')
        ->and($preview)->toContain('with coupon')
        ->and($preview)->toContain('course.subscription_price')
        ->and($preview)->toContain("course.pricing_type === 'free'")
        ->and(strpos($preview, 'aspect-video'))->toBeLessThan(strpos($preview, 'course.pricing_type'))
        ->and(strpos($preview, 'Reserve your seat for'))->toBeLessThan(strpos($preview, '<EnrollOrPlayerButton />'))
        ->and(strpos($preview, '<EnrollOrPlayerButton />'))->toBeLessThan(strpos($preview, 'frontend.students'));

    expect(file_exists(resource_path('js/pages/courses/partials/instructor.tsx')))->toBeTrue();
});

it('keeps the course checkout, enroll, and wishlist actions on their current handlers', function () {
    $player = file_get_contents(resource_path('js/pages/courses/partials/course-player-button.tsx'));
    $checkout = file_get_contents(resource_path('js/components/checkout-item.tsx'));
    $checkoutButton = file_get_contents(resource_path('js/components/ssu-checkout-button.tsx'));

    expect($checkout)->toContain("route('payments.index', { from, item, id: item_id })")
        ->and($checkout)->toContain("route('login')")
        ->and($checkout)->toContain('encodeURIComponent(checkoutUrl)');

    expect($checkoutButton)->toContain('<CheckoutItem item={item} item_id={item_id}>')
        ->and($checkoutButton)->toContain('ssu-checkout-button');

    expect($player)->toContain("route('enrollments.store')")
        ->and($player)->toContain("enrollment_type: 'free'")
        ->and($player)->toContain("route('course-wishlists.store'")
        ->and($player)->toContain("route('course-wishlists.destroy'")
        ->and($player)->toContain('Pre-register — $')
        ->and($player)->toContain('Pay full upfront price')
        ->and($player)->toContain('Pay remaining $')
        ->and($player)->toContain('Balance opens')
        ->and($player)->toContain("'Resubscribe'")
        ->and($player)->toContain("'Enroll now'")
        ->and($player)->toContain("'Subscribe now'")
        ->and($player)->toContain('frontend.buy_now')
        ->and($player)->toContain('frontend.enroll_now')
        ->and($player)->toContain('frontend.play_course')
        ->and($player)->toContain('Coming Soon')
        ->and($player)->toContain('Preview Course')
        ->and($player)->toContain('<SsuCheckoutButton item="course" item_id={course.id}>');
});
