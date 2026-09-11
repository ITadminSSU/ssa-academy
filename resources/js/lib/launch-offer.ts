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

   balanceDueAt?: string | null;

   balanceDeadlineAt?: string | null;

   catalogPromo: CatalogPromo | null;

}



export type CatalogPromoKind = 'pre_register' | 'one_time' | 'upfront';

export interface CatalogPromo {

   advertised: boolean;

   kind?: CatalogPromoKind;

   list_price: number;

   deposit_amount: number;

   balance_amount: number;

   balance_with_coupon: number;

   total_with_coupon: number;

   full_upfront_price: number;

   full_upfront_with_coupon: number;

   subscription_price: number;

   window_end?: string | null;

}



const toNumber = (value: unknown, fallback = 0): number => {

   const n = Number(value);

   return Number.isFinite(n) ? n : fallback;

};



const resolveCatalogPromo = (course: Course, serverPayload?: Record<string, unknown> | null): CatalogPromo | null => {

   const raw = (serverPayload?.catalog_promo as CatalogPromo | null | undefined) ?? course.catalog_promo ?? null;

   if (!raw || raw.advertised === false) {

      return null;

   }

   const kind: CatalogPromoKind = raw.kind ?? 'pre_register';

   if (kind === 'one_time' || kind === 'upfront') {

      if (toNumber(raw.total_with_coupon) >= toNumber(raw.list_price) - 0.009) {

         return null;

      }

   } else if (toNumber(raw.balance_with_coupon, toNumber(raw.balance_amount)) >= toNumber(raw.balance_amount) - 0.009) {

      return null;

   }

   return {

      advertised: true,

      kind,

      list_price: toNumber(raw.list_price),

      deposit_amount: toNumber(raw.deposit_amount),

      balance_amount: toNumber(raw.balance_amount),

      balance_with_coupon: toNumber(raw.balance_with_coupon),

      total_with_coupon: toNumber(raw.total_with_coupon),

      full_upfront_price: toNumber(raw.full_upfront_price),

      full_upfront_with_coupon: toNumber(raw.full_upfront_with_coupon),

      subscription_price: toNumber(raw.subscription_price),

      window_end: raw.window_end ?? null,

   };

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



const isBalancePaymentOpen = (dueAt: unknown, deadlineAt: unknown): boolean => {

   const due = parseDate(dueAt)?.getTime();

   const deadline = parseDate(deadlineAt)?.getTime();



   if (due === undefined || deadline === undefined) {

      return false;

   }



   const now = Date.now();



   return now >= due && now <= deadline;

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

   enrollment?: {

      access_status?: string;

      balance_paid_at?: string | null;

      balance_due_at?: string | null;

      balance_deadline_at?: string | null;

   } | null,

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

         catalogPromo: resolveCatalogPromo(course, serverPayload),

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



   const balanceDueAt =
      (course.launch_at as string | undefined) ??
      (serverPayload?.balance_due_at as string | undefined) ??
      (enrollment?.balance_due_at as string | undefined) ??
      null;

   const balanceDeadlineAt =
      (serverPayload?.balance_deadline_at as string | undefined) ??
      (enrollment?.balance_deadline_at as string | undefined) ??
      null;



   const canPayBalance =

      serverPayload && typeof serverPayload.can_pay_balance === 'boolean'

         ? Boolean(serverPayload.can_pay_balance)

         : reservedSeat && isBalancePaymentOpen(balanceDueAt, balanceDeadlineAt);



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

      canPayBalance,

      canFullEnroll: phase === 'full_price' && !reservedSeat && !hasFullAccess,

      reservedSeat,

      depositNonRefundable: Boolean(serverPayload?.deposit_non_refundable ?? true),

      balanceDueAt,

      balanceDeadlineAt,

      catalogPromo: resolveCatalogPromo(course, serverPayload),

   };

};



export const formatLaunchOfferDateTime = (value?: string | null): string | null => {

   if (!value) {

      return null;

   }



   const date = new Date(value);



   if (Number.isNaN(date.getTime())) {

      return null;

   }



   return date.toLocaleString(undefined, {

      month: 'short',

      day: 'numeric',

      year: 'numeric',

      hour: 'numeric',

      minute: '2-digit',

   });

};



export const formatCatalogPromoDate = (value?: string | null): string | null => {

   if (!value) {

      return null;

   }

   const date = new Date(value);

   if (Number.isNaN(date.getTime())) {

      return null;

   }

   return date.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });

};



export const formatOfferAmount = (amount: number): string =>

   Number.isInteger(amount) ? String(amount) : amount.toFixed(2);



export const isPricedCatalogPromo = (promo: CatalogPromo | null | undefined): promo is CatalogPromo =>

   promo?.kind === 'one_time' || promo?.kind === 'upfront';



export const formatCatalogPromoDeadline = (value?: string | null): string | null => {

   const label = formatCatalogPromoDate(value);

   return label ? `until ${label} only` : null;

};


