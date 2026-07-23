<?php

namespace Database\Data\Sections;

class SsuHomeSections
{
    /**
     * Sections for the single SSU Academy public landing page.
     */
    public static function getSections(): array
    {
        $sections = [
            [
                'name' => 'Hero',
                'slug' => 'hero',
                'title' => 'SMART SOURCING ACADEMY',
                'sub_title' => 'Upskill. Certify your skills. Scale with confidence.',
                'description' => 'Structured learning paths for professionals — video lessons, assignments, quizzes, and verified SSU certificates.',
                'thumbnail' => null,
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                    'description' => true,
                ],
                'properties' => [
                    'button_text' => 'Browse Courses',
                    'button_link' => '/courses/all',
                    'secondary_button_text' => 'Sign In',
                    'secondary_button_link' => '/login',
                ],
            ],
            [
                'name' => 'Value Pillars',
                'slug' => 'pillars',
                'title' => 'Why SMART SOURCING ACADEMY',
                'flags' => [
                    'title' => true,
                ],
                'properties' => [
                    'array' => [
                        ['icon' => '', 'title' => '', 'description' => ''],
                        [
                            'icon' => 'book-open',
                            'title' => 'Structured Learning Paths',
                            'description' => 'Step-by-step courses with video lessons, assignments, and quizzes — designed to build skills you can apply on the job.',
                        ],
                        [
                            'icon' => 'clock',
                            'title' => 'Learn at Your Pace',
                            'description' => 'Access training anytime, track your progress, and pick up exactly where you left off — on desktop or mobile.',
                        ],
                        [
                            'icon' => 'badge-check',
                            'title' => 'Verified Certification',
                            'description' => 'Complete every lesson, assignment, and quiz to earn SSU-verified credentials with unique reference numbers.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Featured Courses',
                'slug' => 'top_courses',
                'title' => 'Featured Programs',
                'sub_title' => 'Start learning today',
                'description' => 'Explore assigned and open-enrollment courses curated for Smart Sourcing Academy teams and partners.',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                    'description' => true,
                ],
                'properties' => [
                    'contents' => [1, 2, 3, 4, 5, 6],
                ],
            ],
            [
                'name' => 'Call to Action',
                'slug' => 'call_to_action',
                'title' => 'Ready to start learning?',
                'sub_title' => 'Join Smart Sourcing Academy Today',
                'description' => 'Create your free account, explore the catalog, and start your next course today.',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                    'description' => true,
                ],
                'properties' => [
                    'button_text' => 'Get Started',
                    'button_link' => '/register',
                ],
            ],
        ];

        foreach ($sections as $key => $section) {
            $sections[$key]['active'] = true;
            $sections[$key]['sort'] = $key + 1;
        }

        return $sections;
    }
}
