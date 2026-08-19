export type LaunchOfferPhase = 'none' | 'scheduled' | 'pre_register' | 'full_price';

export interface LaunchOfferView {
   enabled: boolean;
   phase: LaunchOfferPhase;
   listPrice: number;
   offerPrice: number;
   depositAmount: number;
   balanceAmount: number;
   fullUpfrontPrice: number;
   subscriptionPrice: number;
   canPreRegister: boolean;
   canPayBalance: boolean;
   canFullEnroll: boolean;
   reservedSeat: boolean;
   depositNonRefundable: boolean;
}

const toNumber = (value: unknown, fallback = 0): number => {
   const n = Number(value);
   return Number.isFinite(n) ? n : fallback;
};

const parseDate = (value: unknown): Date | null => {
   if (!value) {
      return null;
   }

   const date = new Date(String(value));
   return Number.isNaN(date.getTime()) ? null : date;
};

const resolveLaunchOfferPhase = (startsAt: unknown, endsAt: unknown): LaunchOfferPhase => {
   const start = parseDate(startsAt)?.getTime();
   const end = parseDate(endsAt)?.getTime();

   if (start === undefined || end === undefined) {
      return 'none';
   }

   const now = Date.now();

   if (now >= start && now <= end) {
      return 'pre_register';
   }

   if (now > end) {
      return 'full_price';
   }

   return 'scheduled';
};

const enrollmentHasFullAccess = (
   enrollment?: { access_status?: string; balance_paid_at?: string | null } | null,
): boolean => {
   if (!enrollment) {
      return false;
   }

   const status = enrollment.access_status;

   return status !== undefined && status !== 'reserved' && status !== 'canceled';
};

/** Build launch-offer view from course fields (and optional server payload). */
export const getLaunchOfferView = (
   course: Course,
   serverPayload?: Record<string, unknown> | null,
   enrollment?: { access_status?: string; balance_paid_at?: string | null } | null,
): LaunchOfferView => {
   const isPreRegisterModel = course.billing_model === 'pre_register_subscription';
   const enabled =
      serverPayload && typeof serverPayload.enabled === 'boolean'
         ? Boolean(serverPayload.enabled)
         : Boolean(course.launch_offer_enabled) || isPreRegisterModel;

   if (!enabled) {
      return {
         enabled: false,
         phase: 'none',
         listPrice: 75,
         offerPrice: 70,
         depositAmount: 20,
         balanceAmount: 50,
         fullUpfrontPrice: 75,
         subscriptionPrice: toNumber(course.subscription_price, 6),
         canPreRegister: false,
         canPayBalance: false,
         canFullEnroll: false,
         reservedSeat: false,
         depositNonRefundable: true,
      };
   }

   const startsAt = course.launch_offer_starts_at ?? serverPayload?.window_start;
   const endsAt = course.launch_offer_ends_at ?? serverPayload?.window_end;
   const phase = resolveLaunchOfferPhase(startsAt, endsAt);

   const reservedSeat =
      serverPayload && typeof serverPayload.reserved_seat === 'boolean'
         ? Boolean(serverPayload.reserved_seat)
         : enrollment?.access_status === 'reserved' && !enrollment?.balance_paid_at;

   const hasFullAccess = enrollmentHasFullAccess(enrollment);

   const listPrice =
      serverPayload && serverPayload.list_price !== undefined
         ? toNumber(serverPayload.list_price, 75)
         : toNumber(course.launch_list_price, 75);
   const offerPrice =
      serverPayload && serverPayload.offer_price !== undefined
         ? toNumber(serverPayload.offer_price, 70)
         : toNumber(course.launch_offer_price, 70);
   const depositAmount =
      serverPayload && serverPayload.deposit_amount !== undefined
         ? toNumber(serverPayload.deposit_amount, 20)
         : toNumber(course.launch_deposit_amount, 20);
   const balanceAmount =
      serverPayload && serverPayload.balance_amount !== undefined
         ? toNumber(serverPayload.balance_amount, 50)
         : toNumber(course.launch_balance_amount, 50);
   const fullUpfrontPrice =
      serverPayload && serverPayload.full_upfront_price !== undefined
         ? toNumber(serverPayload.full_upfront_price, 75)
         : toNumber(course.launch_full_upfront_price, 75);
   const subscriptionPrice =
      serverPayload && serverPayload.subscription_price !== undefined
         ? toNumber(serverPayload.subscription_price, course.subscription_price ?? 6)
         : toNumber(course.subscription_price ?? course.launch_offer_subscription_price, 6);

   return {
      enabled: true,
      phase,
      listPrice,
      offerPrice,
      depositAmount,
      balanceAmount,
      fullUpfrontPrice,
      subscriptionPrice,
      canPreRegister: phase === 'pre_register' && !reservedSeat && !hasFullAccess,
      canPayBalance:
         serverPayload && typeof serverPayload.can_pay_balance === 'boolean'
            ? Boolean(serverPayload.can_pay_balance)
            : Boolean(reservedSeat),
      canFullEnroll: phase === 'full_price' && !reservedSeat && !hasFullAccess,
      reservedSeat,
      depositNonRefundable: Boolean(serverPayload?.deposit_non_refundable ?? true),
   };
};
