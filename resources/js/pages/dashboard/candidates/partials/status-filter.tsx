import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';

interface StatusOption {
   value: string;
   label: string;
}

interface Props {
   statuses: StatusOption[];
   routeName: string;
}

const StatusFilter = ({ statuses, routeName }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url);
   const currentStatus = (urlParams['status'] as string) ?? 'all';

   const handleChange = (value: string) => {
      const params = { ...urlParams } as Record<string, string>;

      if (value === 'all') {
         delete params.status;
      } else {
         params.status = value;
      }

      router.get(route(routeName, params), {}, { preserveState: true, preserveScroll: true });
   };

   return (
      <Select value={currentStatus} onValueChange={handleChange}>
         <SelectTrigger className="w-[180px]">
            <SelectValue placeholder="Filter by status" />
         </SelectTrigger>
         <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            {statuses.map((status) => (
               <SelectItem key={status.value} value={status.value}>
                  {status.label}
               </SelectItem>
            ))}
         </SelectContent>
      </Select>
   );
};

export default StatusFilter;
