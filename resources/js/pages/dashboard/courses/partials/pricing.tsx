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
import { onHandleChange } from '@/lib/inertia';
import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, RefreshCw } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';
import { CourseUpdateProps } from '../update';

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
   const { tab, prices, expiries, course, billingModels = [], stripeActive, stripeSynced } = props;

   const billingOptions = useMemo(() => {
      if (Array.isArray(billingModels) && billingModels.length > 0) {
         if (typeof billingModels[0] === 'string') {
            return (billingModels as string[]).map((value) => ({
               value,
               label: billingModelLabel(value),
            }));
         }

         return billingModels as { value: string; label?: string }[];
      }

      return [
         { value: 'one_time', label: 'One-time purchase' },
         { value: 'subscription', label: 'Monthly subscription' },
      ];
   }, [billingModels]);

   const { data, setData, post, errors, processing } = useForm({
      tab: tab,
      pricing_type: course.pricing_type || '',
      billing_model: course.billing_model || 'one_time',
      price: course.price || '',
      subscription_price: course.subscription_price || '',
      discount: Boolean(course.discount) || false,
      discount_price: course.discount_price || '',
      expiry_type: course.expiry_type || '',
      expiry_duration: course.expiry_duration || '',
   });

   const [syncing, setSyncing] = useState(false);
   const isPaid = data.pricing_type === prices[1];
   const isSubscription = isPaid && data.billing_model === 'subscription';
   const isOneTime = isPaid && data.billing_model === 'one_time';

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('courses.update', { id: course.id }));
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
            <Accordion collapsible type="single" value={data.pricing_type as string}>
               <div>
                  <Label>{input.pricing_type} *</Label>
                  <RadioGroup
                     defaultValue={data.pricing_type as string}
                     className="flex items-center space-x-4 pt-2 pb-1"
                     onValueChange={(value) => setData('pricing_type', value)}
                  >
                     {prices.map((price) => (
                        <div key={price} className="flex items-center space-x-2">
                           <RadioGroupItem className="cursor-pointer" id={price} value={price} />
                           <Label htmlFor={price} className="capitalize">
                              {price}
                           </Label>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.pricing_type} />
               </div>

               <AccordionItem value={prices[1]} className="border-none">
                  <AccordionContent className="space-y-6 p-0.5">
                     <div className="space-y-3 pt-3">
                        <Label>Billing model *</Label>
                        <RadioGroup
                           value={data.billing_model as string}
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
                                 value={data.price}
                                 onChange={(e) => onHandleChange(e, setData)}
                                 placeholder={input.course_price_placeholder}
                              />
                              <InputError message={errors.price} />
                           </div>

                           <div className="space-y-2">
                              <div className="flex items-center space-x-2">
                                 <Checkbox
                                    id="discount"
                                    name="discount"
                                    checked={data.discount as any}
                                    onCheckedChange={(checked: boolean) => {
                                       setData('discount', checked as any);
                                    }}
                                 />
                                 <Label htmlFor="discount">{dashboard.check_course_discount}</Label>
                              </div>

                              {data.discount && (
                                 <div>
                                    <Input
                                       type="number"
                                       name="discount_price"
                                       value={data.discount_price}
                                       onChange={(e) => onHandleChange(e, setData)}
                                       placeholder={input.discount_price_placeholder}
                                    />
                                    <InputError message={errors.discount_price} />
                                 </div>
                              )}
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
                                 value={data.subscription_price}
                                 onChange={(e) => onHandleChange(e, setData)}
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
                                       Save your pricing changes first, then sync to Stripe. Changing the monthly price creates a new Stripe price; existing subscribers keep their current price until you migrate them in Stripe.
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
                     defaultValue={data.expiry_type}
                     className="flex items-center space-x-4 pt-2 pb-1"
                     onValueChange={(value) => setData('expiry_type', value)}
                  >
                     {expiries.map((expiry) => (
                        <div key={expiry} className="flex items-center space-x-2">
                           <RadioGroupItem className="cursor-pointer" id={expiry} value={expiry} />
                           <Label htmlFor={expiry} className="capitalize">
                              {expiry.replace('_', ' ')}
                           </Label>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.expiry_type} />
               </div>

               <AccordionItem value={expiries[1]} className="border-none">
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
