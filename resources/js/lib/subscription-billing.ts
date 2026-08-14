type BillableCourse = Pick<Course, 'pricing_type' | 'billing_model'>;

export function isSubscriptionCourse(course: BillableCourse): boolean {
   return (
      course.pricing_type === 'paid' &&
      (course.billing_model === 'subscription' || course.billing_model === 'upfront_subscription')
   );
}

export function isUpfrontSubscriptionCourse(course: BillableCourse): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'upfront_subscription';
}

export function isMonthlyOnlySubscriptionCourse(course: BillableCourse): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'subscription';
}
