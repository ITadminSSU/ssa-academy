import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

interface Props {
   user: User;
   actionComponent: ReactNode;
   protectedUserId?: number | null;
}

const EditForm = ({ user, actionComponent, protectedUserId }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { dashboard, input, button, common } = translate;
   const [open, setOpen] = useState(false);

   const text = (value: string | undefined, fallback: string) => value?.trim() || fallback;
   const isPrimaryAdmin = protectedUserId != null && user.id === protectedUserId;

   const accountKind = useMemo(() => {
      if (user.role === 'admin') return 'admin';
      if (user.role === 'instructor') return 'trainer';
      return 'student';
   }, [user.role]);

   const dialogTitle = useMemo(() => {
      if (isPrimaryAdmin) {
         return text(dashboard.update_primary_admin, 'Update Primary Admin');
      }

      switch (accountKind) {
         case 'admin':
            return text(dashboard.update_admin, 'Update Admin');
         case 'trainer':
            return text(dashboard.update_trainer, 'Update Trainer');
         default:
            return text(dashboard.update_user, 'Update User');
      }
   }, [accountKind, dashboard, isPrimaryAdmin]);

   const learnerTypeLabel = (type: 'employee' | 'external') =>
      type === 'employee'
         ? text(input.user_type_employee, 'Employee (internal, free access)')
         : text(input.user_type_external, 'External (public, may pay)');

   const { data, put, setData, processing, errors, reset } = useForm({
      name: user.name,
      email: user.email,
      status: user.status,
      user_type: (user.user_type || 'external') as 'employee' | 'external',
      designation: user.instructor?.designation ?? '',
      password: '',
      password_confirmation: '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      put(route('users.update', user.id), {
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   const statusValue = data.status === 1 ? 'active' : 'inactive';

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{actionComponent}</DialogTrigger>
         <DialogContent>
            <DialogHeader>
               <DialogTitle>{dialogTitle}</DialogTitle>

               <form onSubmit={handleSubmit} className="space-y-4 text-start">
                  {isPrimaryAdmin && (
                     <p className="text-muted-foreground text-sm">
                        {text(
                           dashboard.primary_admin_edit_note,
                           'Only the name and email can be updated for the primary admin. Status, password, and deletion remain protected.',
                        )}
                     </p>
                  )}

                  <div>
                     <Label>{text(input.name, 'Name')}</Label>
                     <Input required value={data.name} onChange={(e) => setData('name', e.target.value)} />
                     <InputError message={errors.name} />
                  </div>

                  <div>
                     <Label>{text(input.email, 'Email')}</Label>
                     <Input
                        required
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder={text(input.email_placeholder, 'email@example.com')}
                     />
                     <InputError message={errors.email} />
                  </div>

                  {!isPrimaryAdmin && accountKind === 'student' && (
                     <div>
                        <Label>{text(input.user_type, 'Learner Type')}</Label>
                        <p className="text-muted-foreground mb-2 text-sm">
                           {text(
                              dashboard.learner_type_help,
                              'Internal employees get free course access. External learners may need to pay for public courses.',
                           )}
                        </p>
                        <Select
                           required
                           value={data.user_type}
                           onValueChange={(value: 'employee' | 'external') => setData('user_type', value)}
                        >
                           <SelectTrigger>
                              <SelectValue placeholder={text(dashboard.select_user_type, 'Select learner type')}>
                                 {learnerTypeLabel(data.user_type)}
                              </SelectValue>
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="employee">
                                 {text(input.user_type_employee, 'Employee (internal, free access)')}
                              </SelectItem>
                              <SelectItem value="external">
                                 {text(input.user_type_external, 'External (public, may pay)')}
                              </SelectItem>
                           </SelectContent>
                        </Select>
                        <InputError message={errors.user_type} />
                     </div>
                  )}

                  {!isPrimaryAdmin && accountKind === 'trainer' && (
                     <div>
                        <Label>{text(input.designation, 'Designation')}</Label>
                        <Input
                           required
                           value={data.designation}
                           onChange={(e) => setData('designation', e.target.value)}
                           placeholder={text(input.designation_placeholder, 'Enter designation')}
                        />
                        <InputError message={errors.designation} />
                     </div>
                  )}

                  {!isPrimaryAdmin && (
                     <div>
                        <Label>{text(input.status, 'Status')}</Label>
                        <Select
                           required
                           value={statusValue}
                           onValueChange={(value) => setData('status', value === 'active' ? 1 : 0)}
                        >
                           <SelectTrigger>
                              <SelectValue placeholder={text(dashboard.select_approval_status, 'Select the approval status')}>
                                 {statusValue === 'active' ? text(common.active, 'Active') : text(common.inactive, 'Inactive')}
                              </SelectValue>
                           </SelectTrigger>
                           <SelectContent>
                              <SelectItem value="active">{text(common.active, 'Active')}</SelectItem>
                              <SelectItem value="inactive">{text(common.inactive, 'Inactive')}</SelectItem>
                           </SelectContent>
                        </Select>
                        <InputError message={errors.status} />
                     </div>
                  )}

                  {!isPrimaryAdmin && (accountKind === 'admin' || accountKind === 'trainer') && (
                     <>
                        <div>
                           <Label>{text(dashboard.new_password_optional, 'New password (optional)')}</Label>
                           <Input
                              type="password"
                              value={data.password}
                              onChange={(e) => setData('password', e.target.value)}
                              autoComplete="new-password"
                           />
                           <InputError message={errors.password} />
                        </div>

                        <div>
                           <Label>{text(input.confirm_password, 'Confirm password')}</Label>
                           <Input
                              type="password"
                              value={data.password_confirmation}
                              onChange={(e) => setData('password_confirmation', e.target.value)}
                              autoComplete="new-password"
                           />
                           <InputError message={errors.password_confirmation} />
                        </div>
                     </>
                  )}

                  <LoadingButton loading={processing} className="w-full">
                     {text(button.submit, 'Submit')}
                  </LoadingButton>
               </form>
            </DialogHeader>
         </DialogContent>
      </Dialog>
   );
};

export default EditForm;
