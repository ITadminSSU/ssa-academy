import { textOn, StudentLabelPill } from '@/components/student-tag-pills';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface EnrollmentChartRow {
   label: string;
   students: number;
   kind: 'course' | 'group';
}

export interface EnrollmentOverview {
   rows: EnrollmentChartRow[];
   total_students: number;
   unfiltered_total_students: number;
   filtered: boolean;
}

interface ChartFilter {
   label_ids: number[];
   pef: boolean;
}

type GraphView = 'number' | 'bar' | 'line';

const truncate = (label: string) => (label.length > 28 ? `${label.slice(0, 28)}…` : label);

const EnrollmentCourseChart = ({
   overview,
   labels,
   chartFilter,
}: {
   overview: EnrollmentOverview;
   labels: StudentLabelPill[];
   chartFilter: ChartFilter;
}) => {
   const page = usePage();
   const [view, setView] = useState<GraphView>('bar');
   const [selectedIds, setSelectedIds] = useState<number[]>(chartFilter.label_ids);
   const [pefSelected, setPefSelected] = useState(chartFilter.pef);
   const [tagSearch, setTagSearch] = useState('');

   useEffect(() => {
      setSelectedIds(chartFilter.label_ids);
      setPefSelected(chartFilter.pef);
   }, [chartFilter.label_ids, chartFilter.pef]);

   const allTags = selectedIds.length === 0 && !pefSelected;
   const search = tagSearch.trim().toLowerCase();
   const visibleLabels = labels.filter((label) => label.name.toLowerCase().includes(search));
   const showAllChip = 'all tags'.includes(search);
   const showPefChip = 'pef'.includes(search);

   const chartData = useMemo(
      () =>
         overview.rows.map((row) => ({
            name: truncate(row.label),
            fullName: row.label,
            students: row.students,
            kind: row.kind,
         })),
      [overview.rows],
   );

   const visit = (nextIds: number[], nextPef: boolean) => {
      const params: Record<string, string> = { ...getQueryParams(page.url) };
      delete params.chart_labels;
      delete params.chart_pef;

      if (nextIds.length > 0) {
         params.chart_labels = nextIds.join(',');
      }

      if (nextPef) {
         params.chart_pef = '1';
      }

      router.get(route('course-enrollments.index'), params, {
         preserveScroll: true,
         preserveState: true,
         replace: true,
      });
   };

   const applyFilters = () => visit(selectedIds, pefSelected);

   const clearFilters = () => {
      setSelectedIds([]);
      setPefSelected(false);
      setTagSearch('');
      visit([], false);
   };

   const toggleLabel = (id: number) => {
      setSelectedIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
   };

   const height = Math.max(280, chartData.length * 40);
   const lineWidth = Math.max(640, chartData.length * 72);
   const hasCourses = overview.rows.some((row) => row.kind === 'course');

   return (
      <div className="mb-6 grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
         <Card className="p-4 sm:p-6">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
               <div>
                  <h3 className="text-lg font-medium">Enrolled students by course</h3>
                  <p className="text-muted-foreground mt-1 text-sm">Each course is the number of distinct students enrolled in it.</p>
               </div>
               <div className="flex rounded-lg border p-1">
                  {(
                     [
                        ['number', 'Number'],
                        ['bar', 'Bar graph'],
                        ['line', 'Line graph'],
                     ] as Array<[GraphView, string]>
                  ).map(([value, label]) => (
                     <button
                        key={value}
                        type="button"
                        className={cn(
                           'rounded-md px-3 py-1.5 text-sm',
                           view === value ? 'bg-[#1A344F] text-white' : 'text-muted-foreground hover:bg-muted',
                        )}
                        onClick={() => setView(value)}
                     >
                        {label}
                     </button>
                  ))}
               </div>
            </div>

            <div className="mb-4 space-y-3">
               <p className="text-sm font-medium">Filter by Student Tags</p>
               <div className="flex flex-col gap-2 sm:flex-row">
                  <Input
                     value={tagSearch}
                     onChange={(event) => setTagSearch(event.target.value)}
                     placeholder="Search tags..."
                     aria-label="Search tags"
                  />
                  <div className="flex gap-2">
                     <Button type="button" onClick={applyFilters}>
                        Apply Filters
                     </Button>
                     <Button type="button" variant="outline" onClick={clearFilters}>
                        Clear
                     </Button>
                  </div>
               </div>
               <div className="flex flex-wrap gap-2">
                  {showAllChip ? (
                     <TagChip label="All Tags" selected={allTags} onClick={() => { setSelectedIds([]); setPefSelected(false); }} />
                  ) : null}
                  {showPefChip ? (
                     <TagChip label="PEF" selected={pefSelected} onClick={() => setPefSelected((current) => !current)} />
                  ) : null}
                  {visibleLabels.map((label) => (
                     <TagChip
                        key={label.id}
                        label={label.name}
                        color={label.color}
                        selected={selectedIds.includes(label.id)}
                        onClick={() => toggleLabel(label.id)}
                     />
                  ))}
                  {labels.length === 0 ? <p className="text-muted-foreground text-xs">No labels yet.</p> : null}
               </div>
            </div>

            {view === 'number' ? (
               <NumberList rows={overview.rows} hasCourses={hasCourses} />
            ) : null}

            {view === 'bar' ? (
               hasCourses || overview.total_students > 0 ? (
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
               )
            ) : null}

            {view === 'line' ? (
               hasCourses || overview.total_students > 0 ? (
                  <div className="overflow-x-auto">
                     <div style={{ width: lineWidth, height: 320 }}>
                        <ResponsiveContainer width="100%" height="100%">
                           <LineChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 64 }}>
                              <CartesianGrid strokeDasharray="3 3" vertical={false} />
                              <XAxis dataKey="name" interval={0} angle={-35} textAnchor="end" height={70} tickLine={false} axisLine={false} />
                              <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                              <Tooltip
                                 formatter={(value) => [value, 'Students']}
                                 labelFormatter={(_, payload) => payload?.[0]?.payload?.fullName ?? ''}
                              />
                              <Line type="monotone" dataKey="students" stroke="#1A344F" strokeWidth={2} dot={{ r: 4, fill: '#1A344F' }} />
                           </LineChart>
                        </ResponsiveContainer>
                     </div>
                  </div>
               ) : (
                  <p className="text-muted-foreground py-10 text-center text-sm">No course enrollments yet.</p>
               )
            ) : null}
         </Card>

         <Card className="h-fit overflow-hidden p-0">
            <div className="bg-[#1A344F] px-4 py-3 text-sm font-semibold text-white">Course Enrollment Summary</div>
            <ul>
               {overview.rows.map((row, index) => (
                  <li key={`${row.kind}-${row.label}`} className="border-b px-4 py-3">
                     <div className="flex items-start justify-between gap-3">
                        <span className="text-sm" title={row.label}>
                           {truncate(row.label)}
                        </span>
                        <span className="text-sm font-semibold tabular-nums">{row.students}</span>
                     </div>
                     {index === 0 && overview.filtered ? (
                        <p className="text-muted-foreground mt-1 text-xs">({overview.unfiltered_total_students} from previous view shown)</p>
                     ) : null}
                  </li>
               ))}
               <li className="flex items-center justify-between gap-3 px-4 py-3">
                  <span className="text-sm font-semibold">Total enrollments</span>
                  <span className="text-sm font-semibold tabular-nums">{overview.total_students}</span>
               </li>
            </ul>
         </Card>
      </div>
   );
};

const NumberList = ({ rows, hasCourses }: { rows: EnrollmentChartRow[]; hasCourses: boolean }) => {
   if (!hasCourses && rows.every((row) => row.students === 0)) {
      return <p className="text-muted-foreground py-10 text-center text-sm">No course enrollments yet.</p>;
   }

   return (
      <ul className="divide-y">
         {rows.map((row) => (
            <li
               key={`${row.kind}-${row.label}`}
               className={cn('flex items-center justify-between gap-3 py-2', row.kind === 'group' && row.label === 'Pre-registered Only' && 'mt-2 border-t')}
               title={row.label}
            >
               <span className="text-sm">{truncate(row.label)}</span>
               <span className="text-sm font-semibold tabular-nums">{row.students}</span>
            </li>
         ))}
      </ul>
   );
};

const TagChip = ({
   label,
   color,
   selected,
   onClick,
}: {
   label: string;
   color?: string;
   selected: boolean;
   onClick: () => void;
}) => (
   <button
      type="button"
      onClick={onClick}
      className={cn(
         'rounded-full border px-2.5 py-1 text-xs font-semibold',
         !color && (selected ? 'border-[#1A344F] bg-[#1A344F] text-white' : 'bg-muted text-foreground'),
         !selected && 'opacity-45',
      )}
      style={
         color
            ? { backgroundColor: color, color: textOn(color), borderColor: selected ? '#0f172a' : 'transparent' }
            : undefined
      }
      aria-pressed={selected}
   >
      {label}
   </button>
);

export default EnrollmentCourseChart;
