import AppLogo from '@/components/app-logo';
import HeroVideoPlayer from '@/components/hero-video-player';
import { getPageSection } from '@/lib/page';
import { cn } from '@/lib/utils';
import { IntroPageProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const defaultHero = {
   kicker: 'SMARTSOURCING USA ACADEMY',
   title: 'Upskill. Certify your skills. Scale with confidence.',
   description:
      'Structured learning paths for professionals — video lessons, assignments, quizzes, and verified SSU certificates.',
};

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');

   const rawKicker = heroSection?.title?.trim() || defaultHero.kicker;
   const kicker = rawKicker.replace(/SMART\s+SOURCING/gi, 'SMARTSOURCING');
   const title = heroSection?.sub_title?.trim() || defaultHero.title;
   const description = heroSection?.description?.trim() || defaultHero.description;

   return (
      <section className="relative overflow-hidden bg-primary text-white">
         {/* Soft blueprint atmosphere */}
         <div className="pointer-events-none absolute inset-0" aria-hidden>
            <div
               className="absolute inset-0 opacity-[0.12]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(255,255,255,0.35) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.35) 1px, transparent 1px)',
                  backgroundSize: '48px 48px',
                  maskImage: 'radial-gradient(ellipse 80% 70% at 30% 40%, black 20%, transparent 75%)',
               }}
            />
            <div className="bg-accent/15 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
            <div className="absolute -bottom-28 left-0 h-72 w-72 rounded-full bg-white/10 blur-3xl" />
         </div>

         <div className="relative mx-auto w-full max-w-[1440px] px-4 pt-10 pb-16 sm:px-6 sm:pt-12 md:pt-16 md:pb-20 lg:px-10 lg:pt-20 lg:pb-24">
            {/* Welcome + logo | video */}
            <div
               className={cn(
                  'flex flex-col items-center gap-10',
                  'md:flex-row md:items-center md:justify-between md:gap-12 lg:gap-16',
               )}
            >
               <div className="flex w-full flex-col items-center text-center md:max-w-sm md:items-start md:text-left lg:max-w-md">
                  <p className="font-display mb-4 text-sm font-semibold tracking-[0.22em] text-white uppercase sm:text-base md:mb-5 md:text-lg">
                     Welcome to
                  </p>

                  <AppLogo
                     theme="dark"
                     className="h-[120px] w-auto max-w-[280px] object-contain object-center sm:h-[140px] sm:max-w-[320px] md:h-[160px] md:max-w-[360px] md:object-left lg:h-[180px] lg:max-w-[400px]"
                  />
               </div>

               <div className="relative w-full min-w-0 flex-1 md:max-w-[58%]">
                  <HeroVideoPlayer
                     videoUrl={heroSection?.video_url}
                     posterUrl={heroSection?.thumbnail}
                     className="w-full shadow-2xl shadow-black/35"
                  />
               </div>
            </div>

            {/* Tagline under both */}
            <div className="mt-12 max-w-3xl space-y-4 text-center md:mt-16 md:text-left lg:mt-20">
               <p className="ssu-kicker !text-white/70">{kicker}</p>

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-[2.75rem] lg:leading-[1.15] xl:text-5xl">
                  {title}
               </h1>

               <p className="text-base leading-relaxed text-white/90 md:text-lg lg:max-w-2xl lg:text-xl lg:leading-relaxed">
                  {description}
               </p>
            </div>
         </div>
      </section>
   );
};

export default Hero;
