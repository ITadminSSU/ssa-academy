import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { CourseUpdateProps } from '../../update';
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
   plan?: UsExperiencePlan;
   handler: React.ReactNode;
}

const UsExperiencePlanForm = ({ plan, handler }: Props) => {
   const [open, setOpen] = useState(false);
   const { course } = usePage<CourseUpdateProps>().props;
   const { data, setData, post, put, processing, errors, reset, transform } = useForm({
      group_name: plan?.group_name || '',
      group_description: plan?.group_description || '',
      title: plan?.title || '',
      pass_mark: plan?.pass_mark ?? 85,
      max_attempts: plan?.max_attempts ?? 10,
      published: plan?.published ?? false,
   });

   transform((form) => ({
      ...form,
      published: form.published ? 1 : 0,
   }));

   const submit = (event: React.FormEvent) => {
      event.preventDefault();

      if (plan) {
         put(route('courses.us-experience.update', { course: course.id, plan: plan.id }), {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
         });
         return;
      }

      post(route('courses.us-experience.store', { course: course.id }), {
         preserveScroll: true,
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   return (
      <Dialog open={open} onOpenChange={setOpen}>
         <DialogTrigger asChild>{handler}</DialogTrigger>
         <DialogContent className="sm:max-w-lg">
            <DialogHeader>
               <DialogTitle>{plan ? 'Edit plan' : 'Add plan'}</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
               <div className="space-y-2">
                  <Label htmlFor="group_name">Accordion group</Label>
                  <Input
                     id="group_name"
                     value={data.group_name}
                     onChange={(event) => setData('group_name', event.target.value)}
                     placeholder="Skills Building"
                     required
                  />
                  <p className="text-muted-foreground text-xs">Plans with the same group name appear together. Groups are labels only — unlock is one line for the course.</p>
                  <InputError message={errors.group_name} />
               </div>
               <div className="space-y-2">
                  <Label htmlFor="group_description">Group description</Label>
                  <Textarea
                     id="group_description"
                     value={data.group_description}
                     onChange={(event) => setData('group_description', event.target.value)}
                     rows={2}
                  />
                  <InputError message={errors.group_description} />
               </div>
               <div className="space-y-2">
                  <Label htmlFor="title">Plan title</Label>
                  <Input id="title" value={data.title} onChange={(event) => setData('title', event.target.value)} required />
                  <InputError message={errors.title} />
               </div>
               <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-2">
                     <Label htmlFor="pass_mark">Pass mark %</Label>
                     <Input
                        id="pass_mark"
                        type="number"
                        min={1}
                        max={100}
                        value={data.pass_mark}
                        onChange={(event) => setData('pass_mark', Number(event.target.value))}
                     />
                     <InputError message={errors.pass_mark} />
                  </div>
                  <div className="space-y-2">
                     <Label htmlFor="max_attempts">Max attempts</Label>
                     <Input
                        id="max_attempts"
                        type="number"
                        min={1}
                        max={100}
                        value={data.max_attempts}
                        onChange={(event) => setData('max_attempts', Number(event.target.value))}
                     />
                     <InputError message={errors.max_attempts} />
                  </div>
               </div>
               <div className="flex items-center justify-between rounded-md border p-3">
                  <div>
                     <Label htmlFor="published">Published</Label>
                     <p className="text-muted-foreground text-xs">Students only see published plans that have drawings, a blank template, and an answer key.</p>
                  </div>
                  <Switch id="published" checked={data.published} onCheckedChange={(checked) => setData('published', checked)} />
               </div>
               <DialogFooter>
                  <Button type="submit" disabled={processing}>
                     {plan ? 'Save plan' : 'Create plan'}
                  </Button>
               </DialogFooter>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default UsExperiencePlanForm;
