import InputError from '@/components/input-error';
import { textOn } from '@/components/student-tag-pills';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { ReactNode, useState } from 'react';

interface StudentLabel {
   id: number;
   name: string;
   color: string;
}

interface Props extends SharedData {
   labels: StudentLabel[];
}

const SWATCHES = ['#16a34a', '#7c3aed', '#dc2626', '#ea580c', '#2563eb', '#ca8a04', '#db2777', '#475569'];

const ColorField = ({
   value,
   onChange,
}: {
   value: string;
   onChange: (color: string) => void;
}) => (
   <div className="space-y-2">
      <div className="flex flex-wrap gap-2">
         {SWATCHES.map((color) => (
            <button
               key={color}
               type="button"
               aria-label={color}
               className="h-7 w-7 rounded-full border-2"
               style={{ backgroundColor: color, borderColor: value === color ? '#0f172a' : 'transparent' }}
               onClick={() => onChange(color)}
            />
         ))}
         <input
            type="color"
            aria-label="Custom color"
            value={value || '#2563eb'}
            onChange={(event) => onChange(event.target.value)}
            className="h-7 w-10 cursor-pointer rounded border bg-transparent"
         />
      </div>
   </div>
);

const StudentLabels = ({ labels }: Props) => {
   const [createOpen, setCreateOpen] = useState(false);
   const [editing, setEditing] = useState<StudentLabel | null>(null);
   const createForm = useForm({ name: '', color: '#2563eb' });
   const editForm = useForm({ name: '', color: '#2563eb' });

   const createLabel = (event: React.FormEvent) => {
      event.preventDefault();
      createForm.post(route('student-labels.store'), {
         preserveScroll: true,
         onSuccess: () => {
            setCreateOpen(false);
            createForm.reset();
            createForm.setData('color', '#2563eb');
         },
      });
   };

   const openEdit = (label: StudentLabel) => {
      setEditing(label);
      editForm.setData({ name: label.name, color: label.color });
      editForm.clearErrors();
   };

   const updateLabel = (event: React.FormEvent) => {
      event.preventDefault();

      if (!editing) {
         return;
      }

      editForm.put(route('student-labels.update', editing.id), {
         preserveScroll: true,
         onSuccess: () => setEditing(null),
      });
   };

   const deleteLabel = (label: StudentLabel) => {
      if (!confirm(`Delete the ${label.name} label? Students who have it will lose that pill. Courses and PEF access stay as they are.`)) {
         return;
      }

      editForm.delete(route('student-labels.destroy', label.id), { preserveScroll: true });
   };

   return (
      <>
         <Head title="Student labels" />
         <div className="space-y-6">
            <div className="flex items-center justify-between gap-4">
               <div>
                  <h1 className="text-2xl font-bold">Student labels</h1>
                  <p className="text-muted-foreground mt-1 text-sm">
                     Labels are colored names only. Adding or deleting one does not enroll a student or change PEF access.
                  </p>
               </div>
               <Dialog open={createOpen} onOpenChange={setCreateOpen}>
                  <DialogTrigger asChild>
                     <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add label
                     </Button>
                  </DialogTrigger>
                  <DialogContent>
                     <DialogHeader>
                        <DialogTitle>Add label</DialogTitle>
                     </DialogHeader>
                     <form onSubmit={createLabel} className="space-y-4">
                        <div>
                           <Label htmlFor="label-name">Name</Label>
                           <Input
                              id="label-name"
                              required
                              maxLength={40}
                              value={createForm.data.name}
                              onChange={(event) => createForm.setData('name', event.target.value)}
                              placeholder="EVT, Scholars, At Risk"
                           />
                           <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                           <Label>Color</Label>
                           <ColorField value={createForm.data.color} onChange={(color) => createForm.setData('color', color)} />
                           <InputError message={createForm.errors.color} />
                        </div>
                        <LoadingButton loading={createForm.processing}>Save label</LoadingButton>
                     </form>
                  </DialogContent>
               </Dialog>
            </div>

            <Card>
               <Table>
                  <TableHeader>
                     <TableRow>
                        <TableHead>Label</TableHead>
                        <TableHead className="text-end">Action</TableHead>
                     </TableRow>
                  </TableHeader>
                  <TableBody>
                     {labels.length === 0 ? (
                        <TableRow>
                           <TableCell colSpan={2} className="text-muted-foreground">
                              No labels yet.
                           </TableCell>
                        </TableRow>
                     ) : (
                        labels.map((label) => (
                           <TableRow key={label.id}>
                              <TableCell>
                                 <span
                                    className="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    style={{ backgroundColor: label.color, color: textOn(label.color) }}
                                 >
                                    {label.name}
                                 </span>
                              </TableCell>
                              <TableCell className="text-end">
                                 <Button size="icon" variant="secondary" className="mr-2 h-8 w-8" onClick={() => openEdit(label)}>
                                    <Pencil className="h-4 w-4" />
                                 </Button>
                                 <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => deleteLabel(label)}>
                                    <Trash2 className="text-destructive h-4 w-4" />
                                 </Button>
                              </TableCell>
                           </TableRow>
                        ))
                     )}
                  </TableBody>
               </Table>
            </Card>

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
               <DialogContent>
                  <DialogHeader>
                     <DialogTitle>Edit label</DialogTitle>
                  </DialogHeader>
                  <form onSubmit={updateLabel} className="space-y-4">
                     <div>
                        <Label htmlFor="edit-label-name">Name</Label>
                        <Input
                           id="edit-label-name"
                           required
                           maxLength={40}
                           value={editForm.data.name}
                           onChange={(event) => editForm.setData('name', event.target.value)}
                        />
                        <InputError message={editForm.errors.name} />
                     </div>
                     <div>
                        <Label>Color</Label>
                        <ColorField value={editForm.data.color} onChange={(color) => editForm.setData('color', color)} />
                        <InputError message={editForm.errors.color} />
                     </div>
                     <LoadingButton loading={editForm.processing}>Save changes</LoadingButton>
                  </form>
               </DialogContent>
            </Dialog>
         </div>
      </>
   );
};

StudentLabels.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default StudentLabels;
