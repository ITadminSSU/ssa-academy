import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getQueryParams } from '@/lib/route';
import { SharedData } from '@/types/global';
import { router, usePage } from '@inertiajs/react';

interface CourseOption {
   id: number;
   title: string;
}

interface Props {
   courses: CourseOption[];
}

const ActiveSubscriptionFilters = ({ courses }: Props) => {
   const page = usePage<SharedData>();
   const urlParams = getQueryParams(page.url) as Record<string, string>;
   const course = urlParams.course || 'all';
   const renewal = urlParams.renewal || 'all';

   const visit = (next: { course?: string; renewal?: string }) => {
      const params = { ...urlParams };

      delete params.page;

      if (next.course !== undefined) {
         if (next.course === 'all') {
            delete params.course;
         } else {
            params.course = next.course;
         }
      }

      if (next.renewal !== undefined) {
         if (next.renewal === 'all') {
            delete params.renewal;
         } else {
            params.renewal = next.renewal;
         }
      }

      router.get(route('payment-reports.active-subscriptions.index'), params, {
         preserveState: true,
         preserveScroll: true,
      });
   };

   return (
      <div className="flex flex-wrap items-center gap-2">
         <Select value={course} onValueChange={(value) => visit({ course: value })}>
            <SelectTrigger className="w-[220px]">
               <SelectValue placeholder="All courses" />
            </SelectTrigger>
            <SelectContent>
               <SelectItem value="all">All courses</SelectItem>
               {courses.map((item) => (
                  <SelectItem key={item.id} value={String(item.id)}>
                     {item.title}
                  </SelectItem>
               ))}
            </SelectContent>
         </Select>

         <Select value={renewal} onValueChange={(value) => visit({ renewal: value })}>
            <SelectTrigger className="w-[220px]">
               <SelectValue placeholder="Renews or cancels" />
            </SelectTrigger>
            <SelectContent>
               <SelectItem value="all">Renews or cancels</SelectItem>
               <SelectItem value="renews">Renews</SelectItem>
               <SelectItem value="cancels">Cancels at period end</SelectItem>
            </SelectContent>
         </Select>
      </div>
   );
};

export default ActiveSubscriptionFilters;
