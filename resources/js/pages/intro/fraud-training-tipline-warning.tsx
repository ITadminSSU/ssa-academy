import LandingLayout from '@/layouts/landing-layout';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';

type Warning = {
   id: number;
   link: string | null;
   public_note: string | null;
   confirmed_at: string | null;
   share_url: string | null;
};

export default function FraudTrainingTiplineWarningPage({ warning }: { warning: Warning }) {
   return (
      <LandingLayout>
         <Head title="Community scam warning" />

         <section className="container max-w-3xl py-14 sm:py-20">
            <p className="text-xs font-semibold tracking-[0.16em] text-[#8C2A23] uppercase">Fraud Training Tipline</p>
            <h1 className="mt-3 text-3xl font-semibold tracking-tight text-[#01123A]">Community warning</h1>
            <p className="mt-3 text-muted-foreground">
               This site was reviewed by our team and added to the confirmed warnings list. Treat this as a community safety notice.
            </p>

            <article className="mt-8 rounded-xl border border-border/70 bg-white p-6 shadow-sm">
               <a
                  href={warning.link || '#'}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="block break-all text-lg font-medium text-[#01123A] hover:underline"
               >
                  {warning.link || 'Link unavailable'}
               </a>
               {warning.public_note && <p className="mt-3 text-sm text-muted-foreground">{warning.public_note}</p>}
               {warning.confirmed_at && <p className="mt-4 text-xs text-muted-foreground">Confirmed {warning.confirmed_at}</p>}
            </article>

            <div className="mt-8 flex flex-wrap gap-3">
               <Button asChild>
                  <Link href={route('fraud-training-tipline')}>Back to Tipline</Link>
               </Button>
               <Button asChild variant="brand">
                  <Link href={route('fraud-training-tipline') + '#leave-a-tip'}>Report another site</Link>
               </Button>
            </div>
         </section>
      </LandingLayout>
   );
}
