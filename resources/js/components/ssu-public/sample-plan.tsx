import { cn } from '@/lib/utils';
import { useState } from 'react';

const hotspots = [
   { id: 'wall', label: 'Wall length', value: '24′ 06″', note: 'Sample measure from the exterior wall line.', x: '18%', y: '42%' },
   { id: 'openings', label: 'Opening count', value: '04', note: 'Windows and doors marked on this sheet.', x: '62%', y: '28%' },
   { id: 'takeoff', label: 'Takeoff note', value: 'QTY / VERIFY', note: 'Inspect the measurement before you count.', x: '72%', y: '68%' },
];

const SamplePlan = ({ className }: { className?: string }) => {
   const [active, setActive] = useState<(typeof hotspots)[number] | null>(hotspots[0]);

   return (
      <div className={cn('relative overflow-hidden border border-[color:var(--ssu-line)] bg-[#f3f1ea]', className)}>
         <svg viewBox="0 0 640 420" className="h-auto w-full text-[color:var(--ssu-navy)]" aria-hidden>
            <rect width="640" height="420" fill="#f7f4ec" />
            <g stroke="currentColor" strokeOpacity="0.08" strokeWidth="1">
               {Array.from({ length: 16 }).map((_, i) => (
                  <line key={`v-${i}`} x1={i * 40} y1="0" x2={i * 40} y2="420" />
               ))}
               {Array.from({ length: 11 }).map((_, i) => (
                  <line key={`h-${i}`} x1="0" y1={i * 40} x2="640" y2={i * 40} />
               ))}
            </g>
            <text x="24" y="28" fill="#1a344f" fontSize="10" letterSpacing="1.6" fontFamily="DM Mono, monospace">
               SAMPLE PLAN / A-104
            </text>
            <text x="520" y="28" fill="#e89a1b" fontSize="10" letterSpacing="1.6" fontFamily="DM Mono, monospace">
               1:48 SCALE
            </text>
            <g fill="none" stroke="#1a344f" strokeWidth="2">
               <rect x="70" y="70" width="500" height="290" />
               <rect x="70" y="70" width="220" height="150" />
               <rect x="290" y="70" width="280" height="150" />
               <rect x="70" y="220" width="500" height="140" />
               <path d="M180 70 V220" />
               <path d="M430 220 V360" />
            </g>
            <text x="110" y="150" fill="#1a344f" fontSize="11" letterSpacing="1.4" fontFamily="DM Mono, monospace">
               BEDROOM / 01
            </text>
            <text x="340" y="150" fill="#1a344f" fontSize="11" letterSpacing="1.4" fontFamily="DM Mono, monospace">
               LIVING / 02
            </text>
            <text x="90" y="390" fill="#e89a1b" fontSize="10" letterSpacing="1.6" fontFamily="DM Mono, monospace">
               FOUNDATION LINE / VERIFY
            </text>
            <text x="430" y="390" fill="#1a344f" fontSize="10" letterSpacing="1.6" fontFamily="DM Mono, monospace">
               WINDOW SCHEDULE
            </text>
            <text x="88" y="64" fill="#e89a1b" fontSize="12" fontFamily="DM Mono, monospace">
               24′ 06″
            </text>
            <text x="310" y="64" fill="#1a344f" fontSize="12" fontFamily="DM Mono, monospace">
               18′ 00″
            </text>
         </svg>

         {hotspots.map((spot) => (
            <button
               key={spot.id}
               type="button"
               aria-label={`Show ${spot.label}`}
               onClick={() => setActive(spot)}
               onMouseEnter={() => setActive(spot)}
               onFocus={() => setActive(spot)}
               className={cn(
                  'absolute h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-md transition',
                  active?.id === spot.id ? 'bg-[color:var(--ssu-gold)] scale-110' : 'bg-[color:var(--ssu-navy)]',
               )}
               style={{ left: spot.x, top: spot.y }}
            />
         ))}

         {active ? (
            <div className="absolute bottom-3 left-3 right-3 bg-[color:var(--ssu-navy)] px-4 py-3 text-white shadow-lg">
               <p className="font-mono text-[10px] tracking-[0.18em] text-[color:var(--ssu-gold)] uppercase">{active.label}</p>
               <p className="font-display mt-1 text-xl font-semibold">{active.value}</p>
               <p className="mt-1 text-xs text-white/70">{active.note}</p>
            </div>
         ) : null}
      </div>
   );
};

export default SamplePlan;
