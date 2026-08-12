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

/**
 * Original SSU Academy blueprint skyline (hand-authored SVG).
 * Not derived from third-party artwork.
 */
const BlueprintSkyline = ({ className }: { className?: string }) => (
   <svg
      className={className}
      viewBox="0 0 1200 780"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      preserveAspectRatio="xMinYMid meet"
      aria-hidden
   >
      <g stroke="currentColor" strokeWidth="1.15" strokeLinecap="square" strokeLinejoin="miter">
         {/* Construction guide rays */}
         <path d="M40 740 L280 40" strokeDasharray="5 7" opacity="0.28" />
         <path d="M120 740 L420 20" strokeDasharray="4 8" opacity="0.22" />
         <path d="M560 740 L560 10" strokeDasharray="6 6" opacity="0.2" />
         <path d="M900 740 L760 30" strokeDasharray="5 7" opacity="0.24" />
         <path d="M1100 740 L940 60" strokeDasharray="4 8" opacity="0.2" />

         {/* Alignment crosses */}
         {[
            [280, 40],
            [420, 20],
            [560, 10],
            [760, 30],
            [940, 60],
            [180, 220],
            [640, 160],
            [860, 200],
         ].map(([x, y], i) => (
            <g key={`x-${i}`} opacity="0.35">
               <path d={`M${x - 5} ${y} H${x + 5}`} />
               <path d={`M${x} ${y - 5} V${y + 5}`} />
            </g>
         ))}

         {/* Ground line */}
         <path d="M20 740 H1180" opacity="0.45" />
         <path d="M40 760 H1160" opacity="0.2" strokeDasharray="3 6" />

         {/* Left tower — slender vertical mass */}
         <path d="M90 740 V210 H170 V740" opacity="0.75" />
         <path d="M110 230 V720" opacity="0.35" />
         <path d="M130 230 V720" opacity="0.35" />
         <path d="M150 230 V720" opacity="0.35" />
         {[280, 340, 400, 460, 520, 580, 640, 700].map((y) => (
            <path key={`lt-h-${y}`} d={`M90 ${y} H170`} opacity="0.3" />
         ))}
         {/* Left cantilever */}
         <path d="M170 320 H230 V400 H170" opacity="0.7" />
         <path d="M185 335 V385" opacity="0.35" />
         <path d="M205 335 V385" opacity="0.35" />
         <path d="M170 360 H230" opacity="0.3" />

         {/* Mid-left block */}
         <path d="M200 740 V380 H320 V740" opacity="0.7" />
         {[430, 490, 550, 610, 670].map((y) => (
            <path key={`mlb-h-${y}`} d={`M200 ${y} H320`} opacity="0.28" />
         ))}
         {[230, 260, 290].map((x) => (
            <path key={`mlb-v-${x}`} d={`M${x} 395 V725`} opacity="0.28" />
         ))}

         {/* Center complex — main framed volume */}
         <path d="M340 740 V160 H620 V740" opacity="0.8" />
         {/* Roof setbacks */}
         <path d="M380 160 V100 H580 V160" opacity="0.65" />
         <path d="M420 100 V55 H540 V100" opacity="0.5" />
         {/* Floor plates */}
         {[220, 280, 340, 400, 460, 520, 580, 640, 700].map((y) => (
            <path key={`c-h-${y}`} d={`M340 ${y} H620`} opacity="0.28" />
         ))}
         {/* Structural columns */}
         {[380, 430, 480, 530, 580].map((x) => (
            <path key={`c-v-${x}`} d={`M${x} 170 V725`} opacity="0.3" />
         ))}
         {/* Ground portal frame */}
         <path d="M390 740 V620 H570 V740" opacity="0.75" />
         <path d="M410 635 V725" opacity="0.35" />
         <path d="M480 635 V725" opacity="0.35" />
         <path d="M550 635 V725" opacity="0.35" />
         <path d="M390 680 H570" opacity="0.3" />
         {/* Upper projecting cube */}
         <path d="M620 240 H700 V360 H620" opacity="0.7" />
         <path d="M640 255 V345" opacity="0.35" />
         <path d="M670 255 V345" opacity="0.35" />
         <path d="M620 300 H700" opacity="0.3" />
         {/* Mid projecting volume */}
         <path d="M300 200 H340 V280" opacity="0.55" />
         <path d="M300 200 V280 H340" opacity="0.55" />

         {/* Right mid building */}
         <path d="M660 740 V280 H820 V740" opacity="0.72" />
         {[340, 400, 460, 520, 580, 640, 700].map((y) => (
            <path key={`rm-h-${y}`} d={`M660 ${y} H820`} opacity="0.28" />
         ))}
         {[700, 740, 780].map((x) => (
            <path key={`rm-v-${x}`} d={`M${x} 295 V725`} opacity="0.28" />
         ))}
         {/* Right cantilever box */}
         <path d="M820 360 H900 V480 H820" opacity="0.68" />
         <path d="M845 380 V460" opacity="0.35" />
         <path d="M875 380 V460" opacity="0.35" />
         <path d="M820 420 H900" opacity="0.3" />

         {/* Far-right tower */}
         <path d="M860 740 V200 H980 V740" opacity="0.7" />
         <path d="M890 220 V720" opacity="0.3" />
         <path d="M920 220 V720" opacity="0.3" />
         <path d="M950 220 V720" opacity="0.3" />
         {[260, 320, 380, 440, 500, 560, 620, 680].map((y) => (
            <path key={`fr-h-${y}`} d={`M860 ${y} H980`} opacity="0.28" />
         ))}
         {/* Top fin */}
         <path d="M890 200 V130 H950 V200" opacity="0.55" />
         <path d="M910 145 V185" opacity="0.3" />
         <path d="M930 145 V185" opacity="0.3" />

         {/* Distant low bar */}
         <path d="M1000 740 V480 H1140 V740" opacity="0.55" />
         {[540, 600, 660, 720].map((y) => (
            <path key={`db-h-${y}`} d={`M1000 ${y} H1140`} opacity="0.25" />
         ))}
         {[1040, 1080, 1120].map((x) => (
            <path key={`db-v-${x}`} d={`M${x} 495 V725`} opacity="0.25" />
         ))}
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
         {/* Original blueprint skyline background */}
         <div className="pointer-events-none absolute inset-0 z-0" aria-hidden>
            <div
               className="absolute inset-0 opacity-[0.045]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(147,197,253,0.55) 1px, transparent 1px), linear-gradient(90deg, rgba(147,197,253,0.55) 1px, transparent 1px)',
                  backgroundSize: '56px 56px',
               }}
            />

            <BlueprintSkyline className="absolute top-[6%] left-[-2%] h-[90%] w-[95%] max-w-none text-sky-100/35 sm:left-0 md:w-[70%] lg:w-[62%]" />

            <div className="absolute inset-0 bg-gradient-to-r from-primary/20 via-primary/35 to-primary/80" />
            <div className="absolute inset-0 bg-gradient-to-b from-primary/15 via-transparent to-primary/55" />
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
                     <HeroVideoPlayer
                        videoUrl={videoUrl}
                        posterUrl={posterUrl}
                        className="h-full w-full rounded-none border-0 shadow-none"
                     />
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
