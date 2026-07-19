import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { DialogClose } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';

interface Props {
   isGraded: boolean;
   totalMarks: number;
   submission: LessonActivitySubmission;
}

const ActivityGradeForm = ({ isGraded, totalMarks, submission }: Props) => {
   const { data, setData, put, processing, errors } = useForm({
      marks_obtained: submission.marks_obtained ?? '',
      instructor_feedback: submission.instructor_feedback ?? '',
      status: submission.status ?? 'pending',
   });

   const handleGradeSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      put(route('lesson-activity.submission.update', submission.id));
   };

   return (
      <form onSubmit={handleGradeSubmit} className="space-y-4 rounded-lg border p-4">
         <h3 className="flex items-center gap-2 font-semibold">
            <CheckCircle className="text-primary h-5 w-5" />
            {isGraded ? 'Update grade' : 'Grade activity'}
         </h3>

         <div className="grid grid-cols-2 gap-4">
            <div className="space-y-2">
               <Label htmlFor="marks">
                  Marks obtained *
                  <span className="text-muted-foreground ml-1 text-xs">(Max: {totalMarks})</span>
               </Label>
               <Input
                  id="marks"
                  type="number"
                  min="0"
                  max={totalMarks}
                  step="0.01"
                  value={data.marks_obtained}
                  onChange={(e) => setData('marks_obtained', e.target.value)}
                  required
               />
               <InputError message={errors.marks_obtained} />
            </div>

            <div className="space-y-2">
               <Label htmlFor="status">Status</Label>
               <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                  <SelectTrigger>
                     <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                     <SelectItem value="pending">Pending</SelectItem>
                     <SelectItem value="graded">Graded</SelectItem>
                     <SelectItem value="passed">Passed</SelectItem>
                     <SelectItem value="approved">Approved</SelectItem>
                     <SelectItem value="resubmitted">Resubmitted</SelectItem>
                  </SelectContent>
               </Select>
               <InputError message={errors.status} />
            </div>
         </div>

         <div className="space-y-2">
            <Label htmlFor="feedback">Trainer feedback (optional)</Label>
            <Textarea
               id="feedback"
               placeholder="Provide feedback to the learner..."
               value={data.instructor_feedback || ''}
               onChange={(e) => setData('instructor_feedback', e.target.value)}
               rows={4}
            />
            <InputError message={errors.instructor_feedback} />
         </div>

         <div className="flex justify-end gap-3 pt-4">
            <DialogClose asChild>
               <Button variant="outline">Cancel</Button>
            </DialogClose>
            <LoadingButton type="submit" loading={processing} disabled={processing || !data.marks_obtained}>
               {isGraded ? 'Update grade' : 'Submit grade'}
            </LoadingButton>
         </div>
      </form>
   );
};

export default ActivityGradeForm;
