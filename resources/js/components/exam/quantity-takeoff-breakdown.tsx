import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { Check, X } from 'lucide-react';

export interface QuantityTakeoffBreakdownLine {
   key: string;
   item: string;
   unit: string;
   expected_qty: number;
   submitted_qty?: number | null;
   within_tolerance: boolean;
   is_correct?: boolean;
   auto_within_tolerance?: boolean;
   manual_override?: boolean | null;
   tolerance?: number;
}

interface Props {
   breakdown: QuantityTakeoffBreakdownLine[];
   linesCorrect?: number;
   linesTotal?: number;
   showTolerance?: boolean;
   className?: string;
}

const formatQty = (value?: number | null) => {
   if (value === null || value === undefined || Number.isNaN(value)) {
      return '—';
   }

   return Number(value).toLocaleString(undefined, { maximumFractionDigits: 2 });
};

const QuantityTakeoffBreakdown = ({ breakdown, linesCorrect, linesTotal, showTolerance = true, className }: Props) => {
   const isLineCorrect = (line: QuantityTakeoffBreakdownLine) => line.is_correct ?? line.within_tolerance;
   const correct = linesCorrect ?? breakdown.filter((line) => isLineCorrect(line)).length;
   const total = linesTotal ?? breakdown.length;
   const linePercent = total > 0 ? ((correct / total) * 100).toFixed(1) : '0.0';

   return (
      <div className={cn('space-y-4', className)}>
         <div className="grid gap-3 sm:grid-cols-3">
            <div className="rounded-lg border bg-muted/40 p-4">
               <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Lines correct</p>
               <p className="mt-1 text-2xl font-bold text-green-600">
                  {correct} <span className="text-base font-medium text-muted-foreground">/ {total}</span>
               </p>
            </div>
            <div className="rounded-lg border bg-muted/40 p-4">
               <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Line accuracy</p>
               <p className="mt-1 text-2xl font-bold">{linePercent}%</p>
            </div>
            <div className="rounded-lg border bg-muted/40 p-4">
               <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Incorrect lines</p>
               <p className="mt-1 text-2xl font-bold text-red-600">{total - correct}</p>
            </div>
         </div>

         <div className="overflow-x-auto rounded-lg border">
            <table className="w-full border-collapse text-sm">
               <thead className="bg-muted/60">
                  <tr className="text-left">
                     <th className="p-3">#</th>
                     <th className="p-3">Item</th>
                     <th className="p-3">Expected</th>
                     <th className="p-3">Submitted</th>
                     <th className="p-3">Unit</th>
                     {showTolerance && <th className="p-3">± Tolerance</th>}
                     <th className="p-3">Result</th>
                  </tr>
               </thead>
               <tbody>
                  {breakdown.map((line, index) => {
                     const lineCorrect = isLineCorrect(line);

                     return (
                     <tr
                        key={line.key}
                        className={cn(
                           'border-t align-top',
                           lineCorrect ? 'bg-green-500/5' : 'bg-red-500/5',
                        )}
                     >
                        <td className="p-3 text-muted-foreground">{index + 1}</td>
                        <td className="max-w-md p-3 whitespace-normal">
                           <div className="space-y-1">
                              <span>{line.item}</span>
                              {line.manual_override !== null && line.manual_override !== undefined && (
                                 <Badge variant="outline" className="ml-0 border-amber-500 text-amber-700">
                                    Trainer override
                                 </Badge>
                              )}
                           </div>
                        </td>
                        <td className="p-3 font-medium">{formatQty(line.expected_qty)}</td>
                        <td className={cn('p-3 font-medium', !lineCorrect && 'text-red-600')}>
                           {formatQty(line.submitted_qty)}
                        </td>
                        <td className="p-3">
                           <Badge variant="outline">{line.unit || '—'}</Badge>
                        </td>
                        {showTolerance && <td className="p-3 text-muted-foreground">± {formatQty(line.tolerance)}</td>}
                        <td className="p-3">
                           {lineCorrect ? (
                              <span className="inline-flex items-center gap-1 font-medium text-green-600">
                                 <Check className="h-4 w-4" />
                                 Correct
                              </span>
                           ) : (
                              <span className="inline-flex items-center gap-1 font-medium text-red-600">
                                 <X className="h-4 w-4" />
                                 Incorrect
                              </span>
                           )}
                        </td>
                     </tr>
                     );
                  })}
               </tbody>
            </table>
         </div>
      </div>
   );
};

export default QuantityTakeoffBreakdown;
