import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Switch from '@/components/switch';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface ManualTransferFields {
   active: boolean;
   payment_instructions: string;
   payment_details: string;
}

interface Props {
   payment: Settings<ManualTransferFields>;
   routePath: string;
   gatewayType: 'bank_transfer' | 'wire_transfer' | 'offline';
   title: string;
   description: string;
}

const ManualTransferGateway = ({ payment, routePath, gatewayType, title, description }: Props) => {
   const { props } = usePage<SharedData>();
   const { button, common } = props.translate;
   const { data, setData, post, errors, processing } = useForm({
      ...(payment.fields as ManualTransferFields),
      type: gatewayType,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(routePath);
   };

   return (
      <Card className="p-4 sm:p-6">
         <div className="mb-6 flex items-center justify-between">
            <div>
               <h2 className="text-xl font-semibold">{title}</h2>
               <p className="text-muted-foreground">{description}</p>
            </div>
            <div className="flex items-center space-x-2">
               <Label htmlFor="status">{data.active ? common.enabled : common.disabled}</Label>
               <Switch id="status" checked={data.active} onCheckedChange={(checked) => setData('active', checked)} />
            </div>
         </div>

         <form onSubmit={handleSubmit} className="space-y-6">
            <div>
               <Label>Payment Instructions</Label>
               <Editor
                  output="html"
                  placeholder={{ paragraph: 'Enter instructions for students...' }}
                  contentMinHeight={200}
                  contentMaxHeight={400}
                  initialContent={data.payment_instructions}
                  onContentChange={(value) => setData('payment_instructions', value as string)}
               />
               <InputError message={errors.payment_instructions} />
            </div>

            <div>
               <Label>Bank / Wire Details</Label>
               <Editor
                  output="html"
                  placeholder={{ paragraph: 'Enter account and routing details...' }}
                  contentMinHeight={200}
                  contentMaxHeight={400}
                  initialContent={data.payment_details}
                  onContentChange={(value) => setData('payment_details', value as string)}
               />
               <InputError message={errors.payment_details} />
            </div>

            <LoadingButton loading={processing}>{button.save_changes}</LoadingButton>
         </form>
      </Card>
   );
};

export default ManualTransferGateway;
