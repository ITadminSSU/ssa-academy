import LandingLayout from '@/layouts/landing-layout';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

const ThankYou = ({ enrollUrl }: { enrollUrl: string }) => {
   return (
      <div className="drywall-landing">
         <Head title="Check your email">
            <meta name="robots" content="noindex, nofollow" />
            <meta name="description" content="We sent the Drywall Estimating course details." />
         </Head>

         <section className="mx-auto flex min-h-[60vh] max-w-xl flex-col items-center justify-center px-6 py-20 text-center">
            <p className="text-xs font-extrabold tracking-[0.16em] text-[#c2410c] uppercase">Drywall Estimating</p>
            <h1 className="mt-4 text-4xl text-[#10243f] md:text-5xl">Check your email.</h1>
            <p className="mt-4 text-lg leading-relaxed text-[#3d4c5e]">We sent the Drywall Estimating course details.</p>
            <Link href={enrollUrl} className="drywall-enroll mt-8">
               Ready to enroll
            </Link>
         </section>
      </div>
   );
};

ThankYou.layout = (page: ReactNode) => <LandingLayout children={page} customizable={false} />;

export default ThankYou;
