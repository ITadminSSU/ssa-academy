type SubscriptionStatus = 'trialing' | 'active' | 'past_due' | 'canceled' | 'unpaid' | 'paused';

type EnrollmentAccessStatus = 'active' | 'suspended' | 'expired';

type SubscriptionAccessMode = 'full' | 'completed_only' | 'none';

interface SubscriptionAccess {
   mode: SubscriptionAccessMode;
   can_mark_progress: boolean;
   can_submit_assignments: boolean;
   can_finish_course: boolean;
   can_resubscribe: boolean;
   is_subscription_course: boolean;
   access_status?: EnrollmentAccessStatus | null;
   subscription_status?: SubscriptionStatus | null;
}

interface Subscription extends TableCommon {
   user_id: number;
   course_id: number;
   stripe_customer_id: string;
   stripe_subscription_id: string;
   stripe_price_id: string;
   status: SubscriptionStatus;
   current_period_start?: string | null;
   current_period_end?: string | null;
   cancel_at_period_end?: boolean;
   canceled_at?: string | null;
   grace_ends_at?: string | null;
   course?: Course;
}

interface UserSubscriptionSummary {
   id: number;
   status: SubscriptionStatus;
   current_period_start?: string | null;
   current_period_end?: string | null;
   cancel_at_period_end?: boolean;
   canceled_at?: string | null;
   grace_ends_at?: string | null;
   grants_full_access: boolean;
   access_status?: EnrollmentAccessStatus | null;
   course?: Course;
}
