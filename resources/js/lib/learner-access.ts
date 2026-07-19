export type LearnerUserType = 'employee' | 'external';
export type CourseAudience = 'internal' | 'public' | 'both';

export function isEmployeeLearner(user?: { user_type?: LearnerUserType | null } | null): boolean {
   return user?.user_type === 'employee';
}

export function canAccessCourse(user: { user_type?: LearnerUserType | null; role?: string } | null | undefined, course: { audience?: CourseAudience }): boolean {
   if (course.audience !== 'internal') {
      return true;
   }

   return isEmployeeLearner(user) || user?.role === 'admin' || user?.role === 'instructor';
}

export function requiresCoursePayment(
   user: { user_type?: LearnerUserType | null; role?: string } | null | undefined,
   course: { pricing_type: string; audience?: CourseAudience },
): boolean {
   if (!canAccessCourse(user, course)) {
      return false;
   }

   return course.pricing_type === 'paid' && !isEmployeeLearner(user);
}

export function canEnrollCourseWithoutPayment(
   user: { user_type?: LearnerUserType | null; role?: string } | null | undefined,
   course: { pricing_type: string; audience?: CourseAudience },
): boolean {
   if (!canAccessCourse(user, course)) {
      return false;
   }

   return course.pricing_type === 'free' || isEmployeeLearner(user);
}
