import ManualTransferGateway from '@/components/gateways/manual-transfer-gateway';
import Mollie from '@/components/gateways/mollie';
import PaymentGatewayOverview from '@/components/gateways/payment-gateway-overview';
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

   const resolveComponent = (payment: Settings<any>) => {
      switch (payment.sub_type) {
         case 'paypal':
            return Paypal;
         case 'stripe':
            return Stripe;
         case 'mollie':
            return Mollie;
         case 'paystack':
            return Paystack;
         case 'sslcommerz':
            return SSLCommerz;
         case 'razorpay':
            return Razorpay;
         case 'bank_transfer':
            return (props: { payment: Settings<any>; routePath: string }) => (
               <ManualTransferGateway
                  {...props}
                  gatewayType="bank_transfer"
                  title="Bank Transfer Settings"
                  description="Configure domestic bank transfer instructions for students."
               />
            );
         case 'wire_transfer':
            return (props: { payment: Settings<any>; routePath: string }) => (
               <ManualTransferGateway
                  {...props}
                  gatewayType="wire_transfer"
                  title="Wire Transfer Settings"
                  description="Configure international wire transfer instructions for students."
               />
            );
         case 'offline':
            return (props: { payment: Settings<any>; routePath: string }) => (
               <ManualTransferGateway
                  {...props}
                  gatewayType="offline"
                  title="Offline Payment Settings"
                  description="Legacy manual payment option (cash, cheque, etc.)."
               />
            );
         default:
            return ({ payment }: { payment: any }) => <div>No component found for {payment.sub_type}</div>;
      }
   };

   const tabs = payments.map((payment) => ({
      ...payment,
      Component: resolveComponent(payment),
   }));

   const orderedTabs = [
      ...tabs.filter((t) => ['stripe', 'bank_transfer', 'wire_transfer'].includes(t.sub_type)),
      ...tabs.filter((t) => !['stripe', 'bank_transfer', 'wire_transfer', 'offline'].includes(t.sub_type)),
      ...tabs.filter((t) => t.sub_type === 'offline'),
   ];

   const defaultTab = params['tab'] ?? orderedTabs[0]?.sub_type;

   return (
      <section className="md:px-3">
         <PaymentGatewayOverview payments={payments} />

         <Tabs value={defaultTab} className="grid grid-rows-1 gap-5 md:grid-cols-4">
            <div>
               <TabsList className="horizontal-tabs-list">
                  {orderedTabs.map(({ id, title, sub_type }) => (
                     <TabsTrigger
                        key={id}
                        value={sub_type}
                        className="horizontal-tabs-trigger"
                        onClick={() => router.get(route('settings.payment', { tab: sub_type }))}
                     >
                        {title}
                     </TabsTrigger>
                  ))}
               </TabsList>
            </div>

            <div className="md:col-span-3">
               {orderedTabs.map((payment) => (
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
