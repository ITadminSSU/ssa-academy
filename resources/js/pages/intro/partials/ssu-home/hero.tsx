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

   const kicker = heroSection?.title?.trim() || defaultHero.kicker;
   const title = heroSection?.sub_title?.trim() || defaultHero.title;
   const description = heroSection?.description?.trim() || defaultHero.description;

   return (
      <section className="relative overflow-hidden bg-primary text-white">
         <div className="pointer-events-none absolute inset-0">
            <div className="bg-accent/20 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
            <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/10 blur-3xl" />
         </div>

         <div
            className={cn(
               'relative mx-auto flex w-full max-w-[1440px] flex-col items-center gap-10 px-4 py-16 sm:px-6',
               'md:flex-row md:items-center md:gap-10 md:py-20 lg:gap-14 lg:px-10 lg:py-24',
            )}
         >
            <div className="w-full shrink-0 space-y-5 md:max-w-md lg:max-w-lg xl:max-w-xl">
               <p className="ssu-kicker !text-white/90">{kicker}</p>

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-[2.75rem] lg:leading-[1.15] xl:text-5xl">
                  {title}
               </h1>

               <p className="text-base leading-relaxed text-white md:text-lg lg:text-xl lg:leading-relaxed">{description}</p>
            </div>

            <div className="relative w-full min-w-0 flex-1 md:max-w-none">
               <HeroVideoPlayer
                  videoUrl={heroSection?.video_url}
                  posterUrl={heroSection?.thumbnail}
                  className="w-full shadow-2xl shadow-black/30"
               />
            </div>
         </div>
      </section>
   );
};

export default Hero;
