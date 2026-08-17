import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

export default function VerifyEmail({
   status,
   email,
   expireMinutes = 15,
   hasLiveCode = false,
   resendAvailableIn = 0,
}: {
   status?: string;
   email?: string;
   expireMinutes?: number;
   hasLiveCode?: boolean;
   resendAvailableIn?: number;
}) {
   const { props } = usePage<SharedData>();
   const { auth, button } = props.translate;
   const userEmail = email || props.auth.user?.email;
   const [cooldown, setCooldown] = useState(Math.max(0, resendAvailableIn));

   const verifyForm = useForm({ code: '' });
   const resendForm = useForm({});

   useEffect(() => {
      setCooldown(Math.max(0, resendAvailableIn));
   }, [resendAvailableIn]);

   useEffect(() => {
      if (cooldown <= 0) {
         return;
      }

      const timer = window.setInterval(() => {
         setCooldown((seconds) => Math.max(0, seconds - 1));
      }, 1000);

      return () => window.clearInterval(timer);
   }, [cooldown]);

   useEffect(() => {
      if (props.auth.user?.email_verified_at && props.auth.dashboardUrl) {
         router.visit(props.auth.legalAgreementRequired ? props.auth.legalAgreementUrl : props.auth.dashboardUrl);
      }
   }, [props.auth.dashboardUrl, props.auth.legalAgreementRequired, props.auth.legalAgreementUrl, props.auth.user?.email_verified_at]);

   const submitCode: FormEventHandler = (e) => {
      e.preventDefault();

      verifyForm.post(route('verification.verify'), {
         preserveScroll: true,
      });
   };

   const resend: FormEventHandler = (e) => {
      e.preventDefault();

      if (cooldown > 0 || resendForm.processing) {
         return;
      }

      resendForm.post(route('verification.send'), {
         preserveScroll: true,
      });
   };

   return (
      <AuthLayout title={auth.verify_title} description={auth.verify_description}>
         <Head title={auth.verify_title} />

         {userEmail && (
            <p className="mb-4 text-center text-sm">
               We sent a 6-digit code to <span className="font-semibold">{userEmail}</span>.
            </p>
         )}

         <p className="text-muted-foreground mb-4 text-center text-sm">
            The code is valid for {expireMinutes} minutes. Check spam, junk, and promotions if you do not see it.
         </p>

         {!hasLiveCode && (
            <p className="mb-4 text-center text-sm text-amber-700 dark:text-amber-400">
               Request a new code if you do not have one yet, or if the last code expired.
            </p>
         )}

         {status === 'verification-code-sent' && (
            <div className="mb-4 text-center text-sm font-medium text-green-600">{auth.verification_sent}</div>
         )}

         <form onSubmit={submitCode} className="space-y-4">
            <div>
               <Input
                  id="code"
                  name="code"
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  autoFocus
                  maxLength={6}
                  placeholder="000000"
                  value={verifyForm.data.code}
                  onChange={(e) => verifyForm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                  className="text-center font-mono text-2xl tracking-[0.4em]"
               />
               <InputError message={verifyForm.errors.code} className="mt-2 text-center" />
            </div>

            <Button type="submit" disabled={verifyForm.processing || verifyForm.data.code.length !== 6} className="w-full">
               {verifyForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
               {button.verify_and_continue || 'Verify and continue'}
            </Button>
         </form>

         <form onSubmit={resend} className="mt-4">
            <Button type="submit" disabled={resendForm.processing || cooldown > 0} variant="secondary" className="w-full">
               {resendForm.processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
               {cooldown > 0
                  ? `Resend code in ${cooldown}s`
                  : button.resend_verification_email || 'Resend verification email'}
            </Button>
         </form>

         <Button
            type="button"
            variant="outline"
            className="mx-auto mt-4 block w-full"
            onClick={() => router.post(route('logout'))}
         >
            {button.logout}
         </Button>
      </AuthLayout>
   );
}
