import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PlanRef {
   id: number;
   title: string;
}

const UsExperiencePlanWorkspaceHeader = ({
   courseId,
   plan,
   current,
}: {
   courseId: number;
   plan: PlanRef;
   current: 'setup' | 'attempts';
}) => {
   return (
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
         <div className="flex flex-wrap items-center gap-2">
            <Button type="button" variant="ghost" asChild>
               <Link href={route('courses.edit', { course: courseId, tab: 'us-experience' })}>
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  All plans
               </Link>
            </Button>
            <h2 className="text-lg font-semibold">{plan.title}</h2>
         </div>
         <div className="flex flex-wrap gap-2">
            <Button type="button" size="sm" variant={current === 'setup' ? 'default' : 'outline'} asChild>
               <Link href={route('courses.edit', { course: courseId, tab: 'us-experience', plan: plan.id })}>Setup</Link>
            </Button>
            <Button type="button" size="sm" variant={current === 'attempts' ? 'default' : 'outline'} asChild>
               <Link href={route('courses.us-experience.attempts.index', { course: courseId, plan: plan.id })}>Attempts</Link>
            </Button>
         </div>
      </div>
   );
};

export default UsExperiencePlanWorkspaceHeader;
