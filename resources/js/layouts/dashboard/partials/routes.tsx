import { routeLastSegment } from '@/lib/route';
import { Award, Book, Briefcase, CassetteTape, CreditCard, Folder, LayoutDashboard, LifeBuoy, Newspaper, School, Settings, UserCheck, Users } from 'lucide-react';

function buildDashboardRouteTemplate(): DashboardRoute[] {
   return [
   {
      title: 'Main Menu',
      slug: 'main-menu',
      pages: [
         {
            Icon: LayoutDashboard,
            name: 'Dashboard',
            path: '/dashboard',
            slug: 'dashboard',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [],
         },
         {
            Icon: School,
            name: 'Courses',
            path: '',
            slug: 'courses',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Categories',
                  path: route('categories.index'),
                  slug: routeLastSegment(route('categories.index')),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Manage Courses',
                  slug: routeLastSegment(route('courses.index')),
                  path: route('courses.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Create Course',
                  slug: routeLastSegment(route('courses.create')),
                  path: route('courses.create'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Course Coupons',
                  slug: routeLastSegment(route('course-coupons.index')),
                  path: route('course-coupons.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Tracking Dashboard',
                  slug: routeLastSegment(route('student-progress.index')),
                  path: route('student-progress.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Top Performers',
                  slug: routeLastSegment(route('top-performers.index')),
                  path: route('top-performers.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Verify Certificate',
                  slug: routeLastSegment(route('certificate.verify')),
                  path: route('certificate.verify'),
                  access: ['admin', 'instructor'],
               },
            ],
         },
         {
            Icon: Book,
            name: 'Exams',
            path: '',
            slug: 'exams',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Manage Exams',
                  slug: routeLastSegment(route('exams.index')),
                  path: route('exams.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Create Exam',
                  slug: routeLastSegment(route('exams.create')),
                  path: route('exams.create'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Exam Leaderboard',
                  slug: routeLastSegment(route('exams.leaderboard')),
                  path: route('exams.leaderboard'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
            ],
         },
         {
            Icon: CassetteTape,
            name: 'Enrollments',
            path: '',
            slug: 'enrollments',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Course Enrollments',
                  slug: routeLastSegment(route('course-enrollments.index')),
                  path: route('course-enrollments.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Exam Enrollments',
                  slug: routeLastSegment(route('exam-enrollments.index')),
                  path: route('exam-enrollments.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
            ],
         },
         {
            Icon: Users,
            name: 'Instructors',
            path: '',
            slug: 'instructors',
            active: true,
            access: ['admin', 'collaborative'],
            children: [
               {
                  name: 'Manage Instructors',
                  slug: routeLastSegment(route('instructors.index')),
                  path: route('instructors.index'),
                  access: ['admin', 'collaborative'],
               },
               {
                  name: 'Create Instructor',
                  slug: routeLastSegment(route('instructors.create')),
                  path: route('instructors.create'),
                  access: ['admin', 'collaborative'],
               },
               {
                  name: 'Applications',
                  slug: routeLastSegment(route('instructors.applications')),
                  path: route('instructors.applications', {
                     status: 'pending',
                  }),
                  access: ['admin', 'collaborative'],
               },
            ],
         },
         {
            Icon: CreditCard,
            name: 'Payment Report',
            path: '',
            slug: 'payment-reports',
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Online Payments',
                  slug: routeLastSegment(route('payment-reports.online.index')),
                  path: route('payment-reports.online.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Refund Tracking',
                  slug: routeLastSegment(route('payment-refunds.index')),
                  path: route('payment-refunds.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Trainer Metrics',
                  slug: routeLastSegment(route('admin.trainer-metrics.index')),
                  path: route('admin.trainer-metrics.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               // {
               //    name: 'Offline Payments',
               //    slug: routeLastSegment(route('payment-reports.offline.index')),
               //    path: route('payment-reports.offline.index'),
               //    access: ['admin', 'collaborative', 'administrative'],
               // },
            ],
         },
         {
            Icon: Briefcase,
            name: 'Job Circulars',
            path: '',
            slug: 'job-circulars',
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'All Jobs',
                  slug: routeLastSegment(route('job-circulars.index')),
                  path: route('job-circulars.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Create Job',
                  slug: routeLastSegment(route('job-circulars.create')),
                  path: route('job-circulars.create'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
            ],
         },
         // {
         //    Icon: Book,
         //    name: 'Blogs',
         //    path: '',
         //    slug: 'blogs',
         //    active: true,
         //    access: ['admin', 'instructor', 'collaborative', 'administrative'],
         //    children: [
         //       {
         //          name: 'Categories',
         //          slug: routeLastSegment(route('blogs.categories.index')),
         //          path: route('blogs.categories.index'),
         //          access: ['admin', 'instructor', 'collaborative', 'administrative'],
         //       },
         //       {
         //          name: 'Create Blog',
         //          slug: routeLastSegment(route('blogs.create')),
         //          path: route('blogs.create'),
         //          access: ['admin', 'instructor', 'collaborative', 'administrative'],
         //       },
         //       {
         //          name: 'Manage Blog',
         //          slug: routeLastSegment(route('blogs.index')),
         //          path: route('blogs.index'),
         //          access: ['admin', 'instructor', 'collaborative', 'administrative'],
         //       },
         //    ],
         // },
         {
            Icon: Newspaper,
            name: 'Newsletters',
            path: route('newsletters.index'),
            slug: routeLastSegment(route('newsletters.index')),
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [],
         },
         {
            Icon: Users,
            name: 'Users List',
            path: route('users.index'),
            slug: routeLastSegment(route('users.index')),
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [],
         },
         {
            Icon: UserCheck,
            name: 'Candidate Pipeline',
            path: route('candidates.index'),
            slug: routeLastSegment(route('candidates.index')),
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [],
         },
         {
            Icon: Award,
            name: 'Certificates',
            path: '',
            slug: 'certification',
            active: true,
            access: ['admin', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Certificate',
                  slug: routeLastSegment(route('certificate.templates.index')),
                  path: route('certificate.templates.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Marksheet',
                  slug: routeLastSegment(route('marksheet.templates.index')),
                  path: route('marksheet.templates.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
            ],
         },
         {
            Icon: Folder,
            name: 'Learner Hub',
            path: '',
            slug: 'learner-hub',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Announcements',
                  slug: routeLastSegment(route('announcements.index')),
                  path: route('announcements.index'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Forum Questions',
                  slug: 'forum-questions',
                  path: route('trainer.forum-questions.index'),
                  access: ['instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'Forum Questions',
                  slug: 'forum-questions',
                  path: route('admin.forum-questions.index'),
                  access: ['admin'],
               },
            ],
         },
         {
            Icon: LifeBuoy,
            name: 'Help Center',
            path: route('help-center.index'),
            slug: routeLastSegment(route('help-center.index')),
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [],
         },
         {
            Icon: Settings,
            name: 'Settings',
            path: '',
            slug: 'settings',
            active: true,
            access: ['admin', 'instructor', 'collaborative', 'administrative'],
            children: [
               {
                  name: 'Account',
                  slug: routeLastSegment(route('settings.account')),
                  path: route('settings.account'),
                  access: ['admin', 'instructor', 'collaborative', 'administrative'],
               },
               {
                  name: 'System',
                  slug: routeLastSegment(route('settings.system')),
                  path: route('settings.system'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Platform Tools',
                  slug: routeLastSegment(route('settings.platform-tools')),
                  path: route('settings.platform-tools'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Pages',
                  slug: routeLastSegment(route('settings.pages')),
                  path: route('settings.pages'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Storage',
                  slug: routeLastSegment(route('settings.storage')),
                  path: route('settings.storage'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Bunny Stream',
                  slug: routeLastSegment(route('settings.bunny-stream')),
                  path: route('settings.bunny-stream'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Payment',
                  slug: routeLastSegment(route('settings.payment')),
                  path: route('settings.payment'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'SMTP',
                  slug: routeLastSegment(route('settings.smtp')),
                  path: route('settings.smtp'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Auth',
                  slug: routeLastSegment(route('settings.auth0')),
                  path: route('settings.auth0'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               // {
               //    name: 'Live Class',
               //    slug: routeLastSegment(route('settings.live-class')),
               //    path: route('settings.live-class'),
               //    access: ['admin', 'collaborative', 'administrative'],
               // },
               {
                  name: 'Translation',
                  slug: routeLastSegment(route('language.index')),
                  path: route('language.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
               {
                  name: 'Professional Types',
                  slug: routeLastSegment(route('professional-types.index')),
                  path: route('professional-types.index'),
                  access: ['admin', 'collaborative', 'administrative'],
               },
            ],
         },
      ],
   },
   ];
}

const FEATURE_GATED_SLUGS: Partial<Record<string, keyof PlatformFeatures>> = {
   'job-circulars': 'job_circulars',
   newsletters: 'newsletters',
};

function cloneDashboardRoutes(template: DashboardRoute[]): DashboardRoute[] {
   return template.map((section) => ({
      ...section,
      pages: section.pages.map((page) => ({
         ...page,
         children: page.children.map((child) => ({ ...child })),
      })),
   }));
}

export function getDashboardRoutes(dashboardUrl: string, features?: PlatformFeatures): DashboardRoute[] {
   const routes = cloneDashboardRoutes(buildDashboardRouteTemplate());
   const dashboardSlug = routeLastSegment(dashboardUrl);

   routes[0].pages[0] = {
      ...routes[0].pages[0],
      path: dashboardUrl,
      slug: dashboardSlug,
   };

   if (features) {
      routes[0].pages = routes[0].pages.filter((page) => {
         const featureKey = FEATURE_GATED_SLUGS[page.slug];

         return !featureKey || features[featureKey];
      });
   }

   if (dashboardSlug === 'admin') {
      routes[0].pages = routes[0].pages.map((page) => {
         if (page.slug !== 'courses') {
            return page;
         }

         return {
            ...page,
            children: page.children.map((child) => {
               if (child.name === 'Tracking Dashboard') {
                  return {
                     ...child,
                     slug: routeLastSegment(route('admin.student-progress.index')),
                     path: route('admin.student-progress.index'),
                  };
               }

               if (child.name === 'Top Performers') {
                  return {
                     ...child,
                     slug: routeLastSegment(route('admin.top-performers.index')),
                     path: route('admin.top-performers.index'),
                  };
               }

               return child;
            }),
         };
      });
   }

   return routes;
}
