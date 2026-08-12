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

/** Subtle architectural wireframe — corner building / project lines */
const BuildingLines = ({ className }: { className?: string }) => (
   <svg
      className={className}
      viewBox="0 0 1600 900"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      preserveAspectRatio="xMidYMid slice"
      aria-hidden
   >
      <g stroke="currentColor" strokeWidth="1.1" strokeLinecap="square">
         <path d="M80 860 L520 120" opacity="0.55" />
         <path d="M80 860 L520 860" opacity="0.35" />
         {Array.from({ length: 14 }).map((_, i) => {
            const t = (i + 1) / 15;
            const x1 = 80 + (520 - 80) * t * 0.15;
            const y = 860 - (860 - 120) * t;
            const x2 = 80 + (520 - 80) * (0.55 + t * 0.45);
            return <path key={`lf-${i}`} d={`M${x1} ${y} L${x2} ${y}`} opacity={0.28 + t * 0.2} />;
         })}
         {Array.from({ length: 8 }).map((_, i) => {
            const t = (i + 1) / 9;
            const x = 80 + (520 - 80) * t;
            const yTop = 860 - (860 - 120) * t * 0.92;
            return <path key={`lv-${i}`} d={`M${x} 860 L${x} ${yTop}`} opacity={0.22 + t * 0.25} />;
         })}

         <path d="M520 120 V860" opacity="0.7" />
         <path d="M520 120 H1480" opacity="0.5" />
         <path d="M1480 120 V860" opacity="0.35" />
         <path d="M520 860 H1480" opacity="0.4" />

         {Array.from({ length: 16 }).map((_, i) => {
            const y = 120 + ((860 - 120) / 17) * (i + 1);
            return <path key={`rh-${i}`} d={`M520 ${y} H1480`} opacity={0.18 + (i % 3 === 0 ? 0.12 : 0)} />;
         })}
         {Array.from({ length: 18 }).map((_, i) => {
            const x = 520 + ((1480 - 520) / 19) * (i + 1);
            return <path key={`rv-${i}`} d={`M${x} 120 V860`} opacity={0.16 + (i % 4 === 0 ? 0.1 : 0)} />;
         })}

         {Array.from({ length: 5 }).map((_, row) =>
            Array.from({ length: 6 }).map((_, col) => {
               const x = 580 + col * 140;
               const y = 180 + row * 130;
               return <rect key={`w-${row}-${col}`} x={x} y={y} width="70" height="78" fill="none" opacity="0.22" />;
            }),
         )}

         <path d="M620 120 V60 H1380 V120" opacity="0.45" />
         <path d="M700 60 V20 H1300 V60" opacity="0.35" />
         {Array.from({ length: 10 }).map((_, i) => {
            const x = 700 + ((1300 - 700) / 11) * (i + 1);
            return <path key={`top-${i}`} d={`M${x} 20 V60`} opacity="0.25" />;
         })}
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
   const videoUrl = heroSection?.video_url?.trim() || null;
   const posterUrl = heroSection?.thumbnail?.trim() || null;

   return (
      <section className="relative overflow-hidden bg-primary text-white">
         <div className="pointer-events-none absolute inset-0 z-0" aria-hidden>
            <div
               className="absolute inset-0 opacity-[0.05]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(147,197,253,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(147,197,253,0.5) 1px, transparent 1px)',
                  backgroundSize: '64px 64px',
               }}
            />
            <BuildingLines className="absolute inset-0 h-full w-full text-sky-200/[0.28]" />
            <div className="absolute inset-0 bg-gradient-to-b from-primary/30 via-transparent to-primary/50" />
            <div className="absolute inset-0 bg-gradient-to-r from-primary/40 via-transparent to-primary/55" />
         </div>

         {/* Top: Welcome + logo | video */}
         <div className="relative z-20 mx-auto w-full max-w-[1440px] px-4 pt-12 pb-10 sm:px-6 sm:pt-14 md:pt-16 md:pb-12 lg:px-10 lg:pt-20 lg:pb-14">
            <div
               className={cn(
                  'flex flex-col items-center gap-10',
                  'md:flex-row md:items-center md:justify-between md:gap-12 lg:gap-16',
               )}
            >
               <div className="flex w-full justify-center md:w-auto md:shrink-0">
                  <div className="flex w-[260px] flex-col items-center sm:w-[300px] md:w-[340px] lg:w-[380px]">
                     <p className="font-display mb-4 w-full text-center text-[1.35rem] leading-none font-bold tracking-[0.04em] text-white uppercase sm:mb-5 sm:text-[1.55rem] md:text-[1.7rem] lg:text-[1.85rem]">
                        Welcome to
                     </p>

                     <AppLogo
                        theme="dark"
                        className="mx-auto h-[120px] w-full object-contain object-center sm:h-[140px] md:h-[160px] lg:h-[180px]"
                     />
                  </div>
               </div>

               <div className="w-full min-w-0 flex-1 md:max-w-[56%]">
                  <div className="aspect-video w-full overflow-hidden rounded-2xl border border-white/15 bg-black/30 shadow-2xl shadow-black/35">
                     <HeroVideoPlayer videoUrl={videoUrl} posterUrl={posterUrl} className="h-full w-full rounded-none border-0 shadow-none" />
                  </div>
               </div>
            </div>
         </div>

         <div className="relative z-20 h-10 bg-primary sm:h-14 md:h-20 lg:h-24" aria-hidden>
            <div className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 bg-white md:h-1.5" />
         </div>

         <div className="relative z-20 mx-auto w-full max-w-[1440px] px-4 pt-10 pb-16 sm:px-6 sm:pt-12 md:pt-14 md:pb-20 lg:px-10 lg:pt-16 lg:pb-24">
            <div className="mx-auto max-w-4xl space-y-4 text-center">
               <p className="ssu-kicker !text-sky-100/70">{kicker}</p>

               <h1 className="font-display text-3xl leading-tight font-bold md:text-4xl lg:text-[2.75rem] lg:leading-[1.15] xl:text-5xl">
                  {title}
               </h1>

               <p className="mx-auto max-w-2xl text-base leading-relaxed text-white/90 md:text-lg lg:text-xl lg:leading-relaxed">
                  {description}
               </p>
            </div>
         </div>
      </section>
   );
};

export default Hero;
