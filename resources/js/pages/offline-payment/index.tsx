import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Main from '@/layouts/main';
import { onHandleChange } from '@/lib/inertia';
import { SharedData } from '@/types/global';
import { Head, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, CreditCard, Info, XCircle } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface Item {
   id: number;
   title: string;
   slug: string;
}

interface OfflinePaymentProps extends SharedData {
   user: User;
   item: Item;
   amount: number;
   currency: string;
   payment_instructions: string;
   payment_details: string;
   errors?: Record<string, string>;
}

const Index = () => {
   const { props } = usePage<OfflinePaymentProps>();
   const { user, item, amount, currency, payment_instructions, payment_details, errors } = props;

   const { data, setData, post, processing } = useForm({
      payment_info: '',
      payment_date: new Date().toISOString().split('T')[0],
      attachment: null,
   });

   const handleSubmit = (e: FormEvent) => {
      e.preventDefault();

      post(route('payments.offline.submit'));
   };

   return (
      <>
         <Head title="Offline Payment" />

         <div className="flex min-h-screen items-center justify-center bg-gray-50 py-8">
            <div className="mx-auto w-full max-w-[900px] p-6">
               <Card className="overflow-hidden shadow-sm">
                  {/* Header */}
                  <div className="bg-blue-500 p-6 text-white">
                     <h1 className="mb-2 text-2xl font-bold">Offline Payment Instructions</h1>
                     <p className="text-blue-100">Please complete the payment and submit your transaction details below</p>
                  </div>

                  <CardContent className="space-y-6 p-6">
                     {/* Order Summary */}
                     <Card className="border bg-gray-50">
                        <CardHeader>
                           <CardTitle className="text-lg">Order Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                           <div className="flex items-center justify-between">
                              <span className="text-gray-600">Item:</span>
                              <span className="font-medium text-gray-900">{item.title}</span>
                           </div>
                           <div className="flex items-center justify-between">
                              <span className="text-gray-600">Customer:</span>
                              <span className="font-medium text-gray-900">{user.name}</span>
                           </div>
                           <div className="flex items-center justify-between border-t pt-3">
                              <span className="text-lg font-semibold text-gray-800">Total Amount:</span>
                              <span className="text-2xl font-bold text-blue-600">
                                 {currency} {Number(amount).toFixed(2)}
                              </span>
                           </div>
                        </CardContent>
                     </Card>

                     {/* Payment Instructions */}
                     {payment_instructions && (
                        <Alert className="border-yellow-200 bg-yellow-50">
                           <Info className="h-5 w-5 text-yellow-600" />
                           <AlertDescription>
                              <h2 className="mb-3 text-lg font-semibold text-gray-800">Payment Instructions</h2>
                              <div className="prose prose-sm max-w-none text-gray-700" dangerouslySetInnerHTML={{ __html: payment_instructions }} />
                           </AlertDescription>
                        </Alert>
                     )}

                     {/* Payment Details */}
                     {payment_details && (
                        <Alert className="border-blue-200 bg-blue-50">
                           <CreditCard className="h-5 w-5 text-blue-600" />
                           <AlertDescription>
                              <h2 className="mb-4 text-lg font-semibold text-gray-800">Payment Details</h2>
                              <div className="prose prose-sm max-w-none text-gray-700" dangerouslySetInnerHTML={{ __html: payment_details }} />
                           </AlertDescription>
                        </Alert>
                     )}

                     {/* Payment Submission Form */}
                     <Card>
                        <CardHeader>
                           <CardTitle className="text-lg">Submit Payment Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                           <form onSubmit={handleSubmit} className="space-y-4">
                              {/* Payment Method */}
                              <div className="space-y-2">
                                 <Label htmlFor="payment_method">
                                    Payment Method Used <span className="text-red-500">*</span>
                                 </Label>
                                 <Editor
                                    output="html"
                                    placeholder={{
                                       paragraph: 'Enter payment details here...',
                                       imageCaption: 'Enter image caption here...',
                                    }}
                                    contentMinHeight={220}
                                    contentMaxHeight={600}
                                    initialContent={data.payment_info}
                                    onContentChange={(value) => setData('payment_info', value as string)}
                                 />
                                 <InputError message={errors.payment_info} />
                              </div>

                              {/* Payment Date */}
                              <div className="space-y-2">
                                 <Label>
                                    Payment Date <span className="text-red-500">*</span>
                                 </Label>
                                 <Input
                                    required
                                    type="date"
                                    name="payment_date"
                                    max={new Date().toISOString().split('T')[0]}
                                    value={data.payment_date}
                                    onChange={(e) => onHandleChange(e, setData)}
                                 />
                                 <InputError message={errors.payment_date} />
                              </div>

                              {/* Attachment */}
                              <div className="space-y-2">
                                 <Label>
                                    Transaction Attachment <span className="font-light">(Optional)</span>
                                 </Label>
                                 <Input
                                    type="file"
                                    name="attachment"
                                    placeholder="Enter transaction attachment"
                                    onChange={(e) => onHandleChange(e, setData)}
                                 />
                                 <InputError message={errors.attachment} />
                              </div>

                              {/* Info Alert */}
                              <Alert className="border-blue-200 bg-blue-50">
                                 <Info className="h-4 w-4 text-blue-600" />
                                 <AlertDescription className="text-sm text-blue-800">
                                    <p className="mb-1 font-medium">Important Notice</p>
                                    <p>
                                       Your enrollment will be activated once our team verifies your payment. This usually takes 1-2 business days.
                                       You will receive an email notification once your payment is confirmed.
                                    </p>
                                 </AlertDescription>
                              </Alert>

                              {/* Action Buttons */}
                              <div className="flex gap-3 pt-4">
                                 <Button type="submit" className="flex flex-1 items-center justify-center gap-2" disabled={processing}>
                                    <CheckCircle2 className="h-5 w-5" />
                                    {processing ? 'Submitting...' : 'Submit Payment Details'}
                                 </Button>
                                 <Button asChild type="button" variant="secondary" disabled={processing} className="gap-2">
                                    <a href={route('payments.offline.cancel')}>
                                       <XCircle className="h-4 w-4" />
                                       Cancel
                                    </a>
                                 </Button>
                              </div>
                           </form>
                        </CardContent>
                     </Card>
                  </CardContent>
               </Card>
            </div>
         </div>
      </>
   );
};

Index.layout = (page: ReactNode) => <Main children={page} />;

export default Index;
