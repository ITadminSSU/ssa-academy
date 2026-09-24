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
      <section className="bg-[#EFF2F9] py-16 md:py-20">
         <div className="container px-4">
            <div className="mx-auto max-w-[58rem] text-center text-[#002366]">
               <p className="font-display text-[13px] font-semibold tracking-[0.08em] uppercase sm:text-[0.9375rem]">{kicker}</p>

               <h2 className="font-display mt-2 text-[1.45rem] leading-[1.15] font-extrabold tracking-tight sm:text-[1.7rem] md:text-[1.9rem]">
                  <span className="block">{headline}</span>
                  <span className="mt-0.5 block text-[#7D0000]">{accentLine}</span>
               </h2>

               <p className="mt-6 text-[0.9375rem] leading-[1.7] italic md:text-[1rem] md:leading-[1.75]">{body}</p>

               <div className="mt-8 flex flex-col items-center gap-2">
                  <p className="font-display w-[min(100%,36rem)] bg-[#002366] px-5 py-3.5 text-[1.2rem] leading-none font-extrabold tracking-[0.02em] text-white uppercase sm:text-[1.4rem] md:text-[1.55rem]">
                     {bannerName}
                  </p>
                  <p className="font-display w-[min(100%,27rem)] bg-[#7D0000] px-5 py-3.5 text-[1.2rem] leading-none font-extrabold tracking-[0.02em] text-white uppercase sm:text-[1.4rem] md:text-[1.55rem]">
                     {bannerAction}
                  </p>
               </div>

               <div className="mt-8 space-y-1 text-[0.9375rem] leading-[1.7] italic md:text-[1rem] md:leading-[1.75]">
                  <p>{closingLead}</p>
                  <p>{closingBody}</p>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Gap;
