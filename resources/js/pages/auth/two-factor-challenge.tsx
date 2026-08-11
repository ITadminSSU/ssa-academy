import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import TextLink from '@/components/text-link';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
   status?: string;
}

export default function TwoFactorChallenge({ status }: Props) {
   const { props } = usePage<SharedData>();
   const { auth: authLang, button, input } = props.translate;

   const { data, setData, post, processing, errors } = useForm({
      code: '',
   });

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('two-factor.challenge.store'));
   };

   return (
      <AuthLayout title={authLang.two_factor_title} description={authLang.two_factor_description}>
         <Head title={authLang.two_factor_title} />

         {status && <div className="mb-4 text-sm font-medium text-green-600">{status}</div>}

         <form className="flex flex-col gap-6" onSubmit={submit}>
            <div className="grid gap-2">
               <Label htmlFor="code">{input.two_factor_code}</Label>
               <Input
                  id="code"
                  type="text"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  required
                  autoFocus
                  value={data.code}
                  placeholder={input.two_factor_code_placeholder}
                  onChange={(e) => setData('code', e.target.value)}
               />
               <InputError message={errors.code} />
               <p className="text-muted-foreground text-sm">{authLang.two_factor_recovery_hint}</p>
            </div>

            <LoadingButton loading={processing} className="w-full" tabIndex={2}>
               {button.verify_and_continue}
            </LoadingButton>

            <div className="text-muted-foreground text-center text-sm">
               <TextLink href={route('logout')} method="post" as="button">
                  {button.logout}
               </TextLink>
            </div>
         </form>
      </AuthLayout>
   );
}
