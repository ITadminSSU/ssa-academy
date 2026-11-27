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

   const lastLabel = selectedLabels[selectedLabels.length - 1];

   return (
      <div className="inline-flex flex-col items-start gap-1">
         <Popover>
            <PopoverTrigger asChild>
               {selectedLabels.length === 0 ? (
                  <button
                     type="button"
                     disabled={processing}
                     className="border-input bg-background text-muted-foreground inline-flex h-8 items-center gap-1.5 rounded-full border px-4 text-sm font-normal whitespace-nowrap disabled:opacity-50"
                  >
                     Choose tags
                     <ChevronDown className="h-3.5 w-3.5 shrink-0" />
                  </button>
               ) : (
                  <button
                     type="button"
                     disabled={processing}
                     className="inline-flex flex-wrap items-center gap-1 border-0 bg-transparent p-0 text-left disabled:opacity-50"
                  >
                     {selectedLabels.map((label) => (
                        <span
                           key={label.id}
                           className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
                           style={{ backgroundColor: label.color, color: textOn(label.color) }}
                        >
                           {label.name}
                           {label.id === lastLabel.id ? <ChevronDown className="h-3 w-3 shrink-0" /> : null}
                        </span>
                     ))}
                  </button>
               )}
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
