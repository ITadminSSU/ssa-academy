import { StudentLabelPill, textOn } from '@/components/student-tag-pills';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Props {
   userId: number;
   user: {
      role?: string;
      pef_tagged?: boolean;
      student_labels?: StudentLabelPill[];
   };
   labels: StudentLabelPill[];
}

const TagPill = ({ label }: { label: StudentLabelPill }) => (
   <span
      className="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
      style={{ backgroundColor: label.color, color: textOn(label.color) }}
   >
      {label.name}
   </span>
);

const EnrollmentStudentTagEditor = ({ userId, user, labels }: Props) => {
   const savedKey = (user.student_labels ?? []).map((label) => label.id).join(',');
   const [selected, setSelected] = useState<number[]>(savedKey === '' ? [] : savedKey.split(',').map(Number));
   const [processing, setProcessing] = useState(false);
   const errors = usePage().props.errors as { student_label_ids?: string } | undefined;

   useEffect(() => {
      setSelected(savedKey === '' ? [] : savedKey.split(',').map(Number));
   }, [savedKey]);

   const toggle = (label: StudentLabelPill, checked: boolean) => {
      if (processing) {
         return;
      }

      const confirmed = window.confirm(`Save this tag change for this student? ${label.name}`);

      if (!confirmed) {
         return;
      }

      const previous = selected;
      const next = checked
         ? selected.includes(label.id)
            ? selected
            : [...selected, label.id]
         : selected.filter((id) => id !== label.id);

      setProcessing(true);
      setSelected(next);
      router.put(
         route('enrollments.student-labels.sync', userId),
         { student_label_ids: next },
         {
            preserveScroll: true,
            preserveState: true,
            onError: () => setSelected(previous),
            onFinish: () => setProcessing(false),
         },
      );
   };

   const selectedLabels = labels.filter((label) => selected.includes(label.id));

   return (
      <div className="flex w-full max-w-[16rem] flex-col items-start gap-1">
         <Popover>
            <PopoverTrigger asChild>
               <button
                  type="button"
                  disabled={processing}
                  className="border-input bg-background flex min-h-10 w-full items-center justify-between gap-2 rounded-md border px-3 py-1.5 text-left text-sm font-normal disabled:opacity-50"
               >
                  <span className="flex min-w-0 flex-1 flex-wrap items-center gap-1">
                     {selectedLabels.length > 0 ? (
                        selectedLabels.map((label) => <TagPill key={label.id} label={label} />)
                     ) : (
                        <span className="text-muted-foreground">Choose tags</span>
                     )}
                  </span>
                  <ChevronDown className="text-muted-foreground h-4 w-4 shrink-0" />
               </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-56 p-2">
               {labels.length === 0 ? (
                  <p className="text-muted-foreground px-1 py-1 text-sm">No labels yet.</p>
               ) : (
                  <div className="space-y-2">
                     {labels.map((label) => (
                        <label key={label.id} className="flex items-center gap-2 text-sm">
                           <Checkbox
                              checked={selected.includes(label.id)}
                              disabled={processing}
                              onCheckedChange={(value) => toggle(label, value === true)}
                           />
                           <TagPill label={label} />
                        </label>
                     ))}
                  </div>
               )}
            </PopoverContent>
         </Popover>
         {errors?.student_label_ids ? <p className="text-xs text-red-500">{errors.student_label_ids}</p> : null}
      </div>
   );
};

export default EnrollmentStudentTagEditor;
