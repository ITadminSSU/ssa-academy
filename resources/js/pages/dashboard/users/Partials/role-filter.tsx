import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';

interface RoleFilterOption {
   value: string;
   label: string;
}

interface Props {
   roleFilters: RoleFilterOption[];
   routeName: string;
}

const RoleFilter = ({ roleFilters, routeName }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url);
   const currentFilter = (urlParams['role_filter'] as string) ?? 'all';

   const handleChange = (value: string) => {
      const params = { ...urlParams } as Record<string, string>;

      if (value === 'all') {
         delete params.role_filter;
      } else {
         params.role_filter = value;
      }

      router.get(route(routeName, params), {}, { preserveState: true, preserveScroll: true });
   };

   const currentLabel = roleFilters.find((option) => option.value === currentFilter)?.label ?? 'All users';

   return (
      <Select value={currentFilter} onValueChange={handleChange}>
         <SelectTrigger className="w-[200px]">
            <SelectValue placeholder="Filter by role">{currentLabel}</SelectValue>
         </SelectTrigger>
         <SelectContent>
            {roleFilters.map((option) => (
               <SelectItem key={option.value} value={option.value}>
                  {option.label}
               </SelectItem>
            ))}
         </SelectContent>
      </Select>
   );
};

export default RoleFilter;
