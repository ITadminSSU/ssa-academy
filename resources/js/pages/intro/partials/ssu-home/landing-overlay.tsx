import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { CircleX, X } from 'lucide-react';
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
                        <CircleX className="ssu-landing-overlay__pain-icon" aria-hidden />
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

            <Button type="button" variant="ghost" className={cn('ssu-landing-overlay__continue')} onClick={dismiss}>
               Continue to site
            </Button>
         </div>
      </div>
   );
};

export default LandingOverlay;
