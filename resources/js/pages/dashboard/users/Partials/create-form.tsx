import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';

type AccountType = 'admin' | 'employee' | 'trainer';

const ACCOUNT_TYPE_OPTIONS: Array<{
   value: AccountType;
   labelKey: keyof LanguageTranslations['dashboard'];
   descriptionKey: keyof LanguageTranslations['dashboard'];
   fallbackLabel: string;
   fallbackDescription: string;
}> = [
   {
      value: 'admin',
      labelKey: 'account_type_admin',
      descriptionKey: 'account_type_admin_description',
      fallbackLabel: 'Admin',
      fallbackDescription: 'Full platform access',
   },
   {
      value: 'employee',
      labelKey: 'account_type_employee',
      descriptionKey: 'account_type_employee_description',
      fallbackLabel: 'Internal employee',
      fallbackDescription: 'Student access with free internal courses',
   },
   {
      value: 'trainer',
      labelKey: 'account_type_trainer',
      descriptionKey: 'account_type_trainer_description',
      fallbackLabel: 'Trainer',
      fallbackDescription: 'Instructor access to manage courses',
   },
];

const CreateForm = () => {
   const { translate } = usePage<SharedData>().props;
   const { dashboard, input, button } = translate;
   const [open, setOpen] = useState(false);

   const { data, setData, post, processing, errors, reset } = useForm({
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      account_type: 'employee' as AccountType,
      designation: '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      post(route('users.store'), {
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   const text = (value: string | undefined, fallback: string) => value?.trim() || fallback;

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>
            <Button>
               <Plus className="h-4 w-4" />
               {text(button.create_account, 'Create Account')}
            </Button>
         </DialogTrigger>

         <DialogContent>
            <DialogHeader>
               <DialogTitle>{text(dashboard.create_account, 'Create Account')}</DialogTitle>
            </DialogHeader>

            <form onSubmit={handleSubmit} className="space-y-4 text-start">
               <div>
                  <Label>{text(dashboard.account_type, 'Account type')}</Label>
                  <RadioGroup
                     value={data.account_type}
                     className="mt-2 grid gap-3"
                     onValueChange={(value: AccountType) => setData('account_type', value)}
                  >
                     {ACCOUNT_TYPE_OPTIONS.map((option) => (
                        <div key={option.value} className="flex items-start gap-3">
                           <RadioGroupItem id={`account-${option.value}`} value={option.value} className="mt-1" />
                           <div className="space-y-0.5">
                              <Label htmlFor={`account-${option.value}`} className="font-medium">
                                 {text(dashboard[option.labelKey], option.fallbackLabel)}
                              </Label>
                              <p className="text-muted-foreground text-sm">
                                 {text(dashboard[option.descriptionKey], option.fallbackDescription)}
                              </p>
                           </div>
                        </div>
                     ))}
                  </RadioGroup>
                  <InputError message={errors.account_type} />
               </div>

               <div>
                  <Label>{input.name}</Label>
                  <Input
                     required
                     value={data.name}
                     onChange={(e) => setData('name', e.target.value)}
                     placeholder={input.name_placeholder}
                  />
                  <InputError message={errors.name} />
               </div>

               <div>
                  <Label>{input.email}</Label>
                  <Input
                     required
                     type="email"
                     value={data.email}
                     onChange={(e) => setData('email', e.target.value)}
                     placeholder={input.email_placeholder}
                  />
                  <InputError message={errors.email} />
               </div>

               <div>
                  <Label>{input.password}</Label>
                  <Input
                     required
                     type="password"
                     value={data.password}
                     onChange={(e) => setData('password', e.target.value)}
                     autoComplete="new-password"
                  />
                  <InputError message={errors.password} />
               </div>

               <div>
                  <Label>{input.confirm_password}</Label>
                  <Input
                     required
                     type="password"
                     value={data.password_confirmation}
                     onChange={(e) => setData('password_confirmation', e.target.value)}
                     autoComplete="new-password"
                  />
                  <InputError message={errors.password_confirmation} />
               </div>

               {data.account_type === 'trainer' && (
                  <div>
                     <Label>{input.designation}</Label>
                     <Input
                        required
                        value={data.designation}
                        onChange={(e) => setData('designation', e.target.value)}
                        placeholder={input.designation_placeholder}
                     />
                     <InputError message={errors.designation} />
                  </div>
               )}

               <p className="text-muted-foreground text-sm">
                  {text(
                     dashboard.create_account_note,
                     'Email is marked verified and legal agreements are accepted automatically. Share the password securely with the user.',
                  )}
               </p>

               <LoadingButton loading={processing} className="w-full">
                  {text(button.create_account, 'Create Account')}
               </LoadingButton>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default CreateForm;
