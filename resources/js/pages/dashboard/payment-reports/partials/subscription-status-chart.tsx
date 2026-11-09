import { Card } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import { Bar, BarChart, CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export interface SubscriptionSummaryRow {
   label: string;
   count: number;
}

type GraphView = 'number' | 'bar' | 'line';

const SubscriptionStatusChart = ({ rows }: { rows: SubscriptionSummaryRow[] }) => {
   const [view, setView] = useState<GraphView>('bar');
   const total = rows.reduce((sum, row) => sum + row.count, 0);
   const chartData = rows.map((row) => ({
      name: row.label,
      subscriptions: row.count,
   }));

   return (
      <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
         <Card className="p-4 sm:p-6">
            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
               <div>
                  <h3 className="text-lg font-medium">Subscriptions by Stripe status</h3>
                  <p className="text-muted-foreground mt-1 text-sm">
                     Each figure is a Stripe subscription. A student in two courses is counted twice. Totals follow the course filter.
                  </p>
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
               <p className="text-muted-foreground py-10 text-center text-sm">Stripe subscription totals are unavailable.</p>
            ) : null}

            {chartData.length > 0 && view === 'number' ? (
               <ul className="divide-y">
                  {chartData.map((row) => (
                     <li key={row.name} className="flex items-center justify-between gap-3 py-2 text-sm">
                        <span>{row.name}</span>
                        <span className="font-semibold tabular-nums">{row.subscriptions}</span>
                     </li>
                  ))}
               </ul>
            ) : null}

            {chartData.length > 0 && view === 'bar' ? (
               <div style={{ height: 280 }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <BarChart data={chartData} layout="vertical" margin={{ top: 0, right: 24, left: 8, bottom: 0 }}>
                        <CartesianGrid strokeDasharray="3 3" horizontal={false} />
                        <XAxis type="number" allowDecimals={false} tickLine={false} axisLine={false} />
                        <YAxis type="category" dataKey="name" width={90} tickLine={false} axisLine={false} />
                        <Tooltip formatter={(value) => [value, 'Subscriptions']} />
                        <Bar dataKey="subscriptions" fill="#1A344F" radius={[0, 6, 6, 0]} barSize={18} />
                     </BarChart>
                  </ResponsiveContainer>
               </div>
            ) : null}

            {chartData.length > 0 && view === 'line' ? (
               <div style={{ height: 280 }}>
                  <ResponsiveContainer width="100%" height="100%">
                     <LineChart data={chartData} margin={{ top: 8, right: 16, left: 0, bottom: 8 }}>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis dataKey="name" tickLine={false} axisLine={false} />
                        <YAxis allowDecimals={false} tickLine={false} axisLine={false} width={40} />
                        <Tooltip formatter={(value) => [value, 'Subscriptions']} />
                        <Line type="monotone" dataKey="subscriptions" stroke="#1A344F" strokeWidth={2} dot={{ r: 4, fill: '#1A344F' }} />
                     </LineChart>
                  </ResponsiveContainer>
               </div>
            ) : null}
         </Card>

         <Card className="h-fit overflow-hidden p-0">
            <div className="bg-[#1A344F] px-4 py-3 text-sm font-semibold text-white">Stripe subscription summary</div>
            {rows.length === 0 ? (
               <p className="text-muted-foreground px-4 py-6 text-sm">Stripe subscription totals are unavailable.</p>
            ) : (
               <ul>
                  {rows.map((row) => (
                     <li key={row.label} className="flex items-center justify-between gap-3 border-b px-4 py-3">
                        <span className="text-sm">{row.label}</span>
                        <span className="text-sm font-semibold tabular-nums">{row.count}</span>
                     </li>
                  ))}
                  <li className="flex items-center justify-between gap-3 px-4 py-3">
                     <span className="text-sm font-semibold">Total subscriptions</span>
                     <span className="text-sm font-semibold tabular-nums">{total}</span>
                  </li>
               </ul>
            )}
         </Card>
      </div>
   );
};

export default SubscriptionStatusChart;
