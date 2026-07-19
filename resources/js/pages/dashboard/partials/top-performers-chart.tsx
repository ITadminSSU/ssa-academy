import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { usePage } from '@inertiajs/react';
import { Link } from '@inertiajs/react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { DashboardProps } from '../index';

const TopPerformersChart = () => {
   const { props } = usePage<DashboardProps>();
   const { topPerformers, translate, auth } = props;
   const { dashboard } = translate;
   const isAdmin = auth.user.role === 'admin';
   const topPerformersUrl = isAdmin ? route('admin.top-performers.index') : route('top-performers.index');

   const chartData = topPerformers.map((performer) => ({
      name: performer.name.length > 18 ? `${performer.name.slice(0, 18)}…` : performer.name,
      fullName: performer.name,
      score: performer.score,
   }));

   return (
      <Card className="ssu-surface-card flex h-full flex-col p-6">
         <div className="mb-4 flex items-start justify-between gap-3">
            <div>
               <h3 className="text-lg font-medium">{dashboard.top_performers_chart ?? 'Top Performers'}</h3>
               <p className="text-muted-foreground mt-1 text-sm">
                  {(isAdmin ? dashboard.top_performers_chart_description_admin : dashboard.top_performers_chart_description) ??
                     (isAdmin
                        ? 'Highest-scoring learners across all courses based on quizzes, assignments, and practical activities.'
                        : 'Highest-scoring learners across your courses based on quizzes, assignments, and practical activities.')}
               </p>
            </div>
            <Button variant="outline" size="sm" asChild>
               <Link href={topPerformersUrl}>{dashboard.view_all_top_performers ?? 'View all'}</Link>
            </Button>
         </div>

         {chartData.length > 0 ? (
            <ResponsiveContainer width="100%" height={320}>
               <BarChart data={chartData} layout="vertical" margin={{ top: 0, right: 16, left: 8, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                  <XAxis type="number" domain={[0, 100]} tickLine={false} axisLine={false} unit="%" />
                  <YAxis type="category" dataKey="name" width={110} tickLine={false} axisLine={false} />
                  <Tooltip
                     formatter={(value) => [`${value}%`, 'Average score']}
                     labelFormatter={(_, payload) => payload?.[0]?.payload?.fullName ?? ''}
                  />
                  <Bar dataKey="score" fill="oklch(0.20 0.003 255)" radius={[0, 6, 6, 0]} barSize={18} />
               </BarChart>
            </ResponsiveContainer>
         ) : (
            <div className="text-muted-foreground flex flex-1 items-center justify-center text-sm">
               No graded learners yet.
            </div>
         )}
      </Card>
   );
};

export default TopPerformersChart;
