import Main from '@/layouts/main';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

const PageNotFound = () => {
   return (
      <>
         <Head title="Page Not Found" />

         <div className="relative flex min-h-screen items-center justify-center overflow-hidden">
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top,#1f9be1,transparent_45%)]" />
            <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom,#f43f5e,transparent_35%)]" />

            <div className="relative mx-auto w-full max-w-3xl px-6 py-10 text-center">
               <p className="text-sm tracking-[0.35em] uppercase">Oops! something went wrong</p>
               <h1 className="font-heading mt-4 mb-6 text-7xl leading-none font-black sm:text-8xl">404</h1>
               <p className="text-muted-foreground mx-auto mb-10 max-w-2xl text-lg">
                  The page you&apos;re looking for doesn&apos;t exist or may have been moved. Let&apos;s get you back on track.
               </p>
               <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                  <Link
                     href="/"
                     className="inline-flex items-center justify-center rounded-full bg-white px-6 py-3 font-semibold text-slate-900 shadow-lg shadow-slate-900/30 transition hover:-translate-y-0.5"
                  >
                     Return Home
                  </Link>
               </div>
            </div>
         </div>
      </>
   );
};

PageNotFound.layout = (page: ReactNode) => <Main children={page} />;

export default PageNotFound;
