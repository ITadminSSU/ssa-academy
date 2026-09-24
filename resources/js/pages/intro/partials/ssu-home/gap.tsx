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
      <section className="py-16 md:py-20">
         <div className="container px-4">
            <div className="mx-auto max-w-[44rem] text-center">
               <p className="font-display text-primary text-[11px] font-semibold tracking-[0.22em] uppercase sm:text-xs">
                  {kicker}
               </p>

               <h2 className="font-display text-primary mt-3 text-[1.65rem] leading-[1.15] font-extrabold tracking-tight sm:text-[1.9rem] md:text-[2.15rem]">
                  <span className="block">{headline}</span>
                  <span className="text-accent mt-1 block">{accentLine}</span>
               </h2>

               <p className="text-primary mx-auto mt-5 max-w-[40rem] text-[0.9375rem] leading-[1.7] italic md:text-[1rem] md:leading-[1.75]">
                  {body}
               </p>

               <div className="mt-8 flex flex-col items-center gap-1.5">
                  <p className="bg-primary font-display w-fit px-8 py-2.5 text-[0.95rem] font-bold tracking-[0.12em] text-white uppercase sm:px-10 sm:text-[1.05rem]">
                     {bannerName}
                  </p>
                  <p className="bg-accent font-display w-fit px-8 py-2.5 text-[0.95rem] font-bold tracking-[0.12em] text-white uppercase sm:px-10 sm:text-[1.05rem]">
                     {bannerAction}
                  </p>
               </div>

               <div className="text-primary mx-auto mt-8 max-w-[36rem] space-y-1 text-[0.9375rem] leading-[1.7] italic md:text-[1rem] md:leading-[1.75]">
                  <p>{closingLead}</p>
                  <p>{closingBody}</p>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Gap;
