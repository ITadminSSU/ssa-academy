import Switch from '@/components/switch';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';

interface GatewaySetting {
   id: number;
   sub_type: string;
   title: string;
   fields: { active?: boolean };
}

interface Props {
   payments: GatewaySetting[];
}

const PRIMARY_GATEWAYS = [
   { sub_type: 'stripe', label: 'Stripe' },
   { sub_type: 'bank_transfer', label: 'Bank Transfer' },
   { sub_type: 'wire_transfer', label: 'Wire Transfer' },
] as const;

const PaymentGatewayOverview = ({ payments }: Props) => {
   const toggleGateway = (payment: GatewaySetting, active: boolean) => {
      router.post(route('settings.payment.update', payment.id), {
         ...(payment.fields as Record<string, unknown>),
         type: payment.sub_type,
         active,
      });
   };

   return (
      <Card className="mb-6">
         <CardHeader>
            <CardTitle className="text-base">Primary Payment Gateways</CardTitle>
            <p className="text-muted-foreground text-sm">Enable or disable Stripe, Bank Transfer, and Wire Transfer for checkout.</p>
         </CardHeader>
         <CardContent className="grid gap-4 sm:grid-cols-3">
            {PRIMARY_GATEWAYS.map(({ sub_type, label }) => {
               const payment = payments.find((p) => p.sub_type === sub_type);
               if (!payment) {
                  return (
                     <div key={sub_type} className="rounded-lg border p-4 opacity-60">
                        <p className="font-medium">{label}</p>
                        <p className="text-muted-foreground text-xs">Not configured</p>
                     </div>
                  );
               }

               const isActive = Boolean(payment.fields?.active);

               return (
                  <div key={sub_type} className="flex items-center justify-between rounded-lg border p-4">
                     <div>
                        <p className="font-medium">{label}</p>
                        <p className="text-muted-foreground text-xs">{isActive ? 'Enabled' : 'Disabled'}</p>
                     </div>
                     <div className="flex items-center gap-2">
                        <Label htmlFor={`gw-${sub_type}`} className="text-xs">
                           {isActive ? 'On' : 'Off'}
                        </Label>
                        <Switch
                           id={`gw-${sub_type}`}
                           checked={isActive}
                           onCheckedChange={(checked) => toggleGateway(payment, checked)}
                        />
                     </div>
                  </div>
               );
            })}
         </CardContent>
      </Card>
   );
};

export default PaymentGatewayOverview;
