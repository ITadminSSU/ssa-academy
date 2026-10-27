type BillableCourse = Pick<Course, 'pricing_type' | 'billing_model' | 'installment_price' | 'installment_months' | 'subscription_price'>;

const installmentAmount = (value: unknown): string | null => {
   const amount = Number(value);

   if (!Number.isFinite(amount) || amount < 1) {
      return null;
   }

   const rounded = Math.round(amount * 100) / 100;

   return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2);
};

export function isInstallmentSubscriptionCourse(course: Pick<Course, 'pricing_type' | 'billing_model'>): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'installment_subscription';
}

export function installmentPlanLabel(course: BillableCourse, symbol = '$'): string | null {
   if (!isInstallmentSubscriptionCourse(course)) {
      return null;
   }

   const installment = installmentAmount(course.installment_price);
   const ongoing = installmentAmount(course.subscription_price);
   const months = Number(course.installment_months);

   if (!installment || !ongoing || !Number.isInteger(months) || months < 1) {
      return null;
   }

   const monthWord = months === 1 ? 'month' : 'months';

   return `${symbol}${installment}/mo for ${months} ${monthWord}, then ${symbol}${ongoing}/mo`;
}

export function isSubscriptionCourse(course: BillableCourse): boolean {
   return (
      course.pricing_type === 'paid' &&
      (course.billing_model === 'subscription' ||
         course.billing_model === 'upfront_subscription' ||
         course.billing_model === 'pre_register_subscription')
   );
}

export function isUpfrontSubscriptionCourse(course: BillableCourse): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'upfront_subscription';
}

export function isMonthlyOnlySubscriptionCourse(course: BillableCourse): boolean {
   return course.pricing_type === 'paid' && course.billing_model === 'subscription';
}
