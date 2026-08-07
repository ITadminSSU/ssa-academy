import InputError from '@/components/input-error';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
import LoadingButton from '../loading-button';

const ChangeEmail = () => {
   const { props } = usePage<SharedData>();
   const { email } = props.auth.user;
   const { errors, translate } = props;
   const { auth, button, input } = translate;

   const { data, setData, post, processing, reset } = useForm({
      current_email: email,
      current_password: '',
      new_email: '',
   });

   const onHandleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
      setData(event.target.name as 'current_email' | 'current_password' | 'new_email', event.target.value);
   };

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('account.change-email'), {
         preserveScroll: true,
         onSuccess: () => {
            reset('current_password', 'new_email');
         },
      });
   };

   return (
      <Card className="border-none">
         <div className="border-b-border border-b px-7 pt-7 pb-4">
            <p className="text18 font-bold">{auth.change_email}</p>
         </div>
         <form onSubmit={submit} className="px-7 py-8">
            <div>
               <Label>{input.current_email}</Label>

               <Input required readOnly type="email" name="current_email" value={data.current_email} placeholder={input.current_email_placeholder} />

               <InputError message={errors.current_email} className="mt-2" />
            </div>

            <div className="pt-5">
               <Label>{input.current_password}</Label>

               <Input
                  required
                  type="password"
                  name="current_password"
                  value={data.current_password}
                  placeholder={input.current_password_placeholder}
                  onChange={onHandleChange}
                  autoComplete="current-password"
               />

               <InputError message={errors.current_password} className="mt-2" />
            </div>

            <div className="py-5">
               <Label>{input.new_email}</Label>

               <Input
                  required
                  type="email"
                  name="new_email"
                  value={data.new_email}
                  placeholder={input.new_email_placeholder}
                  onChange={onHandleChange}
                  autoComplete="email"
               />

               <InputError message={errors.new_email} className="mt-2" />
            </div>

            <LoadingButton loading={processing}>{button.get_email_change_link}</LoadingButton>

            <p className="text-muted-foreground mt-4 text-sm">
               Enter your current password to confirm this request. We will email a verification link to your new address
               and send a security alert to your current email. After you confirm, you will need to log in again with the
               new email.
            </p>
         </form>
      </Card>
   );
};

export default ChangeEmail;
