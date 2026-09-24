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
                'title' => 'SMARTSOURCING USA ACADEMY',
                'sub_title' => 'Upskill. Certify your skills. Scale with confidence.',
                'description' => 'Structured learning paths for professionals with video lessons, practical assessments, U.S. industry experience, and verified SSA certificates.',
                'thumbnail' => '/assets/images/ssu-about/about-hero.png',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                    'description' => true,
                    'thumbnail' => true,
                    'video_url' => true,
                ],
                'properties' => [
                    'button_text' => 'Browse Courses',
                    'button_link' => '/courses/all',
                    'secondary_button_text' => 'Sign In',
                    'secondary_button_link' => '/login',
                ],
            ],
            [
                'name' => 'The Gap',
                'slug' => 'gap',
                'title' => 'THE GAP IS WIDENING',
                'sub_title' => 'EVERYONE HAS A TALENT.',
                'description' => 'Global talent is everywhere, but US-specific market expertise is rare. Workflows, compliance, and tools have evolved rapidly. Most companies are trying to bridge this gap with traditional outsourcing that lacks local readiness.',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                    'description' => true,
                ],
                'properties' => [
                    'accent_line' => 'VERY FEW HAS U.S. EXPERIENCE.',
                    'banner_name' => 'SMARTSOURCING USA ACADEMY',
                    'banner_action' => 'CLOSES THAT GAP.',
                    'closing_lead' => '100% focused on U.S. construction readiness.',
                    'closing_body' => 'Built by industry veterans who spent decades inside U.S. job sites and project management, training global professionals to be plug-and-play on day one.',
                ],
            ],
            [
                'name' => 'Value Pillars',
                'slug' => 'pillars',
                'title' => 'WHY SMARTSOURCING USA ACADEMY?',
                'sub_title' => 'We help you build skills that matter in the real world.',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                ],
                'properties' => [
                    'array' => [
                        ['icon' => '', 'title' => '', 'description' => ''],
                        [
                            'icon' => 'book-open',
                            'title' => 'Structured Learning Paths',
                            'description' => 'Step-by-step courses with video lessons and quizzes — designed to build skills you can apply on the job.',
                        ],
                        [
                            'icon' => 'clock',
                            'title' => 'Learn at Your Pace',
                            'description' => 'Access training anytime, track your progress, and pick up exactly where you left off — on desktop or mobile.',
                        ],
                        [
                            'icon' => 'badge-check',
                            'title' => 'Verified Certification',
                            'description' => 'Complete every lesson and quiz to earn SSA-verified credentials with unique reference numbers.',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Inside the Academy',
                'slug' => 'inside_academy',
                'title' => 'INSIDE THE ACADEMY',
                'sub_title' => 'Explore the Learning, Stories, and Opportunities Within',
                'flags' => [
                    'title' => true,
                    'sub_title' => true,
                ],
                'properties' => [
                    'array' => [
                        ['image' => '', 'link' => '', 'views' => ''],
                        ['image' => '', 'link' => '', 'views' => ''],
                        ['image' => '', 'link' => '', 'views' => ''],
                        ['image' => '', 'link' => '', 'views' => ''],
                        ['image' => '', 'link' => '', 'views' => ''],
                    ],
                ],
            ],
            [
                'name' => 'Featured Courses',
                'slug' => 'top_courses',
                'title' => 'Featured Programs',
                'sub_title' => 'Start learning today',
                'description' => 'Explore assigned and open-enrollment courses curated for SMARTSOURCING USA ACADEMY teams and partners.',
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
                'sub_title' => 'Join SMARTSOURCING USA ACADEMY Today',
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
