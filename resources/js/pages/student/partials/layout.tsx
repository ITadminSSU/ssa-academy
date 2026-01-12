import Tabs from '@/components/tabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { TabsContent } from '@/components/ui/tabs';
import useScreen from '@/hooks/use-screen';
import LandingLayout from '@/layouts/landing-layout';
import { SharedData } from '@/types/global';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FileQuestion, GraduationCap, Heart, ListFilter, LoaderCircle, Settings as SettingsIcon, UserCircle } from 'lucide-react';
import { FormEventHandler, ReactNode, useMemo, useState } from 'react';
import TabLists from './tab-lists';

const Layout = ({ children, tab }: { children: ReactNode; tab: string }) => {
   const { screen } = useScreen();
   const [open, setOpen] = useState(false);
   const { props } = usePage<SharedData>();
   const { translate, auth } = props;
   const { button } = translate;

   const tabs = useMemo(
      () => [
         {
            id: 'courses',
            name: button.courses,
            slug: 'courses',
            Icon: GraduationCap,
         },
         {
            id: 'exams',
            name: 'Exams',
            slug: 'exams',
            Icon: FileQuestion,
         },
         {
            id: 'wishlist',
            name: button.wishlist,
            slug: 'wishlist',
            Icon: Heart,
         },
         {
            id: 'profile',
            name: button.profile,
            slug: 'profile',
            Icon: UserCircle,
         },
         {
            id: 'settings',
            name: button.settings,
            slug: 'settings',
            Icon: SettingsIcon,
         },
      ],
      [],
   );

   const { post, processing } = useForm({});

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('verification.send'));
   };

   return (
      <LandingLayout customizable={false} language={true}>
         <div className="container py-6">
            <Tabs value={tab} defaultValue={tabs[0].slug} className="flex items-start gap-6 lg:gap-10">
               {screen > 768 && (
                  <Card className="sticky top-24 w-full max-w-[270px] border-none p-4">
                     <TabLists tabs={tabs} />
                  </Card>
               )}

               <div className="w-full overflow-hidden">
                  {!auth.user.email_verified_at && (
                     <div className="mb-6 rounded-md bg-red-50 p-6">
                        {status === 'verification-link-sent' ? (
                           <p className="mb-4 text-center text-sm font-medium text-green-600">
                              A new verification link has been sent to the email address you provided during registration.
                           </p>
                        ) : (
                           <p className="mb-4 text-center text-sm font-medium text-red-500">
                              Your email is not verified yet. Please verify your email address by clicking on the link we just emailed to you.
                           </p>
                        )}

                        <form onSubmit={submit} className="flex items-center justify-center gap-4 text-center">
                           <Button disabled={processing} variant="secondary">
                              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                              Resend verification email
                           </Button>

                           <Link href={route('logout')} method="post">
                              <Button>Log out</Button>
                           </Link>
                        </form>
                     </div>
                  )}

                  {tabs.map(({ id, slug }) => (
                     <TabsContent key={id} value={slug} className="m-0">
                        {screen < 768 && (
                           <Sheet open={open} onOpenChange={setOpen}>
                              <SheetTrigger asChild>
                                 <Button size="icon" variant="outline">
                                    <ListFilter className="h-5 w-5" />
                                 </Button>
                              </SheetTrigger>

                              <SheetContent side="left" className="border-border w-[230px] p-0">
                                 <ScrollArea className="h-full w-full">
                                    <TabLists tabs={tabs} />
                                 </ScrollArea>
                              </SheetContent>
                           </Sheet>
                        )}

                        {children}
                     </TabsContent>
                  ))}
               </div>
            </Tabs>
         </div>
      </LandingLayout>
   );
};

export default Layout;
