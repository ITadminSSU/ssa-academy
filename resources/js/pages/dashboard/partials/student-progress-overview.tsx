import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { useMemo } from 'react';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { DashboardProps } from '../index';

const COLORS = ['oklch(0.20 0.003 255)', 'oklch(0.62 0.20 25)', 'oklch(0.34 0.004 255)'];

const StudentProgressOverview = () => {
   const { props } = usePage<DashboardProps>();
   const { studentProgressOverview, translate, auth } = props;
   const { dashboard } = translate;
   const isAdmin = auth.user.role === 'admin';
   const progressUrl = isAdmin ? route('admin.student-progress.index') : route('student-progress.index');

   const chartData = useMemo(
      () => [
         {
            name: dashboard.student_progress_completed ?? 'Completed',
            value: studentProgressOverview.completed,
         },
         {
            name: dashboard.student_progress_in_progress ?? 'In Progress',
            value: studentProgressOverview.in_progress,
         },
         {
            name: dashboard.student_progress_not_started ?? 'Not Started',
            value: studentProgressOverview.not_started,
         },
      ],
      [dashboard, studentProgressOverview],
   );

   const total = studentProgressOverview.total;

   const legendItems = [
      { name: dashboard.student_progress_completed ?? 'Completed', color: COLORS[0] },
      { name: dashboard.student_progress_in_progress ?? 'In Progress', color: COLORS[1] },
      { name: dashboard.student_progress_not_started ?? 'Not Started', color: COLORS[2] },
   ];

   return (
      <Card className="ssu-surface-card flex h-full flex-col p-6">
         <div className="mb-4 flex items-start justify-between gap-3">
            <div>
               <h3 className="text-lg font-medium">{dashboard.student_progress_overview ?? 'Student Progress Overview'}</h3>
               <p className="text-muted-foreground mt-1 text-sm">
                  {isAdmin
                     ? (dashboard.student_progress_enrollments_admin ?? `${total} enrollment${total === 1 ? '' : 's'} across all courses`).replace(
                          ':total',
                          String(total),
                       )
                     : `${total} enrollment${total === 1 ? '' : 's'} across your courses`}
               </p>
            </div>
            <Button variant="outline" size="sm" asChild>
               <Link href={progressUrl}>{dashboard.view_student_progress ?? 'View progress'}</Link>
            </Button>
         </div>

         {total > 0 ? (
            <div className="flex flex-1 flex-col items-center">
               <div className="relative w-full" style={{ height: 240 }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <PieChart margin={{ top: 0, right: 0, bottom: 0, left: 0 }}>
                        <Pie
                           data={chartData}
                           cx="50%"
                           cy="50%"
                           innerRadius={70}
                           outerRadius={100}
                           paddingAngle={2}
                           dataKey="value"
                           isAnimationActive={false}
                        >
                           {chartData.map((entry, index) => (
                              <Cell key={entry.name} fill={COLORS[index % COLORS.length]} />
                           ))}
                        </Pie>
                        <Tooltip formatter={(value, name) => [value, name]} />
                     </PieChart>
                  </ResponsiveContainer>
                  <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                     <span className="text-2xl font-semibold">{total}</span>
                     <span className="text-muted-foreground text-xs">Students</span>
                  </div>
               </div>

               <ul className="mt-2 flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
                  {legendItems.map((item) => (
                     <li key={item.name} className="flex items-center gap-1.5 text-xs">
                        <span className="inline-block h-2.5 w-2.5 rounded-full" style={{ backgroundColor: item.color }} />
                        <span className="text-muted-foreground">{item.name}</span>
                     </li>
                  ))}
               </ul>
            </div>
         ) : (
            <div className="text-muted-foreground flex flex-1 items-center justify-center text-sm">No enrollments yet.</div>
         )}
      </Card>
   );
};

export default StudentProgressOverview;
