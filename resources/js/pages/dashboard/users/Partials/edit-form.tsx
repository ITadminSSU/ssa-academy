import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { ReactNode, useMemo, useState } from 'react';

type AccountType = 'admin' | 'operations' | 'employee' | 'trainer' | 'external';

const ACCOUNT_TYPE_OPTIONS: Array<{
   value: AccountType;
   labelKey: keyof LanguageTranslations['dashboard'];
   fallbackLabel: string;
}> = [
   { value: 'admin', labelKey: 'account_type_admin', fallbackLabel: 'Admin (full platform access)' },
   { value: 'operations', labelKey: 'account_type_operations', fallbackLabel: 'Operations Admin' },
   { value: 'employee', labelKey: 'account_type_employee', fallbackLabel: 'Internal employee (student access, free courses)' },
   { value: 'external', labelKey: 'account_type_external', fallbackLabel: 'External learner (student access, may pay)' },
   { value: 'trainer', labelKey: 'account_type_trainer', fallbackLabel: 'Trainer (instructor access)' },
];

const currentAccountType = (user: User): AccountType => {
   if (user.role === 'admin') {
      return user.can_manage_platform_settings === false ? 'operations' : 'admin';
   }

   if (user.role === 'instructor') {
      return 'trainer';
   }

   return user.user_type === 'employee' ? 'employee' : 'external';
};

interface Props {
   user: User;
   actionComponent: ReactNode;
   protectedUserId?: number | null;
}

const EditForm = ({ user, actionComponent, protectedUserId }: Props) => {
   const { props } = usePage<SharedData>();
   const { translate, auth } = props;
   const canAssignAdminAccess = Boolean(auth.canManagePlatformSettings);
   const { dashboard, input, button, common } = translate;
   const [open, setOpen] = useState(false);

   const text = (value: string | undefined, fallback: string) => value?.trim() || fallback;
   const isPrimaryAdmin = protectedUserId != null && user.id === protectedUserId;
   const isSelf = auth.user?.id === user.id;
   const canChangeRole = canAssignAdminAccess && !isPrimaryAdmin && !isSelf;

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

   const initialData = () => ({
      name: user.name,
      email: user.email,
      status: user.status,
      user_type: (user.user_type || 'external') as 'employee' | 'external',
      designation: user.instructor?.designation ?? '',
      account_type: currentAccountType(user),
      password: '',
      password_confirmation: '',
   });

   const { data, put, setData, processing, errors, setDefaults, clearErrors } = useForm(initialData());

   const restoreSavedValues = () => {
      const fresh = initialData();
      setDefaults(fresh);
      setData(fresh);
      clearErrors();
   };

   const handleOpenChange = (nextOpen: boolean) => {
      restoreSavedValues();
      setOpen(nextOpen);
   };

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      put(route('users.update', user.id), {
         preserveScroll: true,
         transform: (payload) => {
            if (canChangeRole) {
               return payload;
            }

            const { account_type: _ignored, ...rest } = payload;

            return rest;
         },
         onSuccess: () => {
            setOpen(false);
            restoreSavedValues();
         },
      });
   };

   const statusValue = data.status === 1 ? 'active' : 'inactive';
   const selectedAccountType = data.account_type;
   const selectedOption = ACCOUNT_TYPE_OPTIONS.find((option) => option.value === selectedAccountType);
   const showDesignation = canChangeRole ? selectedAccountType === 'trainer' : !isPrimaryAdmin && accountKind === 'trainer';
   const showLearnerType = !canChangeRole && !isPrimaryAdmin && accountKind === 'student';
   const showPasswordFields =
      !isPrimaryAdmin &&
      (canChangeRole ? ['admin', 'operations', 'trainer'].includes(selectedAccountType) : accountKind === 'admin' || accountKind === 'trainer');

   return (
      <Dialog open={open} onOpenChange={handleOpenChange}>
         <DialogTrigger asChild>{actionComponent}</DialogTrigger>
         <DialogContent>
            <DialogHeader>
               <DialogTitle>{dialogTitle}</DialogTitle>
            </DialogHeader>

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

               {!isPrimaryAdmin && canChangeRole && (
                  <div>
                     <Label>{text(dashboard.account_type, 'Account type')}</Label>
                     <p className="text-muted-foreground mb-2 text-sm">
                        {text(
                           dashboard.account_type_edit_help,
                           'Only full admins can change account type. You cannot change your own account type.',
                        )}
                     </p>
                     <Select required value={selectedAccountType} onValueChange={(value: AccountType) => setData('account_type', value)}>
                        <SelectTrigger>
                           <SelectValue>
                              {text(dashboard[selectedOption?.labelKey ?? 'account_type'], selectedOption?.fallbackLabel ?? selectedAccountType)}
                           </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                           {ACCOUNT_TYPE_OPTIONS.map((option) => (
                              <SelectItem key={option.value} value={option.value}>
                                 {text(dashboard[option.labelKey], option.fallbackLabel)}
                              </SelectItem>
                           ))}
                        </SelectContent>
                     </Select>
                     <InputError message={errors.account_type} />
                  </div>
               )}

               {showLearnerType && (
                  <div>
                     <Label>{text(input.user_type, 'Learner Type')}</Label>
                     <p className="text-muted-foreground mb-2 text-sm">
                        {text(
                           dashboard.learner_type_help,
                           'Internal employees get free course access. External learners may need to pay for public courses.',
                        )}
                     </p>
                     <Select required value={data.user_type} onValueChange={(value: 'employee' | 'external') => setData('user_type', value)}>
                        <SelectTrigger>
                           <SelectValue placeholder={text(dashboard.select_user_type, 'Select learner type')}>
                              {learnerTypeLabel(data.user_type)}
                           </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="employee">{text(input.user_type_employee, 'Employee (internal, free access)')}</SelectItem>
                           <SelectItem value="external">{text(input.user_type_external, 'External (public, may pay)')}</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.user_type} />
                  </div>
               )}

               {showDesignation && (
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
                     <p className="text-muted-foreground mb-2 text-sm">
                        Inactive accounts cannot sign in. Use this for suspicious emails, unpaid fake signups, or a CV that does not match the name.
                     </p>
                     <Select required value={statusValue} onValueChange={(value) => setData('status', value === 'active' ? 1 : 0)}>
                        <SelectTrigger>
                           <SelectValue placeholder={text(dashboard.select_approval_status, 'Select the approval status')}>
                              {statusValue === 'active' ? text(common.active, 'Active') : text(common.inactive, 'Inactive')}
                           </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                           <SelectItem value="active">{text(common.active, 'Active')}</SelectItem>
                           <SelectItem value="inactive">{text(common.inactive, 'Inactive — cannot sign in')}</SelectItem>
                        </SelectContent>
                     </Select>
                     <InputError message={errors.status} />
                  </div>
               )}

               {showPasswordFields && (
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
         </DialogContent>
      </Dialog>
   );
};

export default EditForm;
