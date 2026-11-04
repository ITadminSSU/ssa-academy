import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { Copy } from 'lucide-react';
import { FormEvent, useState } from 'react';

const duplicateTitle = (title: string) => `Copy of ${title}`.slice(0, 255);

interface Props {
   courseId: number | string;
   title: string;
   variant?: 'icon' | 'button';
   className?: string;
}

const DuplicateCourseButton = ({ courseId, title, variant = 'button', className }: Props) => {
   const [open, setOpen] = useState(false);
   const { data, setData, post, processing, errors, reset } = useForm({
      title: duplicateTitle(title),
   });

   const submit = (event: FormEvent) => {
      event.preventDefault();

      post(route('courses.duplicate', courseId), {
         preserveScroll: true,
         onSuccess: () => {
            reset();
            setOpen(false);
         },
      });
   };

   return (
      <Dialog
         open={open}
         onOpenChange={(next) => {
            setOpen(next);
            if (next) {
               setData('title', duplicateTitle(title));
            }
         }}
      >
         <DialogTrigger asChild>
            {variant === 'icon' ? (
               <Button size="icon" variant="secondary" className={cn('h-8 w-8', className)} title="Duplicate">
                  <Copy className="h-4 w-4" />
               </Button>
            ) : (
               <Button variant="outline" className={className}>
                  <Copy className="h-4 w-4" />
                  Duplicate
               </Button>
            )}
         </DialogTrigger>
         <DialogContent>
            <DialogHeader>
               <DialogTitle>Duplicate course</DialogTitle>
            </DialogHeader>
            <form onSubmit={submit} className="space-y-4">
               <p className="text-muted-foreground text-sm">
                  This creates a draft copy with its own lessons, quizzes, and videos. Students, payments, and Stripe prices stay on the original course.
               </p>
               <div>
                  <Label htmlFor={`duplicate-title-${courseId}`}>New title</Label>
                  <Input
                     id={`duplicate-title-${courseId}`}
                     value={data.title}
                     maxLength={255}
                     onChange={(event) => setData('title', event.target.value)}
                     required
                  />
                  <InputError message={errors.title} />
               </div>
               <LoadingButton loading={processing} className="w-full">
                  Create draft copy
               </LoadingButton>
            </form>
         </DialogContent>
      </Dialog>
   );
};

export default DuplicateCourseButton;
