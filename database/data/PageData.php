<?php

namespace Database\Data;

use App\Enums\TeachingType;
use Database\Data\Sections\InnerSections;
use Database\Data\Sections\SsuHomeSections;

class PageData
{
   /**
    * Get all pages data for seeding
    */
   public static function getAllPages(): array
   {
      return [
         ...self::getHomePages(),
         ...self::getInnerPages(),
      ];
   }

   /**
    * Get home pages data
    */
   public static function getHomePages(): array
   {
      return [
         [
            'name' => 'SSU Academy Landing',
            'slug' => 'ssu-home',
            'type' => TeachingType::COLLABORATIVE->value,
            'title' => 'SMARTSOURCING USA ACADEMY',
            'description' => 'Enterprise training for internal teams and external professionals.',
            'meta_description' => 'SSU Academy — structured learning paths with video, assignments, quizzes, and verified certification.',
            'meta_keywords' => 'SSU Academy, Smart Sourcing USA, training, certification, online courses',
            'sections' => SsuHomeSections::getSections(),
         ],
      ];
   }

   /**
    * Get policy pages data
    */
   public static function getInnerPages(): array
   {
      return [
         // About Us page
         [
            'name' => 'About Us',
            'slug' => 'about-us',
            'type' => 'inner_page',
            'title' => 'About Us - Why Choose SMARTSOURCING USA ACADEMY?',
            'meta_description' => 'SMARTSOURCING USA ACADEMY offers quality content, affordable learning, and continuous improvement in online education.',
            'meta_keywords' => 'about us, mission, vision, quality content, affordable learning, education platform',
            'sections' => InnerSections::getAboutUsSections()
         ],
         // Our Team page
         [
            'name' => 'Our Team',
            'slug' => 'our-team',
            'type' => 'inner_page',
            'title' => 'Our Team - Meet the People Behind SMARTSOURCING USA ACADEMY',
            'meta_description' => 'Meet the SMARTSOURCING USA ACADEMY team - passionate educators, skilled developers, and dedicated professionals working to democratize education.',
            'meta_keywords' => 'our team, about team, SMARTSOURCING USA ACADEMY team, leadership, educators, developers, support staff',
            'sections' => InnerSections::getOurTeamSections()
         ],
         // Careers page
         [
            'name' => 'Careers',
            'slug' => 'careers',
            'type' => 'inner_page',
            'title' => 'Careers - Join Our Mission at SMARTSOURCING USA ACADEMY',
            'meta_description' => 'Join SMARTSOURCING USA ACADEMY team and help transform education. Explore career opportunities, company culture, and growth prospects.',
            'meta_keywords' => 'careers, jobs, employment, SMARTSOURCING USA ACADEMY jobs, education careers, remote work, software engineer, product manager',
            'sections' => []
         ],
         // Contact Us page
         [
            'name' => 'Address',
            'slug' => 'contact-us',
            'type' => 'inner_page',
            'title' => 'Contact Us - Get in Touch with SMARTSOURCING USA ACADEMY',
            'description' => InnerSections::getContactUsDescription(),
            'meta_description' => 'Contact SMARTSOURCING USA ACADEMY for support, partnerships, careers, or general inquiries. Find all our contact information and office details.',
            'meta_keywords' => 'contact us, support, help, contact information, customer service, partnerships, feedback',
            'sections' => []
         ],
         // Cookie Policy page
         [
            'name' => 'Cookie Policy',
            'slug' => 'cookie-policy',
            'type' => 'inner_page',
            'title' => 'Cookie Policy',
            'description' => InnerSections::getCookiePolicyDescription(),
            'meta_description' => 'SMARTSOURCING USA ACADEMY Cookie Policy: Learn about how we use cookies and similar technologies on our platform.',
            'meta_keywords' => 'cookie policy, cookies, privacy, tracking, web cookies, http cookies',
            'sections' => []
         ],
         // Terms and Conditions page
         [
            'name' => 'Terms and Conditions',
            'slug' => 'terms-and-conditions',
            'type' => 'inner_page',
            'title' => 'Terms and Conditions',
            'description' => InnerSections::getTermsAndConditionsDescription(),
            'meta_description' => 'Read SMARTSOURCING USA ACADEMY Terms and Conditions to understand your rights and responsibilities while using our platform.',
            'meta_keywords' => 'terms, conditions, terms of service, legal agreement, user agreement',
            'sections' => []
         ],
         // Non-Disclosure Agreement page
         [
            'name' => 'Non-Disclosure Agreement',
            'slug' => 'non-disclosure-agreement',
            'type' => 'inner_page',
            'title' => 'Non-Disclosure Agreement (NDA)',
            'description' => InnerSections::getNdaDescription(),
            'meta_description' => 'Read the SMARTSOURCING USA ACADEMY Non-Disclosure Agreement governing confidentiality of proprietary training materials.',
            'meta_keywords' => 'nda, non-disclosure agreement, confidentiality, proprietary materials, academy agreement',
            'sections' => []
         ],
         // Privacy Policy page
         [
            'name' => 'Privacy Policy',
            'slug' => 'privacy-policy',
            'type' => 'inner_page',
            'title' => 'Privacy Policy',
            'description' => InnerSections::getPrivacyPolicyDescription(),
            'meta_description' => 'Learn about how SMARTSOURCING USA ACADEMY collects, uses, and protects your personal information in our Privacy Policy.',
            'meta_keywords' => 'privacy policy, data protection, personal information, data collection, data security',
            'sections' => []
         ],
         // Refund Policy page
         [
            'name' => 'Refund Policy',
            'slug' => 'refund-policy',
            'type' => 'inner_page',
            'title' => 'Refund Policy',
            'description' => InnerSections::getRefundPolicyDescription(),
            'meta_description' => 'SMARTSOURCING USA ACADEMY payments are non-refundable. Read our Refund Policy for course fees, subscriptions, and paid services.',
            'meta_keywords' => 'refund policy, non-refundable, course fees, subscription billing, SMARTSOURCING USA ACADEMY',
            'sections' => []
         ],
      ];
   }
}
