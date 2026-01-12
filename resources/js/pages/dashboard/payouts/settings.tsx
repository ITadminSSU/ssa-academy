import Mollie from '@/components/gateways/mollie';
import Paypal from '@/components/gateways/paypal';
import Paystack from '@/components/gateways/paystack';
import Razorpay from '@/components/gateways/razorpay';
import SSLCommerz from '@/components/gateways/sslcommerz';
import Stripe from '@/components/gateways/stripe';
import Tabs from '@/components/tabs';
import { TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import DashboardLayout from '@/layouts/dashboard/layout';
import { getQueryParams } from '@/lib/route';
import { router, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

const Settings = ({ instructor }: { instructor: Instructor }) => {
   const page = usePage();
   const params = getQueryParams(page.url);

   const tabs = instructor.payout_methods.map((payment) => {
      let Component;

      switch (payment.sub_type) {
         case 'paypal':
            Component = Paypal;
            break;

         case 'stripe':
            Component = Stripe;
            break;

         case 'mollie':
            Component = Mollie;
            break;

         case 'paystack':
            Component = Paystack;
            break;

         case 'sslcommerz':
            Component = SSLCommerz;
            break;

         case 'razorpay':
            Component = Razorpay;
            break;

         default:
            Component = (props: { payment: any }) => <div>No component found</div>;
            break;
      }

      return {
         ...payment,
         Component,
      };
   });

   return (
      <section className="md:px-3">
         <Tabs value={params['tab'] ?? tabs[0].sub_type} className="grid grid-rows-1 gap-5 md:grid-cols-4">
            <div>
               <TabsList className="horizontal-tabs-list">
                  {tabs.map(({ id, title, sub_type }) => (
                     <TabsTrigger
                        key={id}
                        value={sub_type}
                        className="horizontal-tabs-trigger"
                        onClick={() =>
                           router.get(
                              route('payouts.settings.index', {
                                 tab: sub_type,
                              }),
                           )
                        }
                     >
                        {title}
                     </TabsTrigger>
                  ))}
               </TabsList>
            </div>

            <div className="md:col-span-3">
               {tabs.map((payment) => (
                  <TabsContent key={payment.id} value={payment.sub_type} className="m-0">
                     <payment.Component payment={payment} routePath={route('payouts.settings.update')} />
                  </TabsContent>
               ))}
            </div>
         </Tabs>
      </section>
   );
};

Settings.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Settings;
