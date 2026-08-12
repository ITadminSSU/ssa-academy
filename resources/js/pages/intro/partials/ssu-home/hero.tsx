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

/** Architectural wireframe / building-line backdrop */
const BuildingLines = ({ className }: { className?: string }) => (
   <svg
      className={className}
      viewBox="0 0 720 900"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden
   >
      <g stroke="currentColor" strokeWidth="1.25" opacity="0.9">
         {/* Ground / horizon guides */}
         <path d="M40 820 H680" />
         <path d="M80 780 H640" />

         {/* Left tower */}
         <path d="M90 820 V260 H210 V820" />
         <path d="M110 280 V800" />
         <path d="M130 280 V800" />
         <path d="M150 280 V800" />
         <path d="M170 280 V800" />
         <path d="M190 280 V800" />
         <path d="M90 320 H210" />
         <path d="M90 380 H210" />
         <path d="M90 440 H210" />
         <path d="M90 500 H210" />
         <path d="M90 560 H210" />
         <path d="M90 620 H210" />
         <path d="M90 680 H210" />
         <path d="M90 740 H210" />
         <path d="M120 260 L150 210 L180 260" />

         {/* Center tower */}
         <path d="M250 820 V180 H400 V820" />
         <path d="M275 200 V800" />
         <path d="M300 200 V800" />
         <path d="M325 200 V800" />
         <path d="M350 200 V800" />
         <path d="M375 200 V800" />
         <path d="M250 240 H400" />
         <path d="M250 300 H400" />
         <path d="M250 360 H400" />
         <path d="M250 420 H400" />
         <path d="M250 480 H400" />
         <path d="M250 540 H400" />
         <path d="M250 600 H400" />
         <path d="M250 660 H400" />
         <path d="M250 720 H400" />
         <path d="M290 180 L325 120 L360 180" />

         {/* Right mid-rise */}
         <path d="M440 820 V340 H560 V820" />
         <path d="M460 360 V800" />
         <path d="M480 360 V800" />
         <path d="M500 360 V800" />
         <path d="M520 360 V800" />
         <path d="M540 360 V800" />
         <path d="M440 400 H560" />
         <path d="M440 460 H560" />
         <path d="M440 520 H560" />
         <path d="M440 580 H560" />
         <path d="M440 640 H560" />
         <path d="M440 700 H560" />
         <path d="M440 760 H560" />

         {/* Far right block */}
         <path d="M590 820 V420 H680 V820" />
         <path d="M610 440 V800" />
         <path d="M630 440 V800" />
         <path d="M650 440 V800" />
         <path d="M590 480 H680" />
         <path d="M590 540 H680" />
         <path d="M590 600 H680" />
         <path d="M590 660 H680" />
         <path d="M590 720 H680" />
         <path d="M590 780 H680" />

         {/* Perspective guides */}
         <path d="M40 820 L325 120" opacity="0.45" />
         <path d="M680 820 L325 120" opacity="0.35" />
         <path d="M40 700 H680" opacity="0.35" />
         <path d="M60 560 H660" opacity="0.25" />
      </g>
   </svg>
);

const Hero = () => {
   const { props } = usePage<IntroPageProps>();
   const heroSection = getPageSection(props.page, 'hero');

   const rawKicker = heroSection?.title?.trim() || defaultHero.kicker;
   const kicker = rawKicker.replace(/SMART\s+SOURCING/gi, 'SMARTSOURCING');
   const title = heroSection?.sub_title?.trim() || defaultHero.title;
   const description = heroSection?.description?.trim() || defaultHero.description;

   return (
      <section className="relative overflow-hidden bg-primary text-white">
         {/* Building-line / blueprint atmosphere */}
         <div className="pointer-events-none absolute inset-0" aria-hidden>
            <div
               className="absolute inset-0 opacity-[0.07]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(147,197,253,0.55) 1px, transparent 1px), linear-gradient(90deg, rgba(147,197,253,0.55) 1px, transparent 1px)',
                  backgroundSize: '56px 56px',
               }}
            />
            <BuildingLines className="absolute top-0 left-0 h-full w-[58%] max-w-[720px] text-sky-200/35 md:w-[50%] lg:w-[46%]" />
            <div className="absolute inset-y-0 left-0 w-[70%] bg-gradient-to-r from-primary via-primary/80 to-transparent" />
            <div className="bg-accent/10 absolute -top-24 -right-24 h-72 w-72 rounded-full blur-3xl" />
         </div>

         {/* Top: Welcome + logo | video */}
         <div className="relative mx-auto w-full max-w-[1440px] px-4 pt-12 pb-10 sm:px-6 sm:pt-14 md:pt-16 md:pb-12 lg:px-10 lg:pt-20 lg:pb-14">
            <div
               className={cn(
                  'flex flex-col items-center gap-10',
                  'md:flex-row md:items-center md:justify-between md:gap-12 lg:gap-16',
               )}
            >
               <div className="relative z-10 flex w-full flex-col items-center text-center md:max-w-md md:items-start md:text-left lg:max-w-lg">
                  <p className="font-display mb-5 text-3xl font-bold tracking-[0.08em] text-white uppercase sm:text-4xl md:mb-6 md:text-5xl lg:text-6xl">
                     Welcome to
                  </p>

                  <AppLogo
                     theme="dark"
                     className="h-[130px] w-auto max-w-[300px] object-contain object-center sm:h-[150px] sm:max-w-[340px] md:h-[170px] md:max-w-[380px] md:object-left lg:h-[190px] lg:max-w-[420px]"
                  />
               </div>

               <div className="relative z-10 w-full min-w-0 flex-1 md:max-w-[56%]">
                  <HeroVideoPlayer
                     videoUrl={heroSection?.video_url}
                     posterUrl={heroSection?.thumbnail}
                     className="w-full shadow-2xl shadow-black/35"
                  />
               </div>
            </div>
         </div>

         {/* Gap / divider between welcome block and tagline */}
         <div className="relative h-10 bg-primary sm:h-14 md:h-20 lg:h-24" aria-hidden>
            <div className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 bg-white md:h-1.5" />
         </div>

         {/* Bottom: tagline */}
         <div className="relative mx-auto w-full max-w-[1440px] px-4 pt-10 pb-16 sm:px-6 sm:pt-12 md:pt-14 md:pb-20 lg:px-10 lg:pt-16 lg:pb-24">
            <div className="mx-auto max-w-4xl space-y-4 text-center md:mx-0 md:max-w-3xl md:text-left">
               <p className="ssu-kicker !text-sky-100/70">{kicker}</p>

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
