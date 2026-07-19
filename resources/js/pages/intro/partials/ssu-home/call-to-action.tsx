import { Button } from '@/components/ui/button';
import { getPageSection } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';

const CallToAction = () => {
   const { props } = usePage<IntroPageProps>();
   const ctaSection = getPageSection(props.page, 'call_to_action');

   return (
      <section className="py-20">
         <div className="container px-4">
            <div className="relative overflow-hidden rounded-2xl bg-[oklch(0.22_0.04_255)] px-6 py-14 text-center text-white md:px-12">
               <div className="bg-primary/30 pointer-events-none absolute -top-10 right-0 h-40 w-40 rounded-full blur-3xl" />
               <div className="pointer-events-none absolute bottom-0 left-0 h-40 w-40 rounded-full bg-[oklch(0.55_0.18_25)]/40 blur-3xl" />

               <div className="relative mx-auto max-w-2xl space-y-5">
                  {ctaSection?.sub_title && <p className="ssu-kicker !text-white/70">{ctaSection.sub_title}</p>}
                  {ctaSection?.title && <h2 className="font-display text-2xl font-bold md:text-3xl">{ctaSection.title}</h2>}
                  {ctaSection?.description && <p className="text-white/80">{ctaSection.description}</p>}

                  {ctaSection?.properties?.button_text && (
                     <Button asChild size="lg" className="bg-primary hover:bg-primary/90 mt-4 rounded-full px-10">
                        <Link href={ctaSection.properties.button_link || route('register')}>{ctaSection.properties.button_text}</Link>
                     </Button>
                  )}
               </div>
            </div>
         </div>
      </section>
   );
};

export default CallToAction;
