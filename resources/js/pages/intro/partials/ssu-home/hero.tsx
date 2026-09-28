import HeroVideoPlayer from '@/components/hero-video-player';
import SamplePlan from '@/components/ssu-public/sample-plan';
import { GhostCta, GoldCta } from '@/components/ssu-public/chrome';
import { getPageSection } from '@/lib/page';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');
   const videoUrl = heroSection?.video_url?.trim() || null;
   const posterUrl = heroSection?.thumbnail?.trim() || null;

   return (
      <section id="academy">
         <div className="relative overflow-hidden bg-[color:var(--ssu-navy)] text-white">
            <div className="pointer-events-none absolute inset-0" aria-hidden>
               <div className="absolute -right-16 top-[-8rem] h-[36rem] w-[36rem] rounded-full border border-[color:var(--ssu-gold)]/25" />
               <div className="absolute right-[-2rem] top-8 h-[28rem] w-[28rem] rounded-full border border-[color:var(--ssu-gold)]/35" />
            </div>

            <div className="relative container grid items-center gap-10 px-4 pt-12 pb-6 lg:grid-cols-[1.08fr_0.92fr] lg:pt-16 lg:pb-8">
               <div className="max-w-3xl">
                  <p className="ssu-pub-kicker ssu-pub-kicker--gold">
                     <span className="ssu-pub-kicker-line" aria-hidden />
                     SmartSourcing USA / Academy
                  </p>
                  <h1 className="ssu-pub-display mt-5 text-[clamp(3.1rem,7.4vw,6.5rem)]">
                     <span className="text-white">
                        Build skills
                        <br />
                        for the way
                     </span>
                     <br />
                     <span className="text-[color:var(--ssu-gold)]">
                        U.S.
                        <br />
                        Construction
                     </span>
                     <br />
                     <span className="text-white">Works.</span>
                  </h1>
                  <p className="mt-6 max-w-xl text-sm leading-relaxed text-white/70 md:text-[0.95rem]">
                     Build practical skills in U.S. construction workflows, estimating, software, and professional development.
                     Designed to help construction professionals become more prepared for remote and global opportunities.
                  </p>
                  <div className="mt-8 flex flex-wrap gap-3">
                     <GoldCta href={route('category.courses', { category: 'all' })}>Explore courses</GoldCta>
                     <GhostCta href="#how-it-works">
                        How it works
                        <ChevronDown className="h-4 w-4" />
                     </GhostCta>
                  </div>
                  <div className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-[12px] text-white/70">
                     <span>✓ Construction-focused training</span>
                     <span>✓ Practical learning</span>
                     <span>✓ Verified credentials</span>
                  </div>
               </div>

               <div className="relative px-2 py-6 lg:px-4 lg:py-10">
                  <span
                     className="absolute bottom-4 left-0 z-10 text-2xl leading-none text-[color:var(--ssu-gold)]"
                     aria-hidden
                  >
                     +
                  </span>
                  {videoUrl ? (
                     <div className="ssu-hero-media">
                        <HeroVideoPlayer videoUrl={videoUrl} posterUrl={posterUrl} className="h-full w-full rounded-none border-0" />
                     </div>
                  ) : (
                     <SamplePlan className="ssu-hero-media min-h-[220px] border-0" />
                  )}
               </div>
            </div>

            <div className="relative container flex justify-end px-4 pb-8">
               <a
                  href="#path"
                  className="font-[family-name:var(--ssu-font-body)] text-[11px] font-semibold tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase"
               >
                  Scroll to review ↓
               </a>
            </div>
         </div>
      </section>
   );
};

export default Hero;
