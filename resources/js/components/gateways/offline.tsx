import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import Switch from '@/components/switch';
import { Card } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface OfflineFields {
   active: boolean;
   payment_instructions: string;
   payment_details: string;
}

interface OfflineProps {
   payment: Settings<OfflineFields>;
   routePath: string;
}

const Offline = ({ payment, routePath }: OfflineProps) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { button, common } = translate;
   const { data, setData, post, errors, processing } = useForm({
      ...(payment.fields as OfflineFields),
      type: 'offline',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(routePath);
   };

   return (
      <Card className="p-4 sm:p-6">
         <div className="mb-6 flex items-center justify-between">
            <div>
               <h2 className="text-xl font-semibold">Offline Payment Settings</h2>
               <p className="text-gray-500">Configure manual payment options for your students</p>
            </div>

            <div className="flex items-center space-x-2">
               <Label htmlFor="status">{data.active ? common.enabled : common.disabled}</Label>
               <Switch id="status" checked={data.active} onCheckedChange={(checked) => setData('active', checked)} />
            </div>
         </div>

         <form onSubmit={handleSubmit} className="space-y-6">
            <div>
               <Label>
                  Payment Instructions <span className="font-light">(for student)</span>
               </Label>
               <Editor
                  output="html"
                  placeholder={{
                     paragraph: 'Enter instructions here...',
                     imageCaption: 'Enter image caption here...',
                  }}
                  contentMinHeight={260}
                  contentMaxHeight={600}
                  initialContent={data.payment_instructions}
                  onContentChange={(value) => setData('payment_instructions', value as string)}
               />
               <InputError message={errors.payment_instructions} />
               <p className="mt-1 text-sm text-gray-500">These instructions will be shown to students when they select offline payment</p>
            </div>

            {/* Bank Details Section */}

            <div>
               <Label>Payment Details</Label>
               <Editor
                  output="html"
                  placeholder={{
                     paragraph: 'Enter payment details here...',
                     imageCaption: 'Enter image caption here...',
                  }}
                  contentMinHeight={260}
                  contentMaxHeight={600}
                  initialContent={data.payment_details}
                  onContentChange={(value) => setData('payment_details', value as string)}
               />
               <InputError message={errors.payment_details} />
               <p className="mt-1 text-sm text-gray-500">These payment/bank details will be displayed to students for making offline payments</p>
            </div>

            <div className="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
               <div className="flex">
                  <svg className="mr-2 h-5 w-5 shrink-0 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                     <path
                        fillRule="evenodd"
                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                        clipRule="evenodd"
                     />
                  </svg>
                  <div className="text-sm text-yellow-800">
                     <p className="mb-1 font-medium">Important Notice</p>
                     <p>
                        Offline payments require manual verification. You will need to approve each payment in the Payment History section before the
                        student can access the course.
                     </p>
                  </div>
               </div>
            </div>

            <LoadingButton loading={processing} type="submit" className="w-full">
               {button.save_changes}
            </LoadingButton>
         </form>
      </Card>
   );
};

export default Offline;
