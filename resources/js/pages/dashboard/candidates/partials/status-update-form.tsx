import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { CandidateStatus } from './table-columns';

interface StatusOption {
   value: string;
   label: string;
}

interface Props {
   candidate: User;
   statuses: StatusOption[];
}

const StatusUpdateForm = ({ candidate, statuses }: Props) => {
   const { translate } = usePage<SharedData>().props;
   const { dashboard, button, input } = translate;

   const { data, setData, put, processing, errors } = useForm({
      candidate_status: (candidate.candidate_status ?? 'new') as CandidateStatus,
      candidate_notes: candidate.candidate_notes ?? '',
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      put(route('candidates.status.update', { id: candidate.id }));
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div>
            <Label>{dashboard.candidate_status ?? 'Pipeline Status'} *</Label>
            <Select value={data.candidate_status} onValueChange={(value) => setData('candidate_status', value as CandidateStatus)}>
               <SelectTrigger>
                  <SelectValue placeholder="Select status" />
               </SelectTrigger>
               <SelectContent>
                  {statuses.map((status) => (
                     <SelectItem key={status.value} value={status.value}>
                        {status.label}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>
            <InputError message={errors.candidate_status} />
         </div>

         <div>
            <Label>{dashboard.candidate_notes ?? 'Admin Notes'}</Label>
            <Textarea
               rows={4}
               value={data.candidate_notes}
               onChange={(e) => setData('candidate_notes', e.target.value)}
               placeholder={input.description_placeholder ?? 'Notes for internal tracking (no auto-refund)'}
            />
            <InputError message={errors.candidate_notes} />
         </div>

         <LoadingButton loading={processing}>{button.save_changes ?? 'Save Changes'}</LoadingButton>
      </form>
   );
};

export default StatusUpdateForm;
