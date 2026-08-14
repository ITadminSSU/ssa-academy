import { isCourseComingSoon } from '@/lib/course-launch';

type EnrollmentCourse = Pick<Course, 'id' | 'slug' | 'status' | 'launch_at' | 'is_coming_soon'>;

type AccessibleEnrollment = Pick<CourseEnrollment, 'access_status'> & {
   course?: EnrollmentCourse | null;
};

/**
 * Reserved seats and coming-soon courses must not open the player.
 * Send learners to the public course details page instead.
 */
export function enrollmentBlocksPlayerAccess(enrollment: AccessibleEnrollment): boolean {
   const status = enrollment.access_status;

   if (status === 'reserved' || status === 'canceled') {
      return true;
   }

   if (enrollment.course && isCourseComingSoon(enrollment.course)) {
      return true;
   }

   return false;
}

export function enrollmentCourseDetailsUrl(enrollment: AccessibleEnrollment): string | null {
   const course = enrollment.course;

   if (!course?.id || !course.slug) {
      return null;
   }

   return route('course.details', { slug: course.slug, id: course.id });
}
