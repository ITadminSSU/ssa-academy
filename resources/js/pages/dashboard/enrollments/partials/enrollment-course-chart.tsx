import { textOn } from '@/components/student-tag-pills';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface EnrollmentChartRow {
   course: string;
   enrollments: number;
}

export interface EnrollmentLabelOption {
   id: number;
   name: string;
   color: string;
}

type GraphView = 'number' | 'bar' | 'line';

export interface EnrollmentCourseOption {
   id: number;
   title: string;
}

const EnrollmentCourseChart = ({
   rows,
   labels,
   courses,
   couponCodes,
}: {
   rows: EnrollmentChartRow[];
   labels: EnrollmentLabelOption[];
   courses: EnrollmentCourseOption[];
   couponCodes: string[];
}) => {
   const page = usePage();
   const appliedIds = useMemo(() => {
      const raw = getQueryParams(page.url).tags ?? '';

      return raw
         .split(',')
         .map((id) => Number(id))
         .filter((id) => Number.isInteger(id) && id > 0);
   }, [page.url]);
   const appliedCourse = getQueryParams(page.url).course ?? '';
   const appliedCoupon = getQueryParams(page.url).coupon ?? '';
   const [view, setView] = useState<GraphView>('bar');
   const [selectedIds, setSelectedIds] = useState<number[]>(appliedIds);
   const [tagSearch, setTagSearch] = useState('');
   const [courseId, setCourseId] = useState(appliedCourse);
   const [coupon, setCoupon] = useState(appliedCoupon);

   useEffect(() => {
      setSelectedIds(appliedIds);
      setCourseId(appliedCourse);
      setCoupon(appliedCoupon);
   }, [appliedIds, appliedCourse, appliedCoupon]);

   const chartData = rows.map((row) => ({
      name: row.course.length > 28 ? `${row.course.slice(0, 28)}…` : row.course,
      fullName: row.course,
      enrollments: row.enrollments,
   }));
   const total = rows.reduce((sum, row) => sum + row.enrollments, 0);
   const search = tagSearch.trim().toLowerCase();
   const visibleLabels = labels.filter((label) => label.name.toLowerCase().includes(search));
   const allSelected = selectedIds.length === 0;

   const visit = (ids: number[], nextCourse: string, nextCoupon: string) => {
      const params: Record<string, string> = { ...getQueryParams(page.url) };
      delete params.page;

      if (ids.length > 0) {
         params.tags = ids.join(',');
      } else {
         delete params.tags;
      }

      if (nextCourse !== '') {
         params.course = nextCourse;
      } else {
         delete params.course;
      }

      if (nextCoupon !== '') {
         params.coupon = nextCoupon;
      } else {
         delete params.coupon;
      }

      router.get(route('course-enrollments.index'), params, {
         preserveScroll: true,
         preserveState: true,
         replace: true,
      });
   };

   const toggleLabel = (id: number) => {
      setSelectedIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
   };

   return (
      <div className="mb-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
         <Card className="p-4 sm:p-6">
            <div className="grid gap-4 lg:grid-cols-2">
               <div className="space-y-3">
                  <p className="text-sm font-medium">Filter by student tags</p>
                  <div className="flex flex-col gap-2 sm:flex-row">
                     <Input
                        value={tagSearch}
                        onChange={(event) => setTagSearch(event.target.value)}
                        placeholder="Search tags..."
                        aria-label="Search tags"
                     />
                     <div className="flex gap-2">
                        <Button type="button" onClick={() => visit(selectedIds, courseId, coupon)}>
                           Apply Filters
                        </Button>
                        <Button
                           type="button"
                           variant="outline"
                           onClick={() => {
                              setSelectedIds([]);
                              setTagSearch('');
                              setCourseId('');
                              setCoupon('');
                              visit([], '', '');
                           }}
                        >
                           Clear
                        </Button>
                     </div>
                  </div>
                  <div className="flex flex-wrap gap-2">
                     {(!search || 'all tags'.includes(search)) && <Chip label="All tags" selected={allSelected} onClick={() => setSelectedIds([])} />}
                     {visibleLabels.map((label) => (
                        <Chip
                           key={label.id}
                           label={label.name}
                           color={label.color}
                           selected={selectedIds.includes(label.id)}
                           onClick={() => toggleLabel(label.id)}
                        />
                     ))}
                  </div>
               </div>
               <div className="space-y-3">
                  <label className="block space-y-1.5 text-sm font-medium">
                     Enrolled course
                     <select
                        value={courseId}
                        onChange={(event) => setCourseId(event.target.value)}
                        className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm font-normal"
                     >
                        <option value="">All courses</option>
                        {courses.map((course) => (
                           <option key={course.id} value={String(course.id)}>
                              {course.title}
                           </option>
                        ))}
                     </select>
                  </label>
                  <label className="block space-y-1.5 text-sm font-medium">
                     Coupon used
                     <select
                        value={coupon}
                        onChange={(event) => setCoupon(event.target.value)}
                        className="border-input bg-background h-10 w-full rounded-md border px-3 text-sm font-normal"
                     >
                        <option value="">All coupons</option>
                        <option value="none">No coupon</option>
                        {couponCodes.map((code) => (
                           <option key={code} value={code}>
                              {code}
                           </option>
                        ))}
                     </select>
                  </label>
               </div>
            </div>

            <div className="mt-5 mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
               <div>
                  <h3 className="text-lg font-medium">Enrollments by course</h3>
                  <p className="text-muted-foreground mt-1 text-sm">Each figure is an enrollment. A student in two courses is counted twice.</p>
               </div>
               <div className="flex rounded-lg border p-1" role="group" aria-label="Chart view">
                  {(
                     [
                        ['number', 'Number'],
                        ['bar', 'Bar'],
                        ['line', 'Line'],
                     ] as Array<[GraphView, string]>
                  ).map(([value, label]) => (
                     <button
                        key={value}
                        type="button"
                        className={cn(
                           'rounded-md px-3 py-1.5 text-sm',
                           view === value ? 'bg-[#1A344F] text-white' : 'text-muted-foreground hover:bg-muted',
                        )}
                        aria-pressed={view === value}
                        onClick={() => setView(value)}
                     >
                        {label}
                     </button>
                  ))}
               </div>
            </div>

            {chartData.length === 0 ? (
               <p className="text-muted-foreground py-10 text-center text-sm">No course enrollments match this filter.</p>
            ) : null}

            {chartData.length > 0 && view === 'number' ? (
               <ul className="max-h-[480px] divide-y overflow-y-auto">
                  {chartData.map((row) => (
                     <li key={row.fullName} className="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>{row.fullName}</span>
                        <span className="font-semibold tabular-nums">{row.enrollments}</span>
                     </li>
                  ))}
               </ul>
            ) : null}

            {chartData.length > 0 && view === 'bar' ? (
               <div className="max-h-[480px] overflow-y-auto">
                  <div style={{ height: Math.max(280, chartData.length * 40) }}>
                     <ResponsiveContainer width="100%" height="100%">
                        <BarChart data={chartData} layout="vertical" margin={{ top: 0, right: 24, left: 8, bottom: 0 }}>
                           <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                           <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                           <YAxis type="category" dataKey="name" width={180} tickLine={false} axisLine={false} />
                           <Tooltip
                              formatter={(value) => [value, 'Enrollments']}
                              labelFormatter={(_, payload) => payload?.[0]?.payload?.fullName ?? ''}
                           />
                           <Bar dataKey="enrollments" fill="#1A344F" radius={[0, 6, 6, 0]} barSize={18} />
                        </BarChart>
                     </ResponsiveContainer>
                  </div>
               </div>
            ) : null}

            {chartData.length > 0 && view === 'line' ? (
               <div className="overflow-x-auto">
                  <div style={{ height: 280, minWidth: Math.max(640, chartData.length * 88) }}>
                     <ResponsiveContainer width="100%" height="100%">
                        <LineChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 8 }}>
                           <CartesianGrid strokeDasharray="3 3" vertical={false} />
                           <XAxis dataKey="name" tickLine={false} axisLine={false} interval={0} />
                           <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                           <Tooltip
                              formatter={(value) => [value, 'Enrollments']}
                              labelFormatter={(_, payload) => payload?.[0]?.payload?.fullName ?? ''}
                           />
                           <Line type="monotone" dataKey="enrollments" stroke="#1A344F" strokeWidth={2} dot={{ r: 4, fill: '#1A344F' }} />
                        </LineChart>
                     </ResponsiveContainer>
                  </div>
               </div>
            ) : null}
         </Card>

         <Card className="h-fit overflow-hidden p-0">
            <div className="bg-[#1A344F] px-4 py-3 text-sm font-semibold text-white">Course Enrollment Summary</div>
            {rows.length === 0 ? (
               <p className="text-muted-foreground px-4 py-6 text-sm">No course enrollments match this filter.</p>
            ) : (
               <ul className="max-h-[560px] overflow-y-auto">
                  {rows.map((row) => (
                     <li key={row.course} className="flex items-center justify-between gap-3 border-b px-4 py-3">
                        <span className="text-sm">{row.course}</span>
                        <span className="text-sm font-semibold tabular-nums">{row.enrollments}</span>
                     </li>
                  ))}
                  <li className="flex items-center justify-between gap-3 px-4 py-3">
                     <span className="text-sm font-semibold">Total enrollments</span>
                     <span className="text-sm font-semibold tabular-nums">{total}</span>
                  </li>
               </ul>
            )}
         </Card>
      </div>
   );
};

const Chip = ({ label, color, selected, onClick }: { label: string; color?: string; selected: boolean; onClick: () => void }) => (
   <button
      type="button"
      onClick={onClick}
      aria-pressed={selected}
      className={cn(
         'rounded-full border px-2.5 py-1 text-xs font-semibold',
         !color && (selected ? 'border-[#1A344F] bg-[#1A344F] text-white' : 'bg-muted text-foreground'),
         color && !selected && 'opacity-45',
      )}
      style={color ? { backgroundColor: color, color: textOn(color), borderColor: selected ? '#0f172a' : 'transparent' } : undefined}
   >
      {label}
   </button>
);

export default EnrollmentCourseChart;
