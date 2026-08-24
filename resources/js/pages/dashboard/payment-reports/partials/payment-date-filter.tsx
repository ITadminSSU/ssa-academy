import { Button } from '@/components/ui/button';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';

const PERIODS = [
   { id: 'all', label: 'All' },
   { id: '24h', label: 'Last 24 hours' },
   { id: '7d', label: 'Last 7 days' },
   { id: '30d', label: 'Last 30 days' },
] as const;

interface Props {
   routeName: string;
}

const PaymentDateFilter = ({ routeName }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url) as Record<string, string>;
   const selectedDate = urlParams.date || '';
   const activePeriod = selectedDate ? '' : urlParams.period || 'all';

   const visit = (next: { period?: string; date?: string }) => {
      const params = { ...urlParams };

      delete params.page;
      delete params.period;
      delete params.date;
      delete params.date_from;
      delete params.date_to;

      if (next.period) {
         params.period = next.period;
      }

      if (next.date) {
         params.date = next.date;
      }

      router.get(route(routeName, params), {}, { preserveState: true, preserveScroll: true });
   };

   return (
      <div className="border-border flex flex-wrap items-center gap-2 border-t px-6 py-4">
         {PERIODS.map((period) => (
            <Button
               key={period.id}
               type="button"
               size="sm"
               variant={activePeriod === period.id ? 'default' : 'outline'}
               onClick={() => visit({ period: period.id === 'all' ? undefined : period.id })}
            >
               {period.label}
            </Button>
         ))}

         <label className="text-muted-foreground ml-1 flex items-center gap-2 text-sm">
            <span>Date</span>
            <input
               type="date"
               value={selectedDate}
               onChange={(event) => visit({ date: event.target.value || undefined })}
               className="border-border focus:border-primary bg-background h-8 rounded-md border px-2 text-sm focus:ring-0 focus:outline-0"
            />
         </label>
      </div>
   );
};

export default PaymentDateFilter;
