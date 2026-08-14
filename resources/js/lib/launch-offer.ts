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

/** Build launch-offer view from course fields (and optional server payload). */
export const getLaunchOfferView = (
   course: Course,
   serverPayload?: Record<string, unknown> | null,
   enrollment?: { access_status?: string; balance_paid_at?: string | null } | null,
): LaunchOfferView => {
   if (serverPayload && typeof serverPayload.enabled === 'boolean') {
      return {
         enabled: Boolean(serverPayload.enabled),
         phase: (serverPayload.phase as LaunchOfferPhase) || 'none',
         listPrice: toNumber(serverPayload.list_price, 75),
         offerPrice: toNumber(serverPayload.offer_price, 70),
         depositAmount: toNumber(serverPayload.deposit_amount, 20),
         balanceAmount: toNumber(serverPayload.balance_amount, 50),
         fullUpfrontPrice: toNumber(serverPayload.full_upfront_price, 75),
         subscriptionPrice: toNumber(serverPayload.subscription_price, course.subscription_price ?? 6),
         canPreRegister: Boolean(serverPayload.can_pre_register),
         canPayBalance: Boolean(serverPayload.can_pay_balance),
         canFullEnroll: Boolean(serverPayload.can_full_enroll),
         reservedSeat: Boolean(serverPayload.reserved_seat),
         depositNonRefundable: Boolean(serverPayload.deposit_non_refundable ?? true),
      };
   }

   if (!course.launch_offer_enabled) {
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

   const now = Date.now();
   const start = parseDate(course.launch_offer_starts_at)?.getTime() ?? 0;
   const end = parseDate(course.launch_offer_ends_at)?.getTime() ?? 0;
   const phase: LaunchOfferPhase =
      now >= start && now <= end ? 'pre_register' : now > end ? 'full_price' : 'scheduled';

   const reservedSeat = enrollment?.access_status === 'reserved' && !enrollment?.balance_paid_at;

   return {
      enabled: true,
      phase,
      listPrice: toNumber(course.launch_list_price, 75),
      offerPrice: toNumber(course.launch_offer_price, 70),
      depositAmount: toNumber(course.launch_deposit_amount, 20),
      balanceAmount: toNumber(course.launch_balance_amount, 50),
      fullUpfrontPrice: toNumber(course.launch_full_upfront_price, 75),
      subscriptionPrice: toNumber(course.subscription_price ?? course.launch_offer_subscription_price, 6),
      canPreRegister: phase === 'pre_register' && !reservedSeat,
      canPayBalance: Boolean(reservedSeat),
      canFullEnroll: phase === 'full_price' && !reservedSeat,
      reservedSeat,
      depositNonRefundable: true,
   };
};
