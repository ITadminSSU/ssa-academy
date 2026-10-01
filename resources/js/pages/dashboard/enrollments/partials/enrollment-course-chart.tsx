import { Card } from '@/components/ui/card';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface EnrollmentChartRow {
   course: string;
   students: number;
}

const EnrollmentCourseChart = ({ rows }: { rows: EnrollmentChartRow[] }) => {
   const chartData = rows.map((row) => ({
      name: row.course.length > 28 ? `${row.course.slice(0, 28)}…` : row.course,
      fullName: row.course,
      students: row.students,
   }));
   const height = Math.max(280, chartData.length * 40);

   return (
      <Card className="mb-6 p-6">
         <div className="mb-4">
            <h3 className="text-lg font-medium">Enrolled students by course</h3>
            <p className="text-muted-foreground mt-1 text-sm">Each bar is the number of distinct students enrolled in that course.</p>
         </div>

         {chartData.length > 0 ? (
            <div className="max-h-[480px] overflow-y-auto">
               <div style={{ height }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <BarChart data={chartData} layout="vertical" margin={{ top: 0, right: 24, left: 8, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                        <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                        <YAxis type="category" dataKey="name" width={180} tickLine={false} axisLine={false} />
                        <Tooltip
                           formatter={(value) => [value, 'Students']}
                           labelFormatter={(_, payload) => payload?.[0]?.payload?.fullName ?? ''}
                        />
                        <Bar dataKey="students" fill="#1A344F" radius={[0, 6, 6, 0]} barSize={18} />
                     </BarChart>
                  </ResponsiveContainer>
               </div>
            </div>
         ) : (
            <p className="text-muted-foreground py-10 text-center text-sm">No course enrollments yet.</p>
         )}
      </Card>
   );
};

export default EnrollmentCourseChart;
