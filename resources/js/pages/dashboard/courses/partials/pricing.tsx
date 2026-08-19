import Combobox from '@/components/combobox';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Accordion, AccordionContent, AccordionItem } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Separator } from '@/components/ui/separator';
import courseDurations from '@/data/course-durations';
import DashboardLayout from '@/layouts/dashboard/layout';
import { toDateTimeLocalValue } from '@/lib/course-launch';
import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, RefreshCw } from 'lucide-react';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import { CourseUpdateProps } from '../update';

const asOptionValue = (value: unknown): string => {
   if (typeof value === 'string') {
      return value;
   }

   if (value && typeof value === 'object' && 'value' in value) {
      return String((value as { value: string }).value);
   }

   return String(value ?? '');
};

const billingModelLabel = (value: string) => {
   if (value === 'subscription') {
      return 'Monthly subscription';
   }

   if (value === 'upfront_subscription') {
      return 'Upfront + monthly subscription';
   }

   if (value === 'pre_register_subscription') {
      return 'Pre-registration + monthly subscription';
   }

   return 'One-time purchase';
};

const timezoneHint = (timezone?: string) => {
   if (!timezone) {
      return 'App timezone';
   }

   if (timezone === 'Asia/Manila') {
      return 'Philippine Time (PHT)';
   }

   return timezone.replace(/_/g, ' ');
};

const Pricing = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { translate, appTimezone } = props;
   const { dashboard, input, button } = translate;
   const { prices, expiries, course, billingModels = [], stripeActive, stripeSynced } = props;
   const dateTimezoneLabel = timezoneHint(appTimezone);

   const priceOptions = useMemo(
      () => (Array.isArray(prices) ? prices.map(asOptionValue) : ['free', 'paid']),
      [prices],
   );
   const expiryOptions = useMemo(
      () => (Array.isArray(expiries) ? expiries.map(asOptionValue) : ['lifetime', 'limited_time']),
      [expiries],
   );
   const paidValue = priceOptions.find((value) => value === 'paid') ?? 'paid';
   const limitedValue = expiryOptions.find((value) => value === 'limited_time') ?? 'limited_time';

   const billingOptions = useMemo(() => {
      if (Array.isArray(billingModels) && billingModels.length > 0) {
         if (typeof billingModels[0] === 'string') {
            return (billingModels as string[]).map((value) => ({
               value,
               label: billingModelLabel(value),
            }));
         }

         return (billingModels as { value: string; label?: string }[]).map((option) => ({
            value: asOptionValue(option.value),
            label: option.label ?? billingModelLabel(asOptionValue(option.value)),
         }));
      }

      return [
         { value: 'one_time', label: 'One-time purchase' },
         { value: 'subscription', label: 'Monthly subscription' },
         { value: 'upfront_subscription', label: 'Upfront + monthly subscription' },
         { value: 'pre_register_subscription', label: 'Pre-registration + monthly subscription' },
      ];
   }, [billingModels]);

   const { data, setData, post, errors, processing, transform } = useForm({
      tab: 'pricing',
      pricing_type: asOptionValue(course.pricing_type) || paidValue,
      billing_model:
         course.launch_offer_enabled && asOptionValue(course.billing_model) === 'subscription'
            ? 'pre_register_subscription'
            : asOptionValue(course.billing_model) || 'one_time',
      price: course.price ?? '',
      subscription_price: course.subscription_price ?? '',
      discount: Boolean(course.discount),
      discount_price: course.discount_price ?? '',
      expiry_type: asOptionValue(course.expiry_type) || 'lifetime',
      expiry_duration: course.expiry_duration || '',
      launch_offer_enabled: Boolean(course.launch_offer_enabled) && asOptionValue(course.billing_model) !== 'upfront_subscription',
      launch_offer_starts_at: toDateTimeLocalValue(course.launch_offer_starts_at, appTimezone) || '2026-08-15T00:00',
      launch_offer_ends_at: toDateTimeLocalValue(course.launch_offer_ends_at, appTimezone) || '2026-09-14T23:59',
      launch_list_price: course.launch_list_price ?? '75',
      launch_offer_price: course.launch_offer_price ?? '70',
      launch_deposit_amount: course.launch_deposit_amount ?? '20',
      launch_balance_amount: course.launch_balance_amount ?? '50',
      launch_balance_grace_days: course.launch_balance_grace_days ?? 5,
      launch_subscription_trial_ends_at:
         toDateTimeLocalValue(course.launch_subscription_trial_ends_at, appTimezone) || '2026-10-15T23:59',
      launch_full_upfront_price: course.launch_full_upfront_price ?? '75',
   });

   useEffect(() => {
      setData((current) => ({
         ...current,
         launch_offer_starts_at: toDateTimeLocalValue(course.launch_offer_starts_at, appTimezone) || current.launch_offer_starts_at,
         launch_offer_ends_at: toDateTimeLocalValue(course.launch_offer_ends_at, appTimezone) || current.launch_offer_ends_at,
         launch_subscription_trial_ends_at:
            toDateTimeLocalValue(course.launch_subscription_trial_ends_at, appTimezone) ||
            current.launch_subscription_trial_ends_at,
      }));
   }, [
      course.launch_offer_starts_at,
      course.launch_offer_ends_at,
      course.launch_subscription_trial_ends_at,
      appTimezone,
      setData,
   ]);

   transform((form) => {
      const isUpfront = form.billing_model === 'upfront_subscription';
      const isPreRegister = form.billing_model === 'pre_register_subscription';
      const launchEnabled = (isPreRegister || Boolean(form.launch_offer_enabled)) && !isUpfront;

      return {
         ...form,
         tab: 'pricing',
         discount: Boolean(form.discount),
         launch_offer_enabled: launchEnabled,
         price: form.price === '' || form.price === null ? null : Number(form.price),
         subscription_price:
            form.subscription_price === '' || form.subscription_price === null ? null : Number(form.subscription_price),
         discount_price:
            form.discount && form.discount_price !== '' && form.discount_price !== null
               ? Number(form.discount_price)
               : null,
         expiry_duration: form.expiry_type === limitedValue ? form.expiry_duration || null : null,
         launch_list_price: launchEnabled ? Number(form.launch_list_price) : null,
         launch_offer_price: launchEnabled ? Number(form.launch_offer_price) : null,
         launch_deposit_amount: launchEnabled ? Number(form.launch_deposit_amount) : null,
         launch_balance_amount: launchEnabled ? Number(form.launch_balance_amount) : null,
         launch_balance_grace_days: launchEnabled ? Number(form.launch_balance_grace_days || 5) : 5,
         launch_full_upfront_price: launchEnabled ? Number(form.launch_full_upfront_price) : null,
         launch_offer_starts_at: launchEnabled ? form.launch_offer_starts_at : null,
         launch_offer_ends_at: launchEnabled ? form.launch_offer_ends_at : null,
         launch_subscription_trial_ends_at: launchEnabled ? form.launch_subscription_trial_ends_at : null,
         billing_model: form.billing_model,
      };
   });

   const [syncing, setSyncing] = useState(false);
   const isPaid = data.pricing_type === paidValue;
   const isPreRegisterSubscription = isPaid && data.billing_model === 'pre_register_subscription';
   const isUpfrontSubscription = isPaid && data.billing_model === 'upfront_subscription' && !data.launch_offer_enabled;
   const isSubscription =
      isPaid &&
      (data.billing_model === 'subscription' ||
         data.billing_model === 'upfront_subscription' ||
         data.billing_model === 'pre_register_subscription' ||
         data.launch_offer_enabled);
   const isOneTime = isPaid && data.billing_model === 'one_time' && !data.launch_offer_enabled;

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('courses.update', { id: course.id }), {
         preserveScroll: true,
      });
   };

   const handleStripeSync = () => {
      setSyncing(true);
      router.post(
         route('courses.stripe.sync', { id: course.id }),
         {},
         {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
         },
      );
   };

   return (
      <Card className="container p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-6">
            <Accordion collapsible type="single" value={data.pricing_type}>
               <div>
                  <Label>{input.pricing_type} *</Label>
                  <RadioGroup
                     value={data.pricing_type}
                     className="flex items-center space-x-4 pt-2 pb-1"
                     onValueChange={(value) => setData('pricing_type', value)}
                  >
                     {priceOptions.map((price) => (
                        <div key={price} className="flex items-center space-x-2">
                           <RadioGroupItem className="cursor-pointer" id={`pricing-${price}`} value={price} />
                           <Label htmlFor={`pricing-${price}`} className="capitalize">
                              {price}
                           </Label>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.pricing_type} />
               </div>

               <AccordionItem value={paidValue} className="border-none">
                  <AccordionContent className="space-y-6 p-0.5">
                     <div className="space-y-3 pt-3">
                        <Label>Billing model *</Label>
                        <RadioGroup
                           value={data.billing_model}
                           className="grid gap-3 sm:grid-cols-1 lg:grid-cols-3"
                           onValueChange={(value) => {
                              setData((current) => ({
                                 ...current,
                                 billing_model: value,
                                 discount: value === 'one_time' ? current.discount : false,
                                 discount_price: value === 'one_time' ? current.discount_price : '',
                                 launch_offer_enabled:
                                    value === 'pre_register_subscription'
                                       ? true
                                       : value === 'upfront_subscription'
                                          ? false
                                          : current.launch_offer_enabled,
                                 price:
                                    value === 'upfront_subscription' && !current.price
                                       ? '75'
                                       : current.price,
                                 subscription_price:
                                    (value === 'subscription' ||
                                       value === 'upfront_subscription' ||
                                       value === 'pre_register_subscription') &&
                                    !current.subscription_price
                                       ? '6'
                                       : current.subscription_price,
                              }));
                           }}
                        >
                           {billingOptions.map((option) => (
                              <div key={option.value} className="flex items-center space-x-2 rounded-md border p-3">
                                 <RadioGroupItem className="cursor-pointer" id={`billing-${option.value}`} value={option.value} />
                                 <Label htmlFor={`billing-${option.value}`} className="cursor-pointer">
                                    {option.label ?? billingModelLabel(option.value)}
                                 </Label>
                              </div>
                           ))}
                        </RadioGroup>
                        <InputError message={errors.billing_model} />
                     </div>

                     {isOneTime || isUpfrontSubscription ? (
                        <>
                           <div>
                              <Label>{isUpfrontSubscription ? 'Upfront enrollment price *' : `${dashboard.price} *`}</Label>
                              <Input
                                 type="number"
                                 name="price"
                                 min="1"
                                 step="0.01"
                                 value={data.price === null || data.price === undefined ? '' : String(data.price)}
                                 onChange={(e) => setData('price', e.target.value)}
                                 placeholder={isUpfrontSubscription ? '75.00' : input.course_price_placeholder}
                              />
                              {isUpfrontSubscription ? (
                                 <p className="text-muted-foreground mt-1 text-xs">
                                    Students pay this amount at checkout for immediate access (tax-inclusive).
                                 </p>
                              ) : null}
                              <InputError message={errors.price} />
                           </div>

                           {isOneTime ? (
                              <div className="space-y-2">
                                 <div className="flex items-center space-x-2">
                                    <Checkbox
                                       id="discount"
                                       checked={Boolean(data.discount)}
                                       onCheckedChange={(checked) => setData('discount', checked === true)}
                                    />
                                    <Label htmlFor="discount">{dashboard.check_course_discount}</Label>
                                 </div>

                                 {data.discount ? (
                                    <div>
                                       <Input
                                          type="number"
                                          name="discount_price"
                                          min="1"
                                          step="0.01"
                                          value={
                                             data.discount_price === null || data.discount_price === undefined
                                                ? ''
                                                : String(data.discount_price)
                                          }
                                          onChange={(e) => setData('discount_price', e.target.value)}
                                          placeholder={input.discount_price_placeholder}
                                       />
                                       <InputError message={errors.discount_price} />
                                    </div>
                                 ) : null}
                              </div>
                           ) : null}
                        </>
                     ) : null}

                     {isSubscription ? (
                        <div className="space-y-4">
                           <div>
                              <Label>Monthly subscription price *</Label>
                              <Input
                                 type="number"
                                 name="subscription_price"
                                 min="1"
                                 step="0.01"
                                 value={
                                    data.subscription_price === null || data.subscription_price === undefined
                                       ? ''
                                       : String(data.subscription_price)
                                 }
                                 onChange={(e) => setData('subscription_price', e.target.value)}
                                 placeholder="6.00"
                              />
                              <p className="text-muted-foreground mt-1 text-xs">
                                 {isUpfrontSubscription
                                    ? 'After the first free month, students are billed this amount every month until they cancel (tax-inclusive).'
                                    : isPreRegisterSubscription
                                      ? 'Pre-reg balance payers get a free period until the trial end date, then this amount monthly. Full-upfront enrollments after pre-reg are billed monthly with no free trial (tax-inclusive).'
                                      : 'Students are billed this amount every month while subscribed (tax-inclusive).'}
                              </p>
                              <InputError message={errors.subscription_price} />
                           </div>

                           {!isUpfrontSubscription ? (
                              <>
                                 <Separator />

                                 <div className="space-y-4 rounded-md border p-4">
                                    {isPreRegisterSubscription ? (
                                       <>
                                          <div>
                                             <Label className="text-base">Pre-registration setup</Label>
                                             <p className="text-muted-foreground mt-1 text-xs">
                                                Students can pay a DP during the pre-registration window to reserve a seat only.
                                                They get no access until launch day, must pay the balance to unlock the course,
                                                then start a one-month free subscription before monthly billing begins.
                                             </p>
                                          </div>
                                          <InputError message={errors.launch_offer_enabled} />

                                          <div className="grid gap-4 sm:grid-cols-2">
                                             <div className="sm:col-span-2">
                                                <p className="text-muted-foreground text-xs">
                                                   All pre-registration dates below use {dateTimezoneLabel}. DP checkout does not
                                                   allow coupons. Balance and full-upfront checkout can use coupons.
                                                </p>
                                             </div>
                                             <div>
                                                <Label>Pre-register start ({dateTimezoneLabel})</Label>
                                                <Input
                                                   type="datetime-local"
                                                   value={data.launch_offer_starts_at}
                                                   onChange={(e) => setData('launch_offer_starts_at', e.target.value)}
                                                />
                                                <InputError message={errors.launch_offer_starts_at} />
                                             </div>
                                             <div>
                                                <Label>Pre-register end ({dateTimezoneLabel})</Label>
                                                <Input
                                                   type="datetime-local"
                                                   value={data.launch_offer_ends_at}
                                                   onChange={(e) => setData('launch_offer_ends_at', e.target.value)}
                                                />
                                                <InputError message={errors.launch_offer_ends_at} />
                                             </div>
                                             <div>
                                                <Label>List price (display only)</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   step="0.01"
                                                   value={String(data.launch_list_price)}
                                                   onChange={(e) => setData('launch_list_price', e.target.value)}
                                                />
                                                <InputError message={errors.launch_list_price} />
                                             </div>
                                             <div>
                                                <Label>Pre-registration total (display only)</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   step="0.01"
                                                   value={String(data.launch_offer_price)}
                                                   onChange={(e) => setData('launch_offer_price', e.target.value)}
                                                />
                                                <InputError message={errors.launch_offer_price} />
                                             </div>
                                             <div>
                                                <Label>Deposit / DP (pay now)</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   step="0.01"
                                                   value={String(data.launch_deposit_amount)}
                                                   onChange={(e) => setData('launch_deposit_amount', e.target.value)}
                                                />
                                                <InputError message={errors.launch_deposit_amount} />
                                             </div>
                                             <div>
                                                <Label>Balance (pay on launch)</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   step="0.01"
                                                   value={String(data.launch_balance_amount)}
                                                   onChange={(e) => setData('launch_balance_amount', e.target.value)}
                                                />
                                                <InputError message={errors.launch_balance_amount} />
                                             </div>
                                             <div>
                                                <Label>Grace days after launch</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   max="30"
                                                   value={String(data.launch_balance_grace_days)}
                                                   onChange={(e) => setData('launch_balance_grace_days', e.target.value)}
                                                />
                                                <InputError message={errors.launch_balance_grace_days} />
                                             </div>
                                             <div>
                                                <Label>Full upfront (after launch)</Label>
                                                <Input
                                                   type="number"
                                                   min="1"
                                                   step="0.01"
                                                   value={String(data.launch_full_upfront_price)}
                                                   onChange={(e) => setData('launch_full_upfront_price', e.target.value)}
                                                />
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                   Price for new enrollments after pre-reg ends. Full access immediately, then monthly
                                                   billing with no free trial. Coupons allowed.
                                                </p>
                                                <InputError message={errors.launch_full_upfront_price} />
                                             </div>
                                             <div className="sm:col-span-2">
                                                <Label>First subscription charge / trial end ({dateTimezoneLabel})</Label>
                                                <Input
                                                   type="datetime-local"
                                                   value={data.launch_subscription_trial_ends_at}
                                                   onChange={(e) => setData('launch_subscription_trial_ends_at', e.target.value)}
                                                />
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                   Applies only to pre-registration students who pay the launch balance. They get full
                                                   access and stay free until this fixed date, then monthly billing starts. Does not
                                                   apply to full-upfront enrollments after pre-reg ends. Time is {dateTimezoneLabel}.
                                                </p>
                                                <InputError message={errors.launch_subscription_trial_ends_at} />
                                             </div>
                                          </div>
                                       </>
                                    ) : data.launch_offer_enabled ? (
                                       <div className="grid gap-4 sm:grid-cols-2">
                                          <div className="sm:col-span-2">
                                             <p className="text-muted-foreground text-xs">
                                                All early-bird dates below use {dateTimezoneLabel}. Enter times the same way
                                                as Coming Soon / Launch date.
                                             </p>
                                          </div>
                                          <div>
                                             <Label>Pre-register start ({dateTimezoneLabel})</Label>
                                             <Input
                                                type="datetime-local"
                                                value={data.launch_offer_starts_at}
                                                onChange={(e) => setData('launch_offer_starts_at', e.target.value)}
                                             />
                                             <InputError message={errors.launch_offer_starts_at} />
                                          </div>
                                          <div>
                                             <Label>Pre-register end ({dateTimezoneLabel})</Label>
                                             <Input
                                                type="datetime-local"
                                                value={data.launch_offer_ends_at}
                                                onChange={(e) => setData('launch_offer_ends_at', e.target.value)}
                                             />
                                             <InputError message={errors.launch_offer_ends_at} />
                                          </div>
                                          <div>
                                             <Label>List price (was)</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                step="0.01"
                                                value={String(data.launch_list_price)}
                                                onChange={(e) => setData('launch_list_price', e.target.value)}
                                             />
                                             <InputError message={errors.launch_list_price} />
                                          </div>
                                          <div>
                                             <Label>Early bird total (display)</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                step="0.01"
                                                value={String(data.launch_offer_price)}
                                                onChange={(e) => setData('launch_offer_price', e.target.value)}
                                             />
                                             <InputError message={errors.launch_offer_price} />
                                          </div>
                                          <div>
                                             <Label>Deposit (pay now)</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                step="0.01"
                                                value={String(data.launch_deposit_amount)}
                                                onChange={(e) => setData('launch_deposit_amount', e.target.value)}
                                             />
                                             <InputError message={errors.launch_deposit_amount} />
                                          </div>
                                          <div>
                                             <Label>Balance (pay on launch)</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                step="0.01"
                                                value={String(data.launch_balance_amount)}
                                                onChange={(e) => setData('launch_balance_amount', e.target.value)}
                                             />
                                             <InputError message={errors.launch_balance_amount} />
                                          </div>
                                          <div>
                                             <Label>Grace days after launch</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                max="30"
                                                value={String(data.launch_balance_grace_days)}
                                                onChange={(e) => setData('launch_balance_grace_days', e.target.value)}
                                             />
                                             <InputError message={errors.launch_balance_grace_days} />
                                          </div>
                                          <div>
                                             <Label>Full upfront (from Sept 15+)</Label>
                                             <Input
                                                type="number"
                                                min="1"
                                                step="0.01"
                                                value={String(data.launch_full_upfront_price)}
                                                onChange={(e) => setData('launch_full_upfront_price', e.target.value)}
                                             />
                                             <InputError message={errors.launch_full_upfront_price} />
                                          </div>
                                          <div className="sm:col-span-2">
                                             <Label>First subscription charge / trial end ({dateTimezoneLabel})</Label>
                                             <Input
                                                type="datetime-local"
                                                value={data.launch_subscription_trial_ends_at}
                                                onChange={(e) => setData('launch_subscription_trial_ends_at', e.target.value)}
                                             />
                                             <p className="text-muted-foreground mt-1 text-xs">
                                                Pre-registrants: free until this date (e.g. Oct 15), then monthly price applies.
                                                Deposit is non-refundable if balance is not paid within grace days. Time is{' '}
                                                {dateTimezoneLabel}.
                                             </p>
                                             <InputError message={errors.launch_subscription_trial_ends_at} />
                                          </div>
                                       </div>
                                    ) : (
                                       <>
                                          <div className="flex items-center space-x-2">
                                             <Checkbox
                                                id="launch_offer_enabled"
                                                checked={data.launch_offer_enabled}
                                                onCheckedChange={(checked) => {
                                                   const enabled = Boolean(checked);
                                                   setData((current) => ({
                                                      ...current,
                                                      launch_offer_enabled: enabled,
                                                      subscription_price:
                                                         enabled && !current.subscription_price ? '6' : current.subscription_price,
                                                   }));
                                                }}
                                             />
                                             <Label htmlFor="launch_offer_enabled">Enable launch / early-bird offer</Label>
                                          </div>
                                          <p className="text-muted-foreground text-xs">
                                             Pre-register window shows a deposit now and balance due on launch. After the window,
                                             students pay the full upfront amount + monthly subscription.
                                          </p>
                                          <InputError message={errors.launch_offer_enabled} />
                                       </>
                                    )}
                                 </div>
                              </>
                           ) : (
                              <p className="text-muted-foreground text-sm">
                                 Regular pricing: students pay the upfront amount now for full access, then the monthly
                                 subscription after 30 days. Early-bird / deposit setup is not used with this model.
                              </p>
                           )}

                           <Separator />

                           <div className="space-y-3">
                              <div className="flex flex-wrap items-center gap-2">
                                 <Label>Stripe checkout</Label>
                                 {stripeSynced ? (
                                    <Badge variant="default" className="gap-1">
                                       <CheckCircle2 className="h-3 w-3" />
                                       Synced
                                    </Badge>
                                 ) : (
                                    <Badge variant="secondary">Not synced</Badge>
                                 )}
                              </div>

                              {!stripeActive ? (
                                 <Alert>
                                    <AlertTitle>Stripe inactive</AlertTitle>
                                    <AlertDescription>
                                       Enable Stripe in payment settings before syncing subscription checkout.
                                    </AlertDescription>
                                 </Alert>
                              ) : (
                                 <>
                                    {(course.stripe_product_id || course.stripe_price_id) && (
                                       <div className="text-muted-foreground space-y-1 text-xs">
                                          {course.stripe_product_id ? <p>Product: {course.stripe_product_id}</p> : null}
                                          {course.stripe_price_id ? <p>Price: {course.stripe_price_id}</p> : null}
                                       </div>
                                    )}

                                    <p className="text-muted-foreground text-sm">
                                       Save your pricing changes first, then sync to Stripe. Changing the monthly price
                                       creates a new Stripe price; existing subscribers keep their current price until you
                                       migrate them in Stripe.
                                    </p>

                                    <Button type="button" variant="outline" disabled={syncing || processing} onClick={handleStripeSync}>
                                       <RefreshCw className={`mr-2 h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
                                       Sync to Stripe
                                    </Button>
                                 </>
                              )}
                           </div>
                        </div>
                     ) : null}
                  </AccordionContent>
               </AccordionItem>
            </Accordion>

            <Accordion collapsible type="single" value={data.expiry_type}>
               <div>
                  <Label>Expiry period type</Label>
                  <RadioGroup
                     value={data.expiry_type}
                     className="flex items-center space-x-4 pt-2 pb-1"
                     onValueChange={(value) => setData('expiry_type', value)}
                  >
                     {expiryOptions.map((expiry) => (
                        <div key={expiry} className="flex items-center space-x-2">
                           <RadioGroupItem className="cursor-pointer" id={`expiry-${expiry}`} value={expiry} />
                           <Label htmlFor={`expiry-${expiry}`} className="capitalize">
                              {expiry.replace('_', ' ')}
                           </Label>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.expiry_type} />
               </div>

               <AccordionItem value={limitedValue} className="border-none">
                  <AccordionContent className="space-y-4 p-0.5">
                     <div className="pt-3">
                        <Label>{input.expiry_duration}</Label>
                        <Combobox
                           defaultValue={data.expiry_duration as string}
                           data={courseDurations}
                           placeholder={input.expiry_duration_placeholder || 'Select duration'}
                           onSelect={(selected) => setData('expiry_duration', selected.value)}
                        />
                        <InputError message={errors.expiry_duration} />
                     </div>
                  </AccordionContent>
               </AccordionItem>
            </Accordion>

            <div className="mt-8">
               <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
            </div>
         </form>
      </Card>
   );
};

Pricing.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Pricing;
