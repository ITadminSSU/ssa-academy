import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export interface LandingOverlayContent {
   version: string;
   headline: string;
   pains_title: string;
   pains: string[];
   solution_title: string;
   solution_html: string;
   cta_label: string;
   cta_url: string;
}

interface Props {
   overlay: LandingOverlayContent;
   force?: boolean;
}

const STORAGE_KEY = 'ssu.landingOverlay.dismissedVersion';

const isExternalUrl = (url: string) => /^(https?:|mailto:|tel:)/i.test(url);

const PainBarricadeIcon = ({ className, id }: { className?: string; id: string }) => (
   <svg className={className} viewBox="0 0 64 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
      <rect x="8" y="14" width="7" height="30" rx="1.5" fill="#d6c4a0" />
      <rect x="49" y="14" width="7" height="30" rx="1.5" fill="#d6c4a0" />
      <rect x="6" y="16" width="52" height="10" rx="1.5" fill="#2a2a2a" />
      <rect x="6" y="29" width="52" height="10" rx="1.5" fill="#2a2a2a" />
      <g clipPath={`url(#${id})`}>
         <g transform="rotate(-32 32 27)">
            {[-20, -8, 4, 16, 28, 40, 52].map((x) => (
               <rect key={x} x={x} y="8" width="8" height="40" fill="#f5c400" />
            ))}
         </g>
      </g>
      <circle cx="11.5" cy="10" r="4.2" fill="#f5a623" />
      <circle cx="11.5" cy="10" r="2" fill="#ffe08a" />
      <circle cx="52.5" cy="10" r="4.2" fill="#f5a623" />
      <circle cx="52.5" cy="10" r="2" fill="#ffe08a" />
      <defs>
         <clipPath id={id}>
            <rect x="6" y="16" width="52" height="10" rx="1.5" />
            <rect x="6" y="29" width="52" height="10" rx="1.5" />
         </clipPath>
      </defs>
   </svg>
);

/** Original CAD building sheet for the overlay panel (not a third-party asset). */
const OverlayBlueprintSheet = () => (
   <svg className="ssu-landing-overlay__sheet-svg" viewBox="0 0 1200 780" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice" aria-hidden>
      <g stroke="currentColor" strokeWidth="1.15" strokeLinecap="square" strokeLinejoin="miter">
         {[80, 160, 240, 320, 400, 480, 560, 640, 720].map((y) => (
            <path key={`h-${y}`} d={`M20 ${y} H1180`} strokeDasharray="3 11" opacity="0.16" />
         ))}
         {[80, 200, 320, 440, 560, 680, 800, 920, 1040, 1160].map((x) => (
            <path key={`v-${x}`} d={`M${x} 20 V760`} strokeDasharray="2 12" opacity="0.12" />
         ))}

         <path d="M40 740 L260 40" strokeDasharray="5 7" opacity="0.28" />
         <path d="M140 740 L420 28" strokeDasharray="4 8" opacity="0.22" />
         <path d="M1080 740 L820 36" strokeDasharray="5 7" opacity="0.24" />
         <path d="M1160 740 L980 80" strokeDasharray="3 9" opacity="0.18" />

         <path d="M70 720 V180 H210 V720 Z" opacity="0.7" />
         {Array.from({ length: 18 }, (_, i) => 210 + i * 28).map((y) => (
            <path key={`a-f-${y}`} d={`M70 ${y} H210`} opacity="0.28" />
         ))}
         {[95, 125, 155, 185].map((x) => (
            <path key={`a-c-${x}`} d={`M${x} 190 V710`} opacity="0.22" />
         ))}
         <path d="M140 180 V110" opacity="0.5" />
         <path d="M132 110 H148" opacity="0.4" />

         <path d="M210 320 H310 V430 H210" opacity="0.65" />
         <path d="M230 340 H290 V410 H230" opacity="0.35" />

         <path d="M250 720 L430 90 H780 L960 720 Z" opacity="0.85" />
         {Array.from({ length: 16 }, (_, i) => 140 + i * 34).map((y) => (
            <path key={`b-f-${y}`} d={`M${250 + (y - 90) * 0.28} ${y} L${960 - (y - 90) * 0.28} ${y}`} opacity="0.26" />
         ))}
         {[320, 400, 480, 560, 640, 720, 800, 880].map((x) => (
            <path key={`b-c-${x}`} d={`M${x} 110 V700`} opacity="0.2" />
         ))}
         <path d="M470 90 V48 H730 V90" opacity="0.7" />
         <path d="M500 48 V22" opacity="0.45" />
         <path d="M700 48 V28" opacity="0.4" />

         <path d="M960 720 V260 H1130 V720 Z" opacity="0.7" />
         {Array.from({ length: 14 }, (_, i) => 290 + i * 30).map((y) => (
            <path key={`c-f-${y}`} d={`M960 ${y} H1130`} opacity="0.26" />
         ))}
         {[990, 1025, 1060, 1095].map((x) => (
            <path key={`c-c-${x}`} d={`M${x} 270 V710`} opacity="0.22" />
         ))}

         <path d="M24 720 H1176" opacity="0.7" />
         <path d="M40 738 H1160" strokeDasharray="5 6" opacity="0.28" />
      </g>
   </svg>
);

const LandingOverlay = ({ overlay, force = false }: Props) => {
   const [open, setOpen] = useState(force);

   const dismiss = useCallback(() => {
      try {
         window.localStorage.setItem(STORAGE_KEY, overlay.version);
      } catch {
         // Ignore storage failures (private mode, blocked cookies).
      }

      setOpen(false);
   }, [overlay.version]);

   useEffect(() => {
      if (force) {
         setOpen(true);
         return;
      }

      try {
         setOpen(window.localStorage.getItem(STORAGE_KEY) !== overlay.version);
      } catch {
         setOpen(true);
      }
   }, [force, overlay.version]);

   useEffect(() => {
      if (!open) {
         return;
      }

      const previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';

      const onKeyDown = (event: KeyboardEvent) => {
         if (event.key === 'Escape') {
            dismiss();
         }
      };

      window.addEventListener('keydown', onKeyDown);

      return () => {
         document.body.style.overflow = previousOverflow;
         window.removeEventListener('keydown', onKeyDown);
      };
   }, [dismiss, open]);

   if (!open) {
      return null;
   }

   const ctaUrl = overlay.cta_url?.trim() || '';
   const ctaLabel = overlay.cta_label?.trim() || '';
   const showCta = ctaUrl !== '' && ctaLabel !== '';
   const solutionHtml = overlay.solution_html?.trim() || '';

   return (
      <div className="ssu-landing-overlay" role="dialog" aria-modal="true" aria-labelledby="ssu-landing-overlay-title">
         <div className="ssu-landing-overlay__backdrop" />
         <div className="ssu-landing-overlay__grid" aria-hidden />
         <div className="ssu-landing-overlay__glow ssu-landing-overlay__glow--tl" aria-hidden />
         <div className="ssu-landing-overlay__glow ssu-landing-overlay__glow--br" aria-hidden />

         <button type="button" className="ssu-landing-overlay__close" onClick={dismiss} aria-label="Close overlay">
            <X className="h-5 w-5" />
         </button>

         <div className="ssu-landing-overlay__panel">
            <div className="ssu-landing-overlay__sheet" aria-hidden>
               <OverlayBlueprintSheet />
            </div>
            <div className="ssu-landing-overlay__content">
            {overlay.headline && (
               <h1 id="ssu-landing-overlay-title" className="ssu-landing-overlay__headline">
                  {overlay.headline}
               </h1>
            )}

            {overlay.pains_title && <p className="ssu-landing-overlay__section-title">{overlay.pains_title}</p>}

            {overlay.pains.length > 0 && (
               <ul className="ssu-landing-overlay__pains">
                  {overlay.pains.map((pain, index) => (
                     <li key={`${index}-${pain}`}>
                        <PainBarricadeIcon id={`ssu-pain-barricade-${index}`} className="ssu-landing-overlay__pain-icon" />
                        <span>{pain}</span>
                     </li>
                  ))}
               </ul>
            )}

            {overlay.solution_title && <p className="ssu-landing-overlay__section-title">{overlay.solution_title}</p>}

            {solutionHtml !== '' && (
               <div className="ssu-landing-overlay__solution" dangerouslySetInnerHTML={{ __html: solutionHtml }} />
            )}

            {showCta && (
               <div className="ssu-landing-overlay__cta">
                  {isExternalUrl(ctaUrl) ? (
                     <a href={ctaUrl} className="ssu-landing-overlay__cta-button" target="_blank" rel="noopener noreferrer">
                        {ctaLabel}
                     </a>
                  ) : (
                     <Link href={ctaUrl} className="ssu-landing-overlay__cta-button">
                        {ctaLabel}
                     </Link>
                  )}
               </div>
            )}

            <Button type="button" variant="ghost" className={cn('ssu-landing-overlay__continue text-base')} onClick={dismiss}>
               Continue to site
            </Button>
            </div>
         </div>
      </div>
   );
};

export default LandingOverlay;
