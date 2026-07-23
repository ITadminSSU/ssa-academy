type BillableCourse = Pick<Course, 'pricing_type' | 'billing_model'>;

export function isSubscriptionCourse(course: BillableCourse): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'subscription';
}
