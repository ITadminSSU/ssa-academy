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

   const kicker = heroSection?.title || defaultHero.kicker;
   const title = heroSection?.sub_title || defaultHero.title;
   const description = heroSection?.description || defaultHero.description;

   return (
      <section className="relative overflow-hidden bg-primary text-white">
         <div className="pointer-events-none absolute inset-0">
            <div className="bg-accent/20 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
            <div className="absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-white/10 blur-3xl" />
         </div>

         <div className={cn('relative container flex flex-col items-center gap-10 px-4 py-20 md:flex-row md:gap-16 md:py-28')}>
            <div className="w-full space-y-6 md:max-w-xl">
               <p className="ssu-kicker text-primary-foreground/90 !text-white/80">{kicker}</p>

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-5xl lg:leading-[1.15]">{title}</h1>

               <p className="text-base leading-relaxed text-white/80 md:text-lg">{description}</p>
            </div>

            <div className="relative w-full max-w-lg">
               <HeroVideoPlayer videoUrl={heroSection?.video_url} posterUrl={heroSection?.thumbnail} />
            </div>
         </div>
      </section>
   );
};

export default Hero;
