import Mollie from '@/components/gateways/mollie';
import Offline from '@/components/gateways/offline';
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

interface Props {
   payments: Settings<PaypalFields | StripeFields | MollieFields | PaystackFields | RazorpayFields | any>[];
}

const Payment = ({ payments }: Props) => {
   const page = usePage();
   const params = getQueryParams(page.url);

   const tabs = payments.map((payment) => {
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

         case 'offline':
            Component = Offline;
            break;

         default:
            Component = ({ payment }: { payment: any }) => <div>No component found</div>;
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
                              route('settings.payment', {
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
                     <payment.Component payment={payment} routePath={route('settings.payment.update', payment.id)} />
                  </TabsContent>
               ))}
            </div>
         </Tabs>
      </section>
   );
};

Payment.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Payment;
