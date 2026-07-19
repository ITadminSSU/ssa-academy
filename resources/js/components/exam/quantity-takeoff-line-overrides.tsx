import LoadingButton from '@/components/loading-button';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { QuantityTakeoffBreakdownLine } from '@/components/exam/quantity-takeoff-breakdown';
import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface Props {
   attemptId: number;
   breakdown: QuantityTakeoffBreakdownLine[];
}

const QuantityTakeoffLineOverrides = ({ attemptId, breakdown }: Props) => {
   const initialOverrides = useMemo(() => {
      const overrides: Record<string, boolean> = {};

      breakdown.forEach((line) => {
         if (line.manual_override !== null && line.manual_override !== undefined) {
            overrides[line.key] = line.manual_override;
         }
      });

      return overrides;
   }, [breakdown]);

   const [lineOverrides, setLineOverrides] = useState<Record<string, boolean>>(initialOverrides);
   const [submitting, setSubmitting] = useState(false);

   const handleToggle = (key: string, checked: boolean) => {
      setLineOverrides((prev) => ({
         ...prev,
         [key]: checked,
      }));
   };

   const handleReset = (key: string) => {
      setLineOverrides((prev) => {
         const next = { ...prev };
         delete next[key];
         return next;
      });
   };

   const handleSave = () => {
      setSubmitting(true);
      router.post(
         route('exam-attempts.takeoff-overrides', attemptId),
         { line_overrides: lineOverrides },
         {
            preserveScroll: true,
            onFinish: () => setSubmitting(false),
         },
      );
   };

   const hasChanges = JSON.stringify(lineOverrides) !== JSON.stringify(initialOverrides);

   return (
      <div className="mt-6 space-y-4 rounded-lg border border-amber-500/30 bg-amber-500/5 p-4">
         <div>
            <p className="font-semibold">Trainer line overrides</p>
            <p className="text-sm text-muted-foreground">
               Toggle individual lines correct or incorrect. Overrides recalculate the attempt score and pass/fail result.
            </p>
         </div>

         <div className="space-y-3">
            {breakdown.map((line) => {
               const hasOverride = Object.prototype.hasOwnProperty.call(lineOverrides, line.key);
               const autoCorrect = line.auto_within_tolerance ?? line.within_tolerance;

               return (
                  <div key={line.key} className="flex flex-col gap-3 rounded-md border bg-background p-3 sm:flex-row sm:items-center sm:justify-between">
                     <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium">{line.item}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                           Auto grade: {autoCorrect ? 'Correct' : 'Incorrect'} · Expected {line.expected_qty} · Submitted{' '}
                           {line.submitted_qty ?? '—'}
                        </p>
                     </div>

                     <div className="flex flex-wrap items-center gap-3">
                        {hasOverride && (
                           <Badge variant="outline" className="border-amber-500 text-amber-700">
                              Overridden
                           </Badge>
                        )}
                        <div className="flex items-center gap-2">
                           <Label htmlFor={`override-${line.key}`} className="text-sm">
                              Mark correct
                           </Label>
                           <Switch
                              id={`override-${line.key}`}
                              checked={hasOverride ? lineOverrides[line.key] : (line.is_correct ?? line.within_tolerance)}
                              onCheckedChange={(checked) => handleToggle(line.key, checked)}
                           />
                        </div>
                        {hasOverride && (
                           <Button type="button" variant="ghost" size="sm" onClick={() => handleReset(line.key)}>
                              Reset
                           </Button>
                        )}
                     </div>
                  </div>
               );
            })}
         </div>

         <div className="flex justify-end">
            <LoadingButton type="button" loading={submitting} disabled={!hasChanges} onClick={handleSave}>
               Save line overrides
            </LoadingButton>
         </div>
      </div>
   );
};

export default QuantityTakeoffLineOverrides;
