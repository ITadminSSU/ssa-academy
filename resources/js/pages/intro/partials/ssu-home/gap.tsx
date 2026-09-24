import { getPageSection } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const defaults = {
   kicker: 'THE GAP IS WIDENING',
   headline: 'EVERYONE HAS A TALENT.',
   accentLine: 'VERY FEW HAS U.S. EXPERIENCE.',
   body: 'Global talent is everywhere, but US-specific market expertise is rare. Workflows, compliance, and tools have evolved rapidly. Most companies are trying to bridge this gap with traditional outsourcing that lacks local readiness.',
   bannerName: 'SMARTSOURCING USA ACADEMY',
   bannerAction: 'CLOSES THAT GAP.',
   closingLead: '100% focused on U.S. construction readiness.',
   closingBody:
      'Built by industry veterans who spent decades inside U.S. job sites and project management, training global professionals to be plug-and-play on day one.',
};

const Gap = () => {
   const { props } = usePage<IntroPageProps>();
   const section = props.page?.sections ? getPageSection(props.page, 'gap') : undefined;
   const properties = section?.properties ?? {};

   const kicker = section?.title?.trim() || defaults.kicker;
   const headline = section?.sub_title?.trim() || defaults.headline;
   const accentLine = String(properties.accent_line ?? '').trim() || defaults.accentLine;
   const body = section?.description?.trim() || defaults.body;
   const bannerName = String(properties.banner_name ?? '').trim() || defaults.bannerName;
   const bannerAction = String(properties.banner_action ?? '').trim() || defaults.bannerAction;
   const closingLead = String(properties.closing_lead ?? '').trim() || defaults.closingLead;
   const closingBody = String(properties.closing_body ?? '').trim() || defaults.closingBody;

   return (
      <section className="bg-white py-16 md:py-20 dark:bg-background">
         <div className="container px-4">
            <div className="mx-auto max-w-3xl space-y-8 text-center">
               <div className="space-y-4">
                  <p className="text-primary text-[0.7rem] font-semibold tracking-[0.28em] uppercase sm:text-xs">{kicker}</p>

                  <h2 className="font-display text-primary space-y-1 text-[1.65rem] leading-tight font-extrabold tracking-tight sm:text-3xl md:text-4xl">
                     <span className="block">{headline}</span>
                     <span className="text-accent block">{accentLine}</span>
                  </h2>

                  <p className="text-primary/80 mx-auto max-w-2xl text-sm leading-relaxed sm:text-base">{body}</p>
               </div>

               <div className="mx-auto max-w-xl overflow-hidden">
                  <p className="bg-primary font-display px-5 py-3 text-sm font-bold tracking-[0.12em] text-white uppercase sm:text-base">
                     {bannerName}
                  </p>
                  <p className="bg-accent font-display px-5 py-3 text-sm font-bold tracking-[0.12em] text-white uppercase sm:text-base">
                     {bannerAction}
                  </p>
               </div>

               <div className="text-primary/80 mx-auto max-w-2xl space-y-2 text-sm leading-relaxed sm:text-base">
                  <p className="font-medium">{closingLead}</p>
                  <p>{closingBody}</p>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Gap;
