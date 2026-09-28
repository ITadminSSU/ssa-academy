import { cn } from '@/lib/utils';
import { useState } from 'react';

const inspectHint = 'Hover over, focus, or click a marked point to inspect this sample plan.';

const hotspots = [
   { id: 'wall', label: 'Wall length', value: '24′ 06″', note: inspectHint, x: '33%', y: '40%' },
   { id: 'openings', label: 'Opening count', value: '04', note: 'Windows and doors marked on this sheet.', x: '61%', y: '40%' },
   { id: 'takeoff', label: 'Takeoff note', value: 'QTY / VERIFY', note: 'Inspect the measurement before you count.', x: '33%', y: '68%' },
];

const PlanDrawing = () => (
   <svg viewBox="0 0 720 500" className="h-auto w-full" aria-hidden>
      <rect width="720" height="500" fill="#d7e2de" />
      <g stroke="#1a344f" strokeOpacity="0.1" strokeWidth="1">
         {Array.from({ length: 31 }).map((_, i) => (
            <line key={`v-${i}`} x1={i * 24} y1="0" x2={i * 24} y2="500" />
         ))}
         {Array.from({ length: 22 }).map((_, i) => (
            <line key={`h-${i}`} x1="0" y1={i * 24} x2="720" y2="500" />
         ))}
      </g>

      <circle cx="28" cy="28" r="5" fill="#3c9a5f" />
      <text x="42" y="32" fill="#1a344f" fontSize="11" letterSpacing="1.8" fontFamily="DM Mono, monospace">
         SAMPLE PLAN / A-104
      </text>
      <g fill="none" stroke="#1a344f" strokeWidth="1.3">
         <circle cx="586" cy="27" r="6" />
         <path d="M582 27 H590 M586 23 V31" />
      </g>
      <text x="600" y="32" fill="#1a344f" fontSize="11" letterSpacing="1.8" fontFamily="DM Mono, monospace">
         1:48 SCALE
      </text>

      <g fill="none" stroke="#e89a1b" strokeWidth="1.4" strokeDasharray="5 4">
         <line x1="86" y1="64" x2="634" y2="64" />
         <line x1="70" y1="86" x2="70" y2="392" />
      </g>
      <text x="330" y="58" fill="#e89a1b" fontSize="12" letterSpacing="1.4" fontFamily="DM Mono, monospace">
         24′-00″
      </text>
      <text x="18" y="250" fill="#e89a1b" fontSize="12" letterSpacing="1.4" fontFamily="DM Mono, monospace" transform="rotate(-90 18 250)">
         18′-00″
      </text>

      <g fill="none" stroke="#1a344f" strokeWidth="1.7">
         <rect x="96" y="86" width="538" height="306" />
         <rect x="118" y="108" width="214" height="132" />
         <rect x="360" y="108" width="250" height="132" />
         <rect x="118" y="262" width="214" height="108" />
         <rect x="360" y="262" width="250" height="108" />
      </g>
      <g fill="none" stroke="#e89a1b" strokeWidth="1.2">
         <line x1="430" y1="240" x2="520" y2="240" />
         <line x1="430" y1="240" x2="430" y2="228" />
         <line x1="520" y1="240" x2="520" y2="228" />
      </g>

      <text x="155" y="180" fill="#1a344f" fontSize="12" letterSpacing="1.6" fontFamily="DM Mono, monospace">
         BEDROOM / 01
      </text>
      <text x="422" y="176" fill="#1a344f" fontSize="12" letterSpacing="1.6" fontFamily="DM Mono, monospace">
         LIVING / 02
      </text>
      <text x="422" y="198" fill="#e89a1b" fontSize="10" letterSpacing="1.6" fontFamily="DM Mono, monospace">
         WINDOW SCHEDULE
      </text>
      <text x="148" y="322" fill="#1a344f" fontSize="11" letterSpacing="1.5" fontFamily="DM Mono, monospace">
         FOUNDATION LINE / VERIFY
      </text>
   </svg>
);

const SamplePlan = ({ className, variant = 'embed' }: { className?: string; variant?: 'sheet' | 'embed' }) => {
   const [active, setActive] = useState<(typeof hotspots)[number] | null>(hotspots[0]);
   const isSheet = variant === 'sheet';

   const canvas = (
      <div className={cn('relative', isSheet ? 'overflow-hidden bg-[#d7e2de]' : 'h-full w-full overflow-hidden')}>
         <PlanDrawing />

         {hotspots.map((spot) => {
            const isActive = active?.id === spot.id;

            return (
               <button
                  key={spot.id}
                  type="button"
                  aria-label={`Show ${spot.label}`}
                  onClick={() => setActive(spot)}
                  onMouseEnter={() => setActive(spot)}
                  onFocus={() => setActive(spot)}
                  className={cn(
                     'absolute h-5 w-5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 transition',
                     isActive
                        ? 'scale-110 border-white bg-[color:var(--ssu-gold)] shadow-md'
                        : 'border-[color:var(--ssu-gold)] bg-transparent hover:bg-[color:var(--ssu-gold)]/20',
                  )}
                  style={{ left: spot.x, top: spot.y }}
               />
            );
         })}

         {active ? (
            <div
               className={cn(
                  'absolute max-w-[17.5rem] px-4 py-3 shadow-md',
                  isSheet
                     ? 'right-[7%] bottom-[18%] border border-[#ead9b4] border-l-[3px] border-l-[color:var(--ssu-gold)] bg-[#f7f1e4]'
                     : 'right-3 bottom-3 left-3 max-w-none bg-[color:var(--ssu-navy)] text-white',
               )}
            >
               <p
                  className={cn(
                     'font-mono text-[10px] tracking-[0.18em] uppercase',
                     isSheet ? 'text-[color:var(--ssu-navy)]' : 'text-[color:var(--ssu-gold)]',
                  )}
               >
                  {active.label}
               </p>
               <p className={cn('ssu-pub-display mt-1 text-2xl', isSheet ? 'text-[color:var(--ssu-navy)]' : 'text-white')}>
                  {active.value}
               </p>
               <p className={cn('mt-1 text-[11px] leading-relaxed', isSheet ? 'text-[color:var(--ssu-muted)]' : 'text-white/70')}>
                  {active.note}
               </p>
            </div>
         ) : null}
      </div>
   );

   if (!isSheet) {
      return <div className={cn('relative overflow-hidden border border-[color:var(--ssu-line)] bg-[#d7e2de]', className)}>{canvas}</div>;
   }

   return (
      <div className={cn('relative pb-6 pr-2', className)}>
         <div className="pointer-events-none absolute inset-x-4 top-5 bottom-2 bg-[#c5d0cb]" aria-hidden />
         <div className="relative overflow-hidden border border-[#b7c4bf] bg-[#d7e2de] shadow-[0_22px_44px_rgb(22_33_49_/_16%)]">
            {canvas}
            <div className="flex items-center justify-between border-t border-[color:var(--ssu-navy)]/10 px-4 py-2 font-mono text-[9px] tracking-[0.16em] text-[color:var(--ssu-navy)]/55 uppercase">
               <span>Issued for estimating</span>
               <span>Sheet #1 / 04</span>
            </div>
         </div>

         <div className="absolute right-0 -bottom-1 z-10 flex bg-[color:var(--ssu-gold)] px-3 py-2 text-[color:var(--ssu-navy)] shadow-md">
            <div>
               <p className="flex items-center gap-1.5 font-mono text-[9px] tracking-[0.16em] uppercase">
                  <span className="inline-block border-y-[5px] border-l-[7px] border-y-transparent border-l-[color:var(--ssu-navy)]" aria-hidden />
                  Inspect a plan
               </p>
               <p className="mt-1.5 flex items-center gap-2 font-mono text-[8px] tracking-[0.16em] uppercase">
                  <span className="h-px w-7 bg-[color:var(--ssu-navy)]" aria-hidden />
                  Measured
               </p>
            </div>
         </div>
      </div>
   );
};

export default SamplePlan;
