import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode, useEffect, useMemo, useState } from 'react';
import { Editor } from 'richtor';
import 'richtor/styles';

interface Props {
   instructor: Instructor;
   actionComponent: ReactNode;
}

const defaultStatusFor = (currentStatus: Instructor['status']): Instructor['status'] => {
   if (currentStatus === 'pending') {
      return 'approved';
   }

   if (currentStatus === 'rejected') {
      return 'approved';
   }

   return 'rejected';
};

const ApplicationApproval = ({ instructor, actionComponent }: Props) => {
   const [open, setOpen] = useState(false);
   const statuses = useMemo(
      () => ['pending', 'approved', 'rejected'].filter((status) => status !== instructor.status),
      [instructor.status],
   );

   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { dashboard, input, button } = translate;

   const { data, put, setData, processing, errors, reset, clearErrors } = useForm({
      status: defaultStatusFor(instructor.status),
      feedback: '',
   });

   useEffect(() => {
      if (!open) {
         return;
      }

      clearErrors();
      reset('status', 'feedback');
      setData({
         status: defaultStatusFor(instructor.status),
         feedback: '',
      });
   }, [open, instructor.status]);

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      if (!data.status) {
         return;
      }

      put(route('instructors.status', { id: instructor.id }), {
         preserveScroll: true,
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   const feedbackRequired = data.status === 'rejected';

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{actionComponent}</DialogTrigger>
         <DialogContent>
            <DialogHeader>
               <DialogTitle>{dashboard.review_application ?? 'Review Application'}</DialogTitle>
            </DialogHeader>

            <p className="text-muted-foreground text-sm">
               {dashboard.review_application_for ?? 'Reviewing application for'} <strong>{instructor.user.name}</strong>
            </p>

            <form onSubmit={handleSubmit} className="space-y-4">
               <div>
                  <Label>{dashboard.approval_status} *</Label>
                  <Select required value={data.status} onValueChange={(value) => setData('status', value as Instructor['status'])}>
                     <SelectTrigger>
                        <SelectValue placeholder={dashboard.select_approval_status ?? 'Select the approval status'} />
                     </SelectTrigger>
                     <SelectContent>
                        {statuses.map((status) => (
                           <SelectItem key={status} value={status} className="capitalize">
                              {status}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <InputError message={errors.status} />
               </div>

               <div className="pb-6">
                  <Label>
                     {dashboard.feedback}
                     {feedbackRequired ? ' *' : ''}
                  </Label>
                  {!feedbackRequired && (
                     <p className="text-muted-foreground mb-2 text-xs">
                        {dashboard.feedback_optional_approval ?? 'Optional when approving. Required when rejecting.'}
                     </p>
                  )}
                  <Editor
                     ssr={true}
                     output="html"
                     placeholder={{
                        paragraph: input.description_placeholder,
                        imageCaption: input.image_url_placeholder,
                     }}
                     contentMinHeight={256}
                     contentMaxHeight={640}
                     initialContent={data.feedback}
                     onContentChange={(value) =>
                        setData((prev) => ({
                           ...prev,
                           feedback: value as string,
                        }))
                     }
                  />
                  <InputError message={errors.feedback} />
               </div>

               <LoadingButton loading={processing} className="w-full">
                  {button.submit}
               </LoadingButton>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default ApplicationApproval;
