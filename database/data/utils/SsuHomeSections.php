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
                'title' => 'SMART SOURCING USA ACADEMY',
                'sub_title' => 'Train your team. Certify your skills. Scale with confidence.',
                'description' => 'Structured learning paths for internal teams and external professionals — video lessons, assignments, quizzes, and verified SSU certificates.',
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
                'title' => 'Why SSU Academy',
                'flags' => [
                    'title' => true,
                ],
                'properties' => [
                    'array' => [
                        ['icon' => '', 'title' => '', 'description' => ''],
                        [
                            'icon' => 'users',
                            'title' => 'Internal Training',
                            'description' => 'Role-based paths for your organization with trainer oversight and real-time progress tracking.',
                        ],
                        [
                            'icon' => 'globe',
                            'title' => 'External Programs',
                            'description' => 'Professional development courses for individuals and partner teams outside your organization.',
                        ],
                        [
                            'icon' => 'badge-check',
                            'title' => 'Verified Certification',
                            'description' => 'Complete video, assignment, and quiz gates to earn SSU-verified credentials with reference numbers.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Featured Courses',
                'slug' => 'top_courses',
                'title' => 'Featured Programs',
                'sub_title' => 'Start learning today',
                'description' => 'Explore assigned and open-enrollment courses curated for Smart Sourcing USA teams and partners.',
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
                'sub_title' => 'Join SSU Academy today',
                'description' => 'Create your account, accept our terms, and begin your assigned learning path.',
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
