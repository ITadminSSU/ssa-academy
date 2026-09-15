import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useLayoutEffect, useRef } from 'react';

const DEFAULT_MESSAGE = 'The academy will be temporarily unavailable for scheduled maintenance.';
const OFFSET_VAR = '--site-alert-offset';

const SiteAlertBar = () => {
   const { system } = usePage<SharedData>().props;
   const fields = system?.fields;
   const enabled = Boolean(fields?.site_alert_enabled);
   const message = (fields?.site_alert_message ?? '').trim() || DEFAULT_MESSAGE;
   const barRef = useRef<HTMLDivElement>(null);

   useLayoutEffect(() => {
      const applyOffset = () => {
         const height = enabled ? (barRef.current?.offsetHeight ?? 0) : 0;
         document.documentElement.style.setProperty(OFFSET_VAR, `${height}px`);
      };

      applyOffset();
      window.addEventListener('resize', applyOffset);

      return () => {
         window.removeEventListener('resize', applyOffset);
         document.documentElement.style.removeProperty(OFFSET_VAR);
      };
   }, [enabled, message]);

   if (!enabled) {
      return null;
   }

   return (
      <div
         ref={barRef}
         role="status"
         aria-live="polite"
         className="fixed inset-x-0 top-0 z-[80] bg-amber-500 px-4 py-2.5 text-center text-sm font-medium text-amber-950 shadow-sm"
      >
         <p className="mx-auto flex max-w-5xl items-start justify-center gap-2 leading-snug sm:items-center">
            <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 sm:mt-0" aria-hidden="true" />
            <span>{message}</span>
         </p>
      </div>
   );
};

export default SiteAlertBar;
