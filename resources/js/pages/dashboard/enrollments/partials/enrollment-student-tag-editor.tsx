import StudentTagPills, { StudentLabelPill, textOn } from '@/components/student-tag-pills';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { router, usePage } from '@inertiajs/react';
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

const EnrollmentStudentTagEditor = ({ userId, user, labels }: Props) => {
   const savedKey = (user.student_labels ?? []).map((label) => label.id).join(',');
   const [selected, setSelected] = useState<number[]>(savedKey === '' ? [] : savedKey.split(',').map(Number));
   const [saved, setSaved] = useState(false);
   const [processing, setProcessing] = useState(false);
   const errors = usePage().props.errors as { student_label_ids?: string } | undefined;

   useEffect(() => {
      setSelected(savedKey === '' ? [] : savedKey.split(',').map(Number));
   }, [savedKey]);

   const toggle = (id: number, checked: boolean) => {
      setSaved(false);
      setSelected((current) => (checked ? [...current, id] : current.filter((item) => item !== id)));
   };

   const save = () => {
      setProcessing(true);
      router.put(
         route('enrollments.student-labels.sync', userId),
         { student_label_ids: selected },
         {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setSaved(true),
            onFinish: () => setProcessing(false),
         },
      );
   };

   const selectedNames = labels.filter((label) => selected.includes(label.id)).map((label) => label.name);

   return (
      <div className="flex max-w-[16rem] flex-col items-start gap-2">
         <StudentTagPills user={user} />
         <Popover>
            <PopoverTrigger asChild>
               <Button type="button" variant="outline" size="sm" className="h-8 max-w-full justify-start truncate">
                  {selectedNames.length > 0 ? selectedNames.join(', ') : 'Choose tags'}
               </Button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-56 p-2">
               {labels.length === 0 ? (
                  <p className="text-muted-foreground px-1 py-1 text-sm">No labels yet.</p>
               ) : (
                  <div className="space-y-2">
                     {labels.map((label) => (
                        <label key={label.id} className="flex items-center gap-2 text-sm">
                           <Checkbox checked={selected.includes(label.id)} onCheckedChange={(value) => toggle(label.id, value === true)} />
                           <span
                              className="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                              style={{ backgroundColor: label.color, color: textOn(label.color) }}
                           >
                              {label.name}
                           </span>
                        </label>
                     ))}
                  </div>
               )}
            </PopoverContent>
         </Popover>
         <div className="flex items-center gap-2">
            <Button type="button" size="sm" onClick={save} disabled={processing}>
               Save
            </Button>
            {saved ? <span className="text-xs font-medium text-green-600">Saved</span> : null}
         </div>
         {errors?.student_label_ids ? <p className="text-xs text-red-500">{errors.student_label_ids}</p> : null}
      </div>
   );
};

export default EnrollmentStudentTagEditor;
