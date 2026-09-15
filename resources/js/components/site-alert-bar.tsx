import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';

const DEFAULT_MESSAGE = 'The academy will be temporarily unavailable for scheduled maintenance.';

const SiteAlertBar = () => {
   const { system } = usePage<SharedData>().props;
   const fields = system?.fields;
   const enabled = Boolean(fields?.site_alert_enabled);
   const message = (fields?.site_alert_message ?? '').trim() || DEFAULT_MESSAGE;

   if (!enabled) {
      return null;
   }

   return (
      <div
         role="status"
         aria-live="polite"
         className="bg-amber-500 px-4 py-2.5 text-center text-sm font-medium text-amber-950"
      >
         <p className="mx-auto flex max-w-5xl items-start justify-center gap-2 leading-snug sm:items-center">
            <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 sm:mt-0" aria-hidden="true" />
            <span>{message}</span>
         </p>
      </div>
   );
};

export default SiteAlertBar;
