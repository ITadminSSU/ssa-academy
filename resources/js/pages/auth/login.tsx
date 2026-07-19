import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import ReCAPTCHA from 'react-google-recaptcha';

interface TestAccount {
   role: string;
   email: string;
   password: string;
}

interface LoginProps {
   status?: string;
   canResetPassword: boolean;
   googleLogIn: boolean;
   recaptcha: {
      status: boolean;
      siteKey: string;
      secretKey: string;
   };
   testAccounts?: TestAccount[] | null;
}

export default function Login({ status, recaptcha, canResetPassword, googleLogIn, testAccounts }: LoginProps) {
   const { props } = usePage<SharedData>();
   const { auth, input, button } = props.translate;
   const recaptchaRef = useRef<ReCAPTCHA | null>(null);

   const { data, setData, post, processing, errors, reset } = useForm({
      email: '',
      password: '',
      remember: false as boolean,
      recaptcha: '',
      recaptcha_status: recaptcha.status,
   });

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('login'), {
         onFinish: () => reset('password', 'recaptcha'),
         onError: () => {
            // Reset reCAPTCHA when there's an error
            if (recaptchaRef.current) {
               recaptchaRef.current.reset();
            }
         },
      });
   };

   return (
      <AuthLayout title={auth.login_title} description={auth.login_description}>
         <Head title={auth.login_title} />

         {testAccounts && testAccounts.length > 0 && (
            <div className="border-amber-500/30 bg-amber-500/10 rounded-xl border p-4 text-sm">
               <p className="mb-2 font-semibold text-amber-900 dark:text-amber-100">Test accounts (UAT)</p>
               <ul className="space-y-2 text-amber-950/90 dark:text-amber-50/90">
                  {testAccounts.map((account) => (
                     <li key={account.email} className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <span>
                           <span className="font-medium">{account.role}:</span> {account.email} / {account.password}
                        </span>
                        <Button
                           type="button"
                           size="sm"
                           variant="outline"
                           className="border-amber-500/40 h-8"
                           onClick={() => {
                              setData('email', account.email);
                              setData('password', account.password);
                           }}
                        >
                           Use this account
                        </Button>
                     </li>
                  ))}
               </ul>
            </div>
         )}

         <form className="flex flex-col gap-6" onSubmit={submit}>
            <div className="grid gap-6">
               <div className="grid gap-2">
                  <Label htmlFor="email">{input.email}</Label>
                  <Input
                     id="email"
                     type="email"
                     required
                     autoFocus
                     tabIndex={1}
                     autoComplete="email"
                     value={data.email}
                     onChange={(e) => setData('email', e.target.value)}
                     placeholder={input.email_placeholder}
                  />
                  <InputError message={errors.email} />
               </div>

               <div className="grid gap-2">
                  <div className="flex items-center">
                     <Label htmlFor="password">{input.password}</Label>
                     {canResetPassword && (
                        <TextLink href={route('password.request')} className="ml-auto text-sm" tabIndex={5}>
                           {auth.forgot_password}
                        </TextLink>
                     )}
                  </div>
                  <Input
                     id="password"
                     type="password"
                     required
                     tabIndex={2}
                     autoComplete="current-password"
                     value={data.password}
                     onChange={(e) => setData('password', e.target.value)}
                     placeholder={input.password_placeholder}
                  />
                  <InputError message={errors.password} />
               </div>

               {recaptcha.status && (
                  <div>
                     <ReCAPTCHA ref={recaptchaRef} sitekey={recaptcha.siteKey} onChange={(token) => setData('recaptcha', token as string)} />
                     <InputError message={errors.recaptcha} />
                  </div>
               )}

               <div className="flex items-center space-x-3">
                  <Checkbox id="remember" name="remember" checked={data.remember} onClick={() => setData('remember', !data.remember)} tabIndex={3} />
                  <Label htmlFor="remember">{input.remember_me}</Label>
               </div>

               <LoadingButton loading={processing} type="submit" className="w-full">
                  {button.login}
               </LoadingButton>

               {googleLogIn && (
                  <>
                     <div className="after:border-border relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t">
                        <span className="bg-background text-muted-foreground relative z-10 px-2">{auth.continue_with}</span>
                     </div>

                     <a type="button" className="w-full" href="auth/google">
                        <Button type="button" variant="outline" className="w-full">
                           {button.continue_with_google}
                        </Button>
                     </a>
                  </>
               )}
            </div>
            <div className="text-muted-foreground text-center text-sm">
               {auth.login_external_register_note}{' '}
               <Link href={route('register')} className="text-foreground font-medium underline underline-offset-4">
                  {button.sign_up}
               </Link>
            </div>
         </form>

         {status && <div className="text-primary text-center text-sm font-medium">{status}</div>}
      </AuthLayout>
   );
}
