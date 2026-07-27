import { Button } from '@/components/ui/button';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';
import { format, startOfMonth, subDays } from 'date-fns';
import { X } from 'lucide-react';

interface Props {
   routeName: string;
   labels: {
      registeredFrom: string;
      registeredTo: string;
      today: string;
      last7Days: string;
      thisMonth: string;
      clearDates: string;
   };
}

type DatePreset = 'today' | 'last_7_days' | 'this_month';

const toDateParam = (date: Date) => format(date, 'yyyy-MM-dd');

const presetRange = (preset: DatePreset): { from: string; to: string } => {
   const today = new Date();

   switch (preset) {
      case 'today':
         return { from: toDateParam(today), to: toDateParam(today) };
      case 'last_7_days':
         return { from: toDateParam(subDays(today, 6)), to: toDateParam(today) };
      case 'this_month':
         return { from: toDateParam(startOfMonth(today)), to: toDateParam(today) };
   }
};

const RegistrationDateFilter = ({ routeName, labels }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url) as Record<string, string>;
   const registeredFrom = urlParams['registered_from'] ?? '';
   const registeredTo = urlParams['registered_to'] ?? '';

   const navigateWithDates = (from: string, to: string) => {
      const params = { ...urlParams } as Record<string, string>;

      if (from) {
         params.registered_from = from;
      } else {
         delete params.registered_from;
      }

      if (to) {
         params.registered_to = to;
      } else {
         delete params.registered_to;
      }

      router.get(route(routeName, params), {}, { preserveState: true, preserveScroll: true });
   };

   const applyPreset = (preset: DatePreset) => {
      const { from, to } = presetRange(preset);
      navigateWithDates(from, to);
   };

   const clearDates = () => {
      navigateWithDates('', '');
   };

   const presetButtons: { key: DatePreset; label: string }[] = [
      { key: 'today', label: labels.today },
      { key: 'last_7_days', label: labels.last7Days },
      { key: 'this_month', label: labels.thisMonth },
   ];

   const activePreset = presetButtons.find(({ key }) => {
      const { from, to } = presetRange(key);
      return registeredFrom === from && registeredTo === to;
   })?.key;

   return (
      <div className="border-border flex flex-col gap-4 border-b px-6 py-4">
         <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
               <label className="flex flex-col gap-1.5 text-sm">
                  <span className="text-muted-foreground font-medium">{labels.registeredFrom}</span>
                  <input
                     type="date"
                     value={registeredFrom}
                     onChange={(event) => navigateWithDates(event.target.value, registeredTo)}
                     className="border-border focus:border-primary h-10 rounded-md border px-3 text-sm focus:ring-0 focus:outline-0"
                  />
               </label>

               <label className="flex flex-col gap-1.5 text-sm">
                  <span className="text-muted-foreground font-medium">{labels.registeredTo}</span>
                  <input
                     type="date"
                     value={registeredTo}
                     min={registeredFrom || undefined}
                     onChange={(event) => navigateWithDates(registeredFrom, event.target.value)}
                     className="border-border focus:border-primary h-10 rounded-md border px-3 text-sm focus:ring-0 focus:outline-0"
                  />
               </label>
            </div>

            <div className="flex flex-wrap items-center gap-2">
               {presetButtons.map((preset) => (
                  <Button
                     key={preset.key}
                     type="button"
                     size="sm"
                     variant={activePreset === preset.key ? 'default' : 'outline'}
                     onClick={() => applyPreset(preset.key)}
                  >
                     {preset.label}
                  </Button>
               ))}

               {(registeredFrom || registeredTo) && (
                  <Button type="button" size="sm" variant="ghost" onClick={clearDates} className="text-muted-foreground">
                     <X className="mr-1 h-4 w-4" />
                     {labels.clearDates}
                  </Button>
               )}
            </div>
         </div>
      </div>
   );
};

export default RegistrationDateFilter;
