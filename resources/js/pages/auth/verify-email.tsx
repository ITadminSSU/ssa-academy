import { Head, router, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, useEffect } from 'react';

import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';

export default function Recaptcha({ status }: { status?: string }) {
   const { props } = usePage<SharedData>();
   const { auth, button } = props.translate;
   const { post, processing } = useForm({});

   useEffect(() => {
      if (props.auth.user?.email_verified_at && props.auth.dashboardUrl) {
         router.visit(props.auth.dashboardUrl);
      }
   }, [props.auth.dashboardUrl, props.auth.user?.email_verified_at]);

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('verification.send'), {
         onSuccess: () => router.reload({ only: ['auth'] }),
      });
   };

   return (
      <AuthLayout title={auth.verify_title} description={auth.verify_description}>
         <Head title={auth.verify_title} />

         {status === 'verification-link-sent' && <div className="mb-4 text-center text-sm font-medium text-green-600">{auth.verification_sent}</div>}

         <form onSubmit={submit} className="space-y-6 text-center">
            <Button type="submit" disabled={processing} variant="secondary" className="w-full">
               {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
               {button.submit}
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
