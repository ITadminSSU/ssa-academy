import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { getQueryParams } from '@/lib/route';
import { cn } from '@/lib/utils';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface CouponLabelOption {
   id: number;
   name: string;
   color: string;
}

export interface CouponReport {
   rows: Array<{ key: string; label: string; coupons: number }>;
   total_coupons: number;
   successful_redemptions: number;
   filtered: boolean;
}

export interface CouponReportFilter {
   statuses: string[];
   label_ids: number[];
   uncategorized: boolean;
}

const STATUS_COLORS: Record<string, string> = {
   active: '#1A344F',
   scheduled: '#64748b',
   used_up: '#d97706',
   expired: '#b91c1c',
   inactive: '#94a3b8',
};

const textOn = (hex: string) => {
   const normalized = hex.replace('#', '');

   if (normalized.length !== 6) {
      return '#ffffff';
   }

   const red = Number.parseInt(normalized.slice(0, 2), 16);
   const green = Number.parseInt(normalized.slice(2, 4), 16);
   const blue = Number.parseInt(normalized.slice(4, 6), 16);

   return (red * 299 + green * 587 + blue * 114) / 1000 >= 160 ? '#1e293b' : '#ffffff';
};

type GraphView = 'number' | 'bar' | 'line';

const CouponSummary = ({
   report,
   labels,
   reportFilter,
}: {
   report: CouponReport;
   labels: CouponLabelOption[];
   reportFilter: CouponReportFilter;
}) => {
   const page = usePage();
   const [view, setView] = useState<GraphView>('bar');
   const [statuses, setStatuses] = useState<string[]>(reportFilter.statuses);
   const [labelIds, setLabelIds] = useState<number[]>(reportFilter.label_ids);
   const [uncategorized, setUncategorized] = useState(reportFilter.uncategorized);
   const [tagSearch, setTagSearch] = useState('');

   useEffect(() => {
      setStatuses(reportFilter.statuses);
      setLabelIds(reportFilter.label_ids);
      setUncategorized(reportFilter.uncategorized);
   }, [reportFilter.statuses, reportFilter.label_ids, reportFilter.uncategorized]);

   const allSelected = statuses.length === 0 && labelIds.length === 0 && !uncategorized;
   const search = tagSearch.trim().toLowerCase();
   const show = (name: string) => name.toLowerCase().includes(search);

   const chartData = useMemo(
      () =>
         report.rows.map((row) => ({
            ...row,
            name: row.label,
            color: STATUS_COLORS[row.key] ?? '#1A344F',
         })),
      [report.rows],
   );

   const visit = (nextStatuses: string[], nextLabels: number[], nextUncategorized: boolean) => {
      const params: Record<string, string> = { ...getQueryParams(page.url) };
      delete params.report_statuses;
      delete params.report_labels;
      delete params.report_uncategorized;

      if (nextStatuses.length > 0) {
         params.report_statuses = nextStatuses.join(',');
      }

      if (nextLabels.length > 0) {
         params.report_labels = nextLabels.join(',');
      }

      if (nextUncategorized) {
         params.report_uncategorized = '1';
      }

      router.get(route('course-coupons.index'), params, {
         preserveScroll: true,
         preserveState: true,
         replace: true,
      });
   };

   const toggleStatus = (key: string) => {
      setStatuses((current) => (current.includes(key) ? current.filter((item) => item !== key) : [...current, key]));
   };

   const toggleLabel = (id: number) => {
      setLabelIds((current) => (current.includes(id) ? current.filter((item) => item !== id) : [...current, id]));
   };

   return (
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
         <Card className="p-4 sm:p-6">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
               <div>
                  <h2 className="text-lg font-semibold">Coupon Summary</h2>
                  <p className="text-muted-foreground mt-1 text-sm">Counts use every coupon you can see, not just this page.</p>
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
               <p className="text-sm font-medium">Filter the report</p>
               <div className="flex flex-col gap-2 sm:flex-row">
                  <Input value={tagSearch} onChange={(event) => setTagSearch(event.target.value)} placeholder="Search tags..." aria-label="Search tags" />
                  <div className="flex gap-2">
                     <Button type="button" onClick={() => visit(statuses, labelIds, uncategorized)}>
                        Apply Filters
                     </Button>
                     <Button
                        type="button"
                        variant="outline"
                        onClick={() => {
                           setStatuses([]);
                           setLabelIds([]);
                           setUncategorized(false);
                           setTagSearch('');
                           visit([], [], false);
                        }}
                     >
                        Clear
                     </Button>
                  </div>
               </div>
               <div className="flex flex-wrap gap-2">
                  {show('All') ? (
                     <Chip
                        label="All"
                        selected={allSelected}
                        onClick={() => {
                           setStatuses([]);
                           setLabelIds([]);
                           setUncategorized(false);
                        }}
                     />
                  ) : null}
                  {chartData
                     .filter((row) => show(row.label))
                     .map((row) => (
                        <Chip key={row.key} label={row.label} selected={statuses.includes(row.key)} onClick={() => toggleStatus(row.key)} />
                     ))}
                  {show('Uncategorized') ? (
                     <Chip label="Uncategorized" selected={uncategorized} onClick={() => setUncategorized((current) => !current)} />
                  ) : null}
                  {labels
                     .filter((label) => show(label.name))
                     .map((label) => (
                        <Chip
                           key={label.id}
                           label={label.name}
                           color={label.color}
                           selected={labelIds.includes(label.id)}
                           onClick={() => toggleLabel(label.id)}
                        />
                     ))}
               </div>
            </div>

            {view === 'number' ? (
               <ul className="divide-y">
                  {report.rows.map((row) => (
                     <li key={row.key} className="flex items-center justify-between py-2 text-sm">
                        <span>{row.label}</span>
                        <span className="font-semibold tabular-nums">{row.coupons}</span>
                     </li>
                  ))}
               </ul>
            ) : null}

            {view === 'bar' ? (
               <div style={{ height: 280 }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <BarChart data={chartData} layout="vertical" margin={{ top: 0, right: 24, left: 8, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                        <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                        <YAxis type="category" dataKey="name" width={110} tickLine={false} axisLine={false} />
                        <Tooltip formatter={(value) => [value, 'Coupons']} />
                        <Bar dataKey="coupons" radius={[0, 6, 6, 0]} barSize={18}>
                           {chartData.map((row) => (
                              <Cell key={row.key} fill={row.color} />
                           ))}
                        </Bar>
                     </BarChart>
                  </ResponsiveContainer>
               </div>
            ) : null}

            {view === 'line' ? (
               <div style={{ height: 280 }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <LineChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 8 }}>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="name" tickLine={false} axisLine={false} />
                        <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                        <Tooltip formatter={(value) => [value, 'Coupons']} />
                        <Line type="monotone" dataKey="coupons" stroke="#1A344F" strokeWidth={2} dot={{ r: 4, fill: '#1A344F' }} />
                     </LineChart>
                  </ResponsiveContainer>
               </div>
            ) : null}
         </Card>

         <Card className="h-fit overflow-hidden p-0">
            <div className="bg-[#1A344F] px-4 py-3 text-sm font-semibold text-white">Coupon Summary Details</div>
            <ul>
               <Detail label="Total coupons shown" value={report.total_coupons} />
               {report.rows.map((row) => (
                  <Detail key={row.key} label={row.label} value={row.coupons} />
               ))}
               <Detail label="Successful redemptions" value={report.successful_redemptions} />
            </ul>
         </Card>
      </div>
   );
};

const Detail = ({ label, value }: { label: string; value: number }) => (
   <li className="flex items-center justify-between gap-3 border-b px-4 py-3 last:border-b-0">
      <span className="text-sm">{label}</span>
      <span className="text-sm font-semibold tabular-nums">{value}</span>
   </li>
);

const Chip = ({
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
      aria-pressed={selected}
      className={cn(
         'rounded-full border px-2.5 py-1 text-xs font-semibold',
         !color && (selected ? 'border-[#1A344F] bg-[#1A344F] text-white' : 'bg-muted text-foreground'),
         !selected && 'opacity-45',
      )}
      style={color ? { backgroundColor: color, color: textOn(color), borderColor: selected ? '#0f172a' : 'transparent' } : undefined}
   >
      {label}
   </button>
);

export default CouponSummary;
