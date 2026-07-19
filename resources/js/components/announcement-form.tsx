import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { router, useForm } from '@inertiajs/react';

interface AnnouncementFormProps {
   announcement?: Announcement;
   onDone: () => void;
}

const AnnouncementForm = ({ announcement, onDone }: AnnouncementFormProps) => {
   const { data, setData, post, put, errors, processing } = useForm({
      title: announcement?.title ?? '',
      body: announcement?.body ?? '',
      is_published: announcement?.is_published ?? true,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();

      const options = {
         preserveScroll: true,
         onSuccess: () => {
            onDone();
            router.reload({ only: ['announcements'] });
         },
      };

      if (announcement) {
         put(route('announcements.update', announcement.id), options);
      } else {
         post(route('announcements.store'), options);
      }
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div>
            <Label>Title</Label>
            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
            <InputError message={errors.title} />
         </div>

         <div>
            <Label>Message</Label>
            <Textarea rows={5} value={data.body} onChange={(e) => setData('body', e.target.value)} />
            <InputError message={errors.body} />
         </div>

         <div className="flex items-center gap-3">
            <Switch checked={data.is_published} onCheckedChange={(value) => setData('is_published', value)} />
            <Label>Published</Label>
         </div>

         <DialogFooter>
            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : announcement ? 'Save changes' : 'Publish announcement'}
            </Button>
         </DialogFooter>
      </form>
   );
};

export default AnnouncementForm;
