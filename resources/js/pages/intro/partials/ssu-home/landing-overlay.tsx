import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

export interface LandingOverlayContent {
   version: string;
   headline: string;
   pains_title: string;
   pains_intro?: string;
   pains: string[];
   solution_title: string;
   highlight: string;
   solution_html: string;
   cta_label: string;
   cta_url: string;
   panel_width?: number;
   sizes?: {
      headline: number;
      pains_title: number;
      pains_intro: number;
      pains: number;
      solution_title: number;
      highlight: number;
      solution: number;
   };
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

type IsoPt = { x: number; y: number };
type IsoFn = (x: number, y: number, z: number) => IsoPt;

const isoAt = (ox: number, oy: number, scale = 17): IsoFn => (x, y, z) => ({
   x: ox + (x - y) * scale,
   y: oy + (x + y) * (scale * 0.5) - z * (scale * 0.88),
});

const p = (pt: IsoPt) => `${pt.x.toFixed(1)} ${pt.y.toFixed(1)}`;

const Crosshair = ({ pt, size = 5, opacity = 0.55 }: { pt: IsoPt; size?: number; opacity?: number }) => (
   <g opacity={opacity}>
      <path d={`M${(pt.x - size).toFixed(1)} ${pt.y.toFixed(1)} H${(pt.x + size).toFixed(1)}`} />
      <path d={`M${pt.x.toFixed(1)} ${(pt.y - size).toFixed(1)} V${(pt.y + size).toFixed(1)}`} />
   </g>
);

const IsoBox = ({
   iso,
   x,
   y,
   w,
   d,
   h,
   floors = 0,
   columns = 0,
   opacity = 0.9,
}: {
   iso: IsoFn;
   x: number;
   y: number;
   w: number;
   d: number;
   h: number;
   floors?: number;
   columns?: number;
   opacity?: number;
}) => {
   const sw = iso(x, y, 0);
   const se = iso(x + w, y, 0);
   const ne = iso(x + w, y + d, 0);
   const nw = iso(x, y + d, 0);
   const tw = iso(x, y, h);
   const te = iso(x + w, y, h);
   const tn = iso(x + w, y + d, h);
   const tf = iso(x, y + d, h);
   const floorCount = floors || Math.max(2, Math.round(h));
   const colCount = columns || Math.max(2, Math.round(Math.max(w, d)));

   return (
      <g opacity={opacity}>
         <path d={`M${p(se)} L${p(ne)} L${p(tn)} L${p(te)} Z`} />
         <path d={`M${p(nw)} L${p(ne)} L${p(tn)} L${p(tf)} Z`} />
         <path d={`M${p(tw)} L${p(te)} L${p(tn)} L${p(tf)} Z`} />
         <path d={`M${p(sw)} L${p(se)}`} opacity="0.35" />
         <path d={`M${p(sw)} L${p(nw)}`} opacity="0.28" />
         <path d={`M${p(sw)} L${p(tw)}`} opacity="0.28" />

         {Array.from({ length: floorCount - 1 }, (_, i) => {
            const z = ((i + 1) / floorCount) * h;
            return (
               <g key={`fl-${x}-${y}-${i}`} opacity="0.28">
                  <path d={`M${p(iso(x + w, y, z))} L${p(iso(x + w, y + d, z))}`} />
                  <path d={`M${p(iso(x, y + d, z))} L${p(iso(x + w, y + d, z))}`} />
               </g>
            );
         })}

         {Array.from({ length: colCount - 1 }, (_, i) => {
            const gx = x + ((i + 1) / colCount) * w;
            const gy = y + ((i + 1) / colCount) * d;
            return (
               <g key={`col-${x}-${y}-${i}`} opacity="0.22">
                  <path d={`M${p(iso(x + w, gy, 0))} L${p(iso(x + w, gy, h))}`} />
                  <path d={`M${p(iso(gx, y + d, 0))} L${p(iso(gx, y + d, h))}`} />
               </g>
            );
         })}

         <Crosshair pt={te} />
         <Crosshair pt={tn} />
         <Crosshair pt={tf} />
         <Crosshair pt={tw} opacity={0.35} size={4} />
         <Crosshair pt={ne} opacity={0.4} size={4} />
      </g>
   );
};

/** Original isometric CAD sheet for the overlay panel (not a third-party asset). */
const OverlayBlueprintSheet = () => {
   const iso = isoAt(930, 690, 18);

   return (
      <svg
         className="ssu-landing-overlay__sheet-svg"
         viewBox="0 0 1200 780"
         fill="none"
         xmlns="http://www.w3.org/2000/svg"
         preserveAspectRatio="xMaxYMax slice"
         aria-hidden
      >
         <g stroke="currentColor" strokeWidth="0.95" strokeLinecap="square" strokeLinejoin="miter">
            {Array.from({ length: 18 }, (_, i) => i - 4).map((n) => (
               <path
                  key={`g-r-${n}`}
                  d={`M${p(iso(n, -8, 0))} L${p(iso(n, 28, 0))}`}
                  opacity={n > 2 ? 0.12 : 0.05}
               />
            ))}
            {Array.from({ length: 22 }, (_, i) => i - 6).map((n) => (
               <path
                  key={`g-l-${n}`}
                  d={`M${p(iso(-6, n, 0))} L${p(iso(26, n, 0))}`}
                  opacity={n > 0 ? 0.1 : 0.045}
               />
            ))}

            <path d={`M${p(iso(8, 4, 22))} L${p(iso(-18, -22, 38))}`} strokeDasharray="6 8" opacity="0.22" />
            <path d={`M${p(iso(18, 8, 12))} L${p(iso(-8, -28, 30))}`} strokeDasharray="5 9" opacity="0.16" />
            <path d={`M${p(iso(4, 14, 8))} L${p(iso(-24, -6, 26))}`} strokeDasharray="4 10" opacity="0.14" />
            <path d={`M${p(iso(22, 2, 0))} L${p(iso(38, -16, 0))}`} strokeDasharray="7 7" opacity="0.14" />
            <path d={`M${p(iso(20, 16, 0))} L${p(iso(36, 32, 0))}`} strokeDasharray="6 8" opacity="0.12" />

            <IsoBox iso={iso} x={-2} y={-3} w={26} d={18} h={2.2} floors={2} columns={10} opacity={0.45} />
            <IsoBox iso={iso} x={1} y={1} w={8} d={7} h={22} floors={14} columns={5} opacity={0.95} />
            <IsoBox iso={iso} x={10} y={0} w={12} d={8} h={11} floors={8} columns={7} opacity={0.88} />
            <IsoBox iso={iso} x={3} y={9} w={9} d={7} h={7} floors={5} columns={5} opacity={0.8} />
            <IsoBox iso={iso} x={16} y={9} w={6} d={6} h={16} floors={10} columns={4} opacity={0.9} />
            <IsoBox iso={iso} x={22} y={2} w={5} d={5} h={6} floors={4} columns={3} opacity={0.7} />

            <path d={`M${p(iso(1, 1, 22))} L${p(iso(1, 1, 26))}`} opacity="0.5" />
            <path d={`M${p(iso(9, 1, 22))} L${p(iso(9, 1, 25))}`} opacity="0.4" />
            <path d={`M${p(iso(16, 9, 16))} L${p(iso(16, 9, 19))}`} opacity="0.4" />
            <Crosshair pt={iso(1, 1, 26)} size={6} />
            <Crosshair pt={iso(9, 1, 25)} size={5} opacity={0.4} />
            <Crosshair pt={iso(16, 9, 19)} size={5} opacity={0.4} />

            <path d={`M${p(iso(-4, 16, 0))} L${p(iso(28, 16, 0))}`} opacity="0.2" />
            <path d={`M${p(iso(24, -4, 0))} L${p(iso(24, 20, 0))}`} opacity="0.18" />
         </g>
      </svg>
   );
};

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
   const sizes = overlay.sizes;
   const px = (value: number | undefined, fallback: number) => ({ fontSize: `${value || fallback}px` });

   return (
      <div className="ssu-landing-overlay" role="dialog" aria-modal="true" aria-labelledby="ssu-landing-overlay-title">
         <div className="ssu-landing-overlay__backdrop" />
         <div className="ssu-landing-overlay__grid" aria-hidden />
         <div className="ssu-landing-overlay__glow ssu-landing-overlay__glow--tl" aria-hidden />
         <div className="ssu-landing-overlay__glow ssu-landing-overlay__glow--br" aria-hidden />

         <button type="button" className="ssu-landing-overlay__close" onClick={dismiss} aria-label="Close overlay">
            <X className="h-5 w-5" />
         </button>

         <div
            className="ssu-landing-overlay__panel"
            style={{ maxWidth: `${overlay.panel_width || 1024}px` }}
         >
            <div className="ssu-landing-overlay__sheet" aria-hidden>
               <OverlayBlueprintSheet />
            </div>
            <div className="ssu-landing-overlay__content">
            {overlay.headline && (
               <h1 id="ssu-landing-overlay-title" className="ssu-landing-overlay__headline" style={px(sizes?.headline, 56)}>
                  {overlay.headline}
               </h1>
            )}

            {overlay.pains_title && (
               <p className="ssu-landing-overlay__section-title" style={px(sizes?.pains_title, 20)}>
                  {overlay.pains_title}
               </p>
            )}

            {overlay.pains_intro?.trim() && (
               <p className="ssu-landing-overlay__section-title" style={px(sizes?.pains_intro, 20)}>
                  {overlay.pains_intro}
               </p>
            )}

            {overlay.pains.length > 0 && (
               <ul className="ssu-landing-overlay__pains" style={px(sizes?.pains, 16)}>
                  {overlay.pains.map((pain, index) => (
                     <li key={`${index}-${pain}`}>
                        <PainBarricadeIcon id={`ssu-pain-barricade-${index}`} className="ssu-landing-overlay__pain-icon" />
                        <span>{pain}</span>
                     </li>
                  ))}
               </ul>
            )}

            {overlay.solution_title && (
               <p className="ssu-landing-overlay__section-title" style={px(sizes?.solution_title, 20)}>
                  {overlay.solution_title}
               </p>
            )}

            {overlay.highlight?.trim() && (
               <p className="ssu-landing-overlay__highlight-wrap">
                  <span className="ssu-landing-overlay__highlight" style={px(sizes?.highlight, 19)}>
                     {overlay.highlight}
                  </span>
               </p>
            )}

            {solutionHtml !== '' && (
               <div
                  className="ssu-landing-overlay__solution"
                  style={px(sizes?.solution, 16)}
                  dangerouslySetInnerHTML={{ __html: solutionHtml }}
               />
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
