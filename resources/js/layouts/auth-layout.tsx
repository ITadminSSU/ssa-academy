import AppLogo from '@/components/app-logo';
import { BRAND_TAGLINE } from '@/lib/branding';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import Main from './main';

interface Props {
   title: string;
   description: string;
   children: React.ReactNode;
}

const AuthLayout = ({ children, title, description }: Props) => {
   const { branding } = usePage<SharedData>().props;

   return (
      <Main>
         <div className="ssu-page-shell grid min-h-svh lg:grid-cols-2">
            <div className="ssu-auth-hero">
               <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.14),transparent_50%)]" />
               <div className="bg-primary/20 pointer-events-none absolute -right-20 -bottom-20 h-64 w-64 rounded-full blur-3xl" />
               <div className="pointer-events-none absolute -top-16 -left-10 h-48 w-48 rounded-full bg-[color:var(--brand-red)]/25 blur-3xl" />

               <div className="ssu-auth-hero__logo relative z-10">
                  <Link href={route('home')} className="ssu-auth-logo block">
                     <AppLogo className="ssu-auth-logo-colored" theme="dark" />
                  </Link>
               </div>

               <div className="ssu-auth-hero__footer relative z-10">
                  <div className="max-w-md space-y-3">
                     <h2 className="font-display text-3xl leading-tight font-semibold tracking-tight">{branding.name}</h2>
                     <p className="text-base leading-relaxed text-white/80">{branding.tagline || BRAND_TAGLINE}</p>
                  </div>

                  <p className="mt-8 text-sm text-white/60">
                     © {new Date().getFullYear()} {branding.author}
                  </p>
               </div>
            </div>

            <div className="flex flex-col items-center justify-center p-6 md:p-10">
               <Link
                  href={route('home')}
                  className="ssu-auth-mobile-brand mb-8 block w-full max-w-sm lg:hidden"
               >
                  <AppLogo className="ssu-auth-logo-mobile ssu-auth-logo-colored w-full" theme="dark" />
               </Link>

               <div className="ssu-auth-panel">
                  <div className="space-y-2">
                     <p className="ssu-kicker">Account</p>
                     <h1 className="font-display text-2xl font-semibold tracking-tight">{title}</h1>
                     <p className="text-muted-foreground text-sm">{description}</p>
                  </div>

                  {children}
               </div>
            </div>
         </div>
      </Main>
   );
};

export default AuthLayout;
