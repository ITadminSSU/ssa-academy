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
      'Structured learning paths for professionals with video lessons, practical assessments, U.S. industry experience, and verified SSA certificates.',
};

/** Dense original CAD-style building blueprint (hand-authored, not third-party). */
const BlueprintSkyline = ({ className }: { className?: string }) => {
   const floors = (y0: number, y1: number, step: number) => {
      const ys: number[] = [];
      for (let y = y0; y <= y1; y += step) ys.push(y);
      return ys;
   };
   const cols = (x0: number, x1: number, step: number) => {
      const xs: number[] = [];
      for (let x = x0; x <= x1; x += step) xs.push(x);
      return xs;
   };
   const Cross = ({ x, y }: { x: number; y: number }) => (
      <g opacity="0.55">
         <path d={`M${x - 6} ${y} H${x + 6}`} />
         <path d={`M${x} ${y - 6} V${y + 6}`} />
      </g>
   );

   return (
      <svg
         className={className}
         viewBox="0 0 1200 780"
         fill="none"
         xmlns="http://www.w3.org/2000/svg"
         preserveAspectRatio="xMinYMid meet"
         aria-hidden
      >
         <g stroke="currentColor" strokeWidth="1.1" strokeLinecap="square" strokeLinejoin="miter">
            {/* Perspective / survey rays */}
            <path d="M30 750 L220 30" strokeDasharray="4 7" opacity="0.35" />
            <path d="M90 750 L360 18" strokeDasharray="5 6" opacity="0.28" />
            <path d="M180 750 L480 8" strokeDasharray="3 8" opacity="0.24" />
            <path d="M520 750 L520 6" strokeDasharray="6 5" opacity="0.22" />
            <path d="M780 750 L640 20" strokeDasharray="4 7" opacity="0.28" />
            <path d="M980 750 L820 40" strokeDasharray="5 6" opacity="0.24" />
            <path d="M1120 750 L980 80" strokeDasharray="3 8" opacity="0.2" />

            {/* Horizontal construction guides */}
            {[80, 160, 240, 320, 400, 480, 560, 640, 720].map((y) => (
               <path key={`hg-${y}`} d={`M20 ${y} H1180`} strokeDasharray="2 10" opacity="0.12" />
            ))}

            <Cross x={220} y={30} />
            <Cross x={360} y={18} />
            <Cross x={480} y={8} />
            <Cross x={520} y={6} />
            <Cross x={640} y={20} />
            <Cross x={820} y={40} />
            <Cross x={140} y={180} />
            <Cross x={300} y={120} />
            <Cross x={560} y={70} />
            <Cross x={700} y={140} />
            <Cross x={900} y={110} />
            <Cross x={1040} y={200} />

            {/* Ground & site lines */}
            <path d="M16 750 H1184" opacity="0.65" />
            <path d="M30 768 H1170" strokeDasharray="4 5" opacity="0.28" />
            <path d="M50 750 V768" opacity="0.35" />
            <path d="M1150 750 V768" opacity="0.35" />

            {/* —— Building A: thin left tower —— */}
            <path d="M48 750 V160 H118 V750" opacity="0.85" />
            {floors(185, 735, 22).map((y) => (
               <path key={`a-f-${y}`} d={`M48 ${y} H118`} opacity="0.32" />
            ))}
            {cols(62, 104, 14).map((x) => (
               <path key={`a-c-${x}`} d={`M${x} 170 V740`} opacity="0.28" />
            ))}
            {/* Antenna / crown */}
            <path d="M72 160 V95" opacity="0.55" />
            <path d="M72 95 H95" opacity="0.4" />
            <Cross x={72} y={95} />

            {/* Side cantilever on A */}
            <path d="M118 280 H175 V355 H118" opacity="0.8" />
            {floors(295, 345, 16).map((y) => (
               <path key={`a-cant-f-${y}`} d={`M118 ${y} H175`} opacity="0.3" />
            ))}
            {cols(132, 162, 15).map((x) => (
               <path key={`a-cant-c-${x}`} d={`M${x} 288 V348`} opacity="0.3" />
            ))}

            {/* —— Building B: mid-rise block —— */}
            <path d="M145 750 V360 H275 V750" opacity="0.82" />
            {floors(380, 735, 24).map((y) => (
               <path key={`b-f-${y}`} d={`M145 ${y} H275`} opacity="0.3" />
            ))}
            {cols(165, 255, 18).map((x) => (
               <path key={`b-c-${x}`} d={`M${x} 372 V740`} opacity="0.28" />
            ))}
            {/* Parapet detail */}
            <path d="M155 360 V340 H265 V360" opacity="0.55" />

            {/* —— Building C: tall center complex —— */}
            <path d="M290 750 V130 H575 V750" opacity="0.9" />
            {/* Setback crowns */}
            <path d="M325 130 V78 H540 V130" opacity="0.75" />
            <path d="M365 78 V38 H500 V78" opacity="0.6" />
            <path d="M410 38 V14 H455 V38" opacity="0.5" />
            {floors(150, 735, 20).map((y) => (
               <path key={`c-f-${y}`} d={`M290 ${y} H575`} opacity="0.28" />
            ))}
            {cols(315, 550, 22).map((x) => (
               <path key={`c-c-${x}`} d={`M${x} 140 V740`} opacity="0.26" />
            ))}
            {/* Core elevator shaft */}
            <path d="M410 140 V720 H455 V140" opacity="0.55" />
            {floors(160, 710, 28).map((y) => (
               <path key={`c-core-${y}`} d={`M410 ${y} H455`} opacity="0.35" />
            ))}
            {/* Lobby portal */}
            <path d="M340 750 V610 H525 V750" opacity="0.85" />
            {cols(365, 500, 32).map((x) => (
               <path key={`c-lob-${x}`} d={`M${x} 620 V740`} opacity="0.4" />
            ))}
            <path d="M340 680 H525" opacity="0.35" />
            {/* Left wing box on C */}
            <path d="M235 210 H290 V305 H235" opacity="0.7" />
            {floors(225, 295, 18).map((y) => (
               <path key={`c-wing-f-${y}`} d={`M235 ${y} H290`} opacity="0.3" />
            ))}
            {cols(250, 275, 14).map((x) => (
               <path key={`c-wing-c-${x}`} d={`M${x} 218 V298`} opacity="0.3" />
            ))}
            {/* Right upper cube on C */}
            <path d="M575 195 H665 V330 H575" opacity="0.78" />
            {floors(210, 320, 18).map((y) => (
               <path key={`c-cube-f-${y}`} d={`M575 ${y} H665`} opacity="0.3" />
            ))}
            {cols(595, 645, 16).map((x) => (
               <path key={`c-cube-c-${x}`} d={`M${x} 205 V322`} opacity="0.3" />
            ))}

            {/* —— Building D: stepped mid tower —— */}
            <path d="M600 750 V250 H760 V750" opacity="0.82" />
            <path d="M625 250 V195 H735 V250" opacity="0.65" />
            {floors(270, 735, 22).map((y) => (
               <path key={`d-f-${y}`} d={`M600 ${y} H760`} opacity="0.28" />
            ))}
            {cols(625, 735, 20).map((x) => (
               <path key={`d-c-${x}`} d={`M${x} 260 V740`} opacity="0.26" />
            ))}
            {/* Mid cantilever */}
            <path d="M760 340 H845 V470 H760" opacity="0.75" />
            {floors(355, 460, 18).map((y) => (
               <path key={`d-cant-f-${y}`} d={`M760 ${y} H845`} opacity="0.3" />
            ))}
            {cols(780, 825, 15).map((x) => (
               <path key={`d-cant-c-${x}`} d={`M${x} 348 V462`} opacity="0.3" />
            ))}

            {/* —— Building E: glass curtain tower —— */}
            <path d="M790 750 V145 H935 V750" opacity="0.85" />
            <path d="M815 145 V95 H910 V145" opacity="0.6" />
            {floors(165, 735, 18).map((y) => (
               <path key={`e-f-${y}`} d={`M790 ${y} H935`} opacity="0.26" />
            ))}
            {cols(812, 912, 16).map((x) => (
               <path key={`e-c-${x}`} d={`M${x} 155 V740`} opacity="0.24" />
            ))}
            {/* Diagonal brace (structural) */}
            <path d="M790 500 L935 350" opacity="0.22" />
            <path d="M790 620 L935 470" opacity="0.18" />

            {/* —— Building F: low podium —— */}
            <path d="M950 750 V430 H1125 V750" opacity="0.7" />
            {floors(450, 735, 24).map((y) => (
               <path key={`f-f-${y}`} d={`M950 ${y} H1125`} opacity="0.26" />
            ))}
            {cols(975, 1100, 22).map((x) => (
               <path key={`f-c-${x}`} d={`M${x} 442 V740`} opacity="0.24" />
            ))}
            {/* Rooftop mechanical box */}
            <path d="M990 430 V390 H1080 V430" opacity="0.55" />
            {cols(1010, 1060, 18).map((x) => (
               <path key={`f-mech-${x}`} d={`M${x} 398 V422`} opacity="0.35" />
            ))}

            {/* —— Building G: far slender needle —— */}
            <path d="M1085 750 V280 H1155 V750" opacity="0.6" />
            {floors(300, 735, 26).map((y) => (
               <path key={`g-f-${y}`} d={`M1085 ${y} H1155`} opacity="0.22" />
            ))}
            {cols(1102, 1138, 18).map((x) => (
               <path key={`g-c-${x}`} d={`M${x} 290 V740`} opacity="0.2" />
            ))}
            <path d="M1112 280 V210" opacity="0.4" />
            <Cross x={1112} y={210} />

            {/* Dimension ticks along ground */}
            {[90, 200, 320, 480, 620, 780, 900, 1040].map((x) => (
               <g key={`dim-${x}`} opacity="0.4">
                  <path d={`M${x} 750 V762`} />
                  <path d={`M${x - 4} 762 H${x + 4}`} />
               </g>
            ))}

            {/* Small elevation callout boxes */}
            <path d="M40 40 H120 V85 H40 Z" opacity="0.35" />
            <path d="M50 52 H110" opacity="0.25" />
            <path d="M50 68 H95" opacity="0.25" />
            <path d="M1050 50 H1145 V100 H1050 Z" opacity="0.28" />
            <path d="M1062 65 H1132" opacity="0.2" />
            <path d="M1062 80 H1110" opacity="0.2" />
         </g>
      </svg>
   );
};

/** Original residential house elevations + plan lines (not third-party art). */
const BlueprintHouses = ({ className }: { className?: string }) => {
   const Cross = ({ x, y }: { x: number; y: number }) => (
      <g opacity="0.5">
         <path d={`M${x - 5} ${y} H${x + 5}`} />
         <path d={`M${x} ${y - 5} V${y + 5}`} />
      </g>
   );

   return (
      <svg
         className={className}
         viewBox="0 0 640 420"
         fill="none"
         xmlns="http://www.w3.org/2000/svg"
         preserveAspectRatio="xMidYMid slice"
         aria-hidden
      >
         <g stroke="currentColor" strokeWidth="1.15" strokeLinecap="square" strokeLinejoin="miter">
            {/* Survey guides */}
            <path d="M20 400 L120 30" strokeDasharray="4 7" opacity="0.28" />
            <path d="M80 400 L260 20" strokeDasharray="5 6" opacity="0.22" />
            <path d="M320 400 L320 16" strokeDasharray="6 5" opacity="0.2" />
            <path d="M560 400 L460 40" strokeDasharray="4 7" opacity="0.24" />
            <Cross x={120} y={30} />
            <Cross x={260} y={20} />
            <Cross x={320} y={16} />
            <Cross x={460} y={40} />

            <path d="M10 400 H630" opacity="0.55" />
            <path d="M24 412 H616" strokeDasharray="3 5" opacity="0.25" />

            {/* —— Two-story house elevation (left) —— */}
            {/* Walls */}
            <path d="M40 400 V210 H230 V400" opacity="0.85" />
            {/* Pitched roof */}
            <path d="M30 210 L135 95 L240 210" opacity="0.9" />
            <path d="M48 210 L135 118 L222 210" opacity="0.35" />
            {/* Chimney */}
            <path d="M175 145 V85 H200 V160" opacity="0.7" />
            <path d="M170 85 H205" opacity="0.55" />
            {/* Upper windows */}
            <path d="M60 240 H100 V290 H60 Z" opacity="0.75" />
            <path d="M80 240 V290" opacity="0.4" />
            <path d="M60 265 H100" opacity="0.4" />
            <path d="M160 240 H200 V290 H160 Z" opacity="0.75" />
            <path d="M180 240 V290" opacity="0.4" />
            <path d="M160 265 H200" opacity="0.4" />
            {/* Door */}
            <path d="M105 400 V320 H155 V400" opacity="0.85" />
            <path d="M145 360 H150" opacity="0.5" />
            {/* Side window lower */}
            <path d="M175 330 H210 V375 H175 Z" opacity="0.65" />
            <path d="M192 330 V375" opacity="0.35" />
            <path d="M175 352 H210" opacity="0.35" />
            {/* Floor line */}
            <path d="M40 300 H230" opacity="0.4" />
            {/* Garage wing */}
            <path d="M230 400 V280 H320 V400" opacity="0.75" />
            <path d="M230 280 L275 240 L320 280" opacity="0.7" />
            <path d="M250 400 V310 H300 V400" opacity="0.7" />
            <path d="M250 340 H300" opacity="0.35" />
            <path d="M275 310 V400" opacity="0.35" />

            {/* —— Ranch house elevation (center-right) —— */}
            <path d="M350 400 V250 H560 V400" opacity="0.8" />
            <path d="M335 250 L455 155 L575 250" opacity="0.88" />
            <path d="M360 250 L455 175 L550 250" opacity="0.32" />
            {/* Dormer */}
            <path d="M430 200 V165 L455 145 L480 165 V200" opacity="0.65" />
            <path d="M440 175 H470 V198 H440 Z" opacity="0.5" />
            {/* Windows row */}
            {[365, 415, 500].map((x) => (
               <g key={`rw-${x}`} opacity="0.7">
                  <path d={`M${x} 280 H${x + 40} V330 H${x} Z`} />
                  <path d={`M${x + 20} 280 V330`} opacity="0.55" />
                  <path d={`M${x} 305 H${x + 40}`} opacity="0.55" />
               </g>
            ))}
            {/* Front door */}
            <path d="M460 400 V310 H500 V400" opacity="0.8" />
            <path d="M490 355 H495" opacity="0.45" />
            {/* Porch posts */}
            <path d="M350 250 H560" opacity="0.35" />
            <path d="M370 250 V270" opacity="0.4" />
            <path d="M540 250 V270" opacity="0.4" />

            {/* —— Small floor-plan inset (top-left style) —— */}
            <path d="M40 70 H160 V160 H40 Z" opacity="0.55" />
            <path d="M40 100 H160" opacity="0.35" />
            <path d="M100 70 V160" opacity="0.35" />
            <path d="M40 130 H100" opacity="0.3" />
            {/* Door swings (arcs approximated as lines) */}
            <path d="M100 130 H115 V145" opacity="0.4" />
            <path d="M70 160 V148" opacity="0.4" />
            <path d="M55 85 H85 V95 H55 Z" opacity="0.35" />
            <path d="M115 85 H145 V95 H115 Z" opacity="0.35" />
            <Cross x={40} y={70} />
            <Cross x={160} y={160} />

            {/* —— Second floor-plan fragment —— */}
            <path d="M500 55 H610 V145 H500 Z" opacity="0.45" />
            <path d="M500 90 H610" opacity="0.3" />
            <path d="M555 55 V145" opacity="0.3" />
            <path d="M520 105 H545 V130 H520 Z" opacity="0.35" />
            <path d="M570 105 H595 V130 H570 Z" opacity="0.35" />
            <path d="M555 90 V75" opacity="0.35" />

            {/* Dimension ticks */}
            {[80, 180, 280, 400, 520].map((x) => (
               <g key={`hd-${x}`} opacity="0.4">
                  <path d={`M${x} 400 V410`} />
                  <path d={`M${x - 4} 410 H${x + 4}`} />
               </g>
            ))}
         </g>
      </svg>
   );
};

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
         {/* Top skyline blueprint (logo / video band) */}
         <div className="pointer-events-none absolute inset-0 z-0" aria-hidden>
            <div
               className="absolute inset-0 opacity-[0.07]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(186,230,253,0.7) 1px, transparent 1px), linear-gradient(90deg, rgba(186,230,253,0.7) 1px, transparent 1px)',
                  backgroundSize: '40px 40px',
               }}
            />
            <div
               className="absolute inset-0 opacity-[0.04]"
               style={{
                  backgroundImage:
                     'linear-gradient(rgba(125,211,252,0.8) 1px, transparent 1px), linear-gradient(90deg, rgba(125,211,252,0.8) 1px, transparent 1px)',
                  backgroundSize: '160px 160px',
               }}
            />

            <BlueprintSkyline className="absolute top-[2%] left-[-4%] h-[55%] w-[110%] max-w-none text-sky-100/55 sm:left-[-2%] md:w-[78%] lg:w-[70%]" />

            <div className="absolute inset-0 bg-gradient-to-r from-primary/10 via-primary/25 to-primary/85" />
            <div className="absolute inset-0 bg-gradient-to-b from-primary/10 via-transparent to-transparent" />
         </div>

         {/* Top: Welcome + logo | video — 330px on desktop */}
         <div className="relative z-20 mx-auto w-full max-w-[1440px] px-4 py-4 sm:px-6 md:h-[330px] md:py-4 lg:px-10">
            <div
               className={cn(
                  'flex flex-col items-center gap-4',
                  'md:h-full md:flex-row md:items-center md:justify-center md:gap-8 lg:gap-12',
               )}
            >
               <div className="flex w-full justify-center md:w-1/2 md:flex-1">
                  <div className="flex w-[220px] flex-col items-center sm:w-[260px] md:w-[300px] lg:w-[320px]">
                     <p className="font-display mb-2.5 w-full text-center text-[1.15rem] leading-none font-bold tracking-[0.04em] text-white uppercase sm:mb-3 sm:text-[1.25rem] md:text-[1.4rem] lg:text-[1.5rem]">
                        Welcome to
                     </p>

                     <AppLogo
                        theme="dark"
                        className="mx-auto h-[100px] w-full object-contain object-center sm:h-[120px] md:h-[140px] lg:h-[148px]"
                     />
                  </div>
               </div>

               <div className="flex w-full min-w-0 flex-1 items-center justify-center md:h-full md:max-w-[48%]">
                  <div className="aspect-video w-full overflow-hidden rounded-2xl border border-white/15 bg-black/30 shadow-2xl shadow-black/35 md:h-full md:w-auto md:max-h-full">
                     <HeroVideoPlayer
                        videoUrl={videoUrl}
                        posterUrl={posterUrl}
                        className="h-full w-full rounded-none border-0 shadow-none"
                     />
                  </div>
               </div>
            </div>
         </div>

         <div className="relative z-20 h-8 sm:h-9 md:h-9" aria-hidden>
            <div className="absolute inset-x-0 top-1/2 h-1 -translate-y-1/2 bg-white md:h-1.5" />
         </div>

         {/* Tagline band — house blueprint background */}
         <div className="relative z-20 overflow-hidden">
            <div className="pointer-events-none absolute inset-0 z-0" aria-hidden>
               <div
                  className="absolute inset-0 opacity-[0.08]"
                  style={{
                     backgroundImage:
                        'linear-gradient(rgba(186,230,253,0.75) 1px, transparent 1px), linear-gradient(90deg, rgba(186,230,253,0.75) 1px, transparent 1px)',
                     backgroundSize: '36px 36px',
                  }}
               />
               <BlueprintHouses className="absolute inset-y-0 left-0 h-full w-[48%] max-w-[560px] text-sky-100/60" />
               <BlueprintHouses className="absolute inset-y-0 right-0 h-full w-[48%] max-w-[560px] scale-x-[-1] text-sky-100/50" />
               <div className="absolute inset-0 bg-gradient-to-r from-primary/25 via-primary/70 to-primary/25" />
               <div className="absolute inset-0 bg-gradient-to-b from-primary/20 via-transparent to-primary/40" />
            </div>

            <div className="relative z-10 mx-auto w-full max-w-[1440px] px-4 pt-6 pb-8 sm:px-6 sm:pt-7 md:pt-8 md:pb-10 lg:px-10 lg:pt-8 lg:pb-11">
               <div className="mx-auto w-full max-w-6xl space-y-2.5 text-center md:space-y-3">
                  <p className="ssu-kicker !text-sky-100/70">{kicker}</p>

                  <h1 className="font-display whitespace-nowrap text-[clamp(0.8rem,2.15vw,1.65rem)] leading-none font-bold tracking-tight">
                     {title}
                  </h1>

                  <p className="mx-auto max-w-5xl text-[0.8rem] leading-snug text-sky-100/80 sm:text-sm md:text-[0.95rem] md:leading-relaxed">
                     {description}
                  </p>
               </div>
            </div>
         </div>
      </section>
   );
};

export default Hero;
