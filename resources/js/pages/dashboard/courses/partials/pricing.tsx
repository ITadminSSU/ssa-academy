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
import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, RefreshCw } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
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

   return 'One-time purchase';
};

const Pricing = () => {
   const { props } = usePage<CourseUpdateProps>();
   const { translate } = props;
   const { dashboard, input, button } = translate;
   const { prices, expiries, course, billingModels = [], stripeActive, stripeSynced } = props;

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
      ];
   }, [billingModels]);

   const { data, setData, post, errors, processing, transform } = useForm({
      tab: 'pricing',
      pricing_type: asOptionValue(course.pricing_type) || paidValue,
      billing_model: asOptionValue(course.billing_model) || 'one_time',
      price: course.price ?? '',
      subscription_price: course.subscription_price ?? '',
      discount: Boolean(course.discount),
      discount_price: course.discount_price ?? '',
      expiry_type: asOptionValue(course.expiry_type) || 'lifetime',
      expiry_duration: course.expiry_duration || '',
   });

   transform((form) => ({
      ...form,
      tab: 'pricing',
      discount: Boolean(form.discount),
      price: form.price === '' || form.price === null ? null : Number(form.price),
      subscription_price:
         form.subscription_price === '' || form.subscription_price === null ? null : Number(form.subscription_price),
      discount_price:
         form.discount && form.discount_price !== '' && form.discount_price !== null
            ? Number(form.discount_price)
            : null,
      expiry_duration: form.expiry_type === limitedValue ? form.expiry_duration || null : null,
   }));

   const [syncing, setSyncing] = useState(false);
   const isPaid = data.pricing_type === paidValue;
   const isSubscription = isPaid && data.billing_model === 'subscription';
   const isOneTime = isPaid && data.billing_model === 'one_time';

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
                           className="grid gap-3 sm:grid-cols-2"
                           onValueChange={(value) => {
                              setData((current) => ({
                                 ...current,
                                 billing_model: value,
                                 discount: value === 'subscription' ? false : current.discount,
                                 discount_price: value === 'subscription' ? '' : current.discount_price,
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

                     {isOneTime ? (
                        <>
                           <div>
                              <Label>{dashboard.price} *</Label>
                              <Input
                                 type="number"
                                 name="price"
                                 min="1"
                                 step="0.01"
                                 value={data.price === null || data.price === undefined ? '' : String(data.price)}
                                 onChange={(e) => setData('price', e.target.value)}
                                 placeholder={input.course_price_placeholder}
                              />
                              <InputError message={errors.price} />
                           </div>

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
                                 placeholder="29.99"
                              />
                              <p className="text-muted-foreground mt-1 text-xs">
                                 Students are billed this amount every month while subscribed.
                              </p>
                              <InputError message={errors.subscription_price} />
                           </div>

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
