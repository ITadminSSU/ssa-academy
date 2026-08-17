import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';

export default function VerifyEmail({
   status,
   email,
   expireHours = 24,
}: {
   status?: string;
   email?: string;
   expireHours?: number;
}) {
   const { props } = usePage<SharedData>();
   const { auth, button } = props.translate;
   const { post, processing } = useForm({});
   const userEmail = email || props.auth.user?.email;

   useEffect(() => {
      if (props.auth.user?.email_verified_at && props.auth.dashboardUrl) {
         router.visit(props.auth.legalAgreementRequired ? props.auth.legalAgreementUrl : props.auth.dashboardUrl);
      }
   }, [props.auth.dashboardUrl, props.auth.legalAgreementRequired, props.auth.legalAgreementUrl, props.auth.user?.email_verified_at]);

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('verification.send'), {
         preserveScroll: true,
      });
   };

   return (
      <AuthLayout title={auth.verify_title} description={auth.verify_description}>
         <Head title={auth.verify_title} />

         {userEmail && (
            <p className="mb-4 text-center text-sm">
               We sent a verification link to <span className="font-semibold">{userEmail}</span>.
            </p>
         )}

         <p className="text-muted-foreground mb-4 text-center text-sm">
            The link is valid for {expireHours} {expireHours === 1 ? 'hour' : 'hours'}. Check spam, junk, and promotions if
            you do not see it.
         </p>

         {status === 'verification-link-sent' && (
            <div className="mb-4 text-center text-sm font-medium text-green-600">{auth.verification_sent}</div>
         )}

         <form onSubmit={submit} className="space-y-6 text-center">
            <Button type="submit" disabled={processing} variant="secondary" className="w-full">
               {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
               {button.resend_verification_email || button.submit}
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
