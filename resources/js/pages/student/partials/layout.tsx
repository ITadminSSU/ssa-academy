import { Button } from '@/components/ui/button';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Link, useForm, usePage } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler, ReactNode } from 'react';

const Layout = ({ children, tab }: { children: ReactNode; tab: string }) => {
   const { props } = usePage<SharedData>();
   const { translate, auth } = props;
   const { frontend } = translate;

   const { post, processing } = useForm({});

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('verification.send'));
   };

   return (
      <DashboardLayout variant="learner" headTitle={frontend.student_dashboard}>
         <div className="space-y-6">
            {!auth.user?.email_verified_at && (
               <div className="border-destructive/20 bg-destructive/5 rounded-xl border p-6">
                  <p className="text-destructive mb-4 text-center text-sm font-medium">
                     Your email is not verified yet. Please verify your email address using the link we sent you.
                  </p>

                  <form onSubmit={submit} className="flex items-center justify-center gap-4 text-center">
                     <Button disabled={processing} variant="secondary">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Resend verification email
                     </Button>

                     <Link href={route('logout')} method="post">
                        <Button variant="outline">Log out</Button>
                     </Link>
                  </form>
               </div>
            )}

            {children}
         </div>
      </DashboardLayout>
   );
};

export default Layout;
