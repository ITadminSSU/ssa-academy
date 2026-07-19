import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import DashboardLayout from '@/layouts/dashboard/layout';
import { RESOURCE_TYPES } from '@/pages/student/tabs-content/resources';
import { SharedData } from '@/types/global';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

interface Props extends SharedData {
   resources: LearnerResource[];
}

const typeLabel = (key: string) => RESOURCE_TYPES.find((type) => type.key === key)?.label ?? key;

const ResourceForm = ({ resource, onDone }: { resource?: LearnerResource; onDone: () => void }) => {
   const { data, setData, post, errors, processing } = useForm<{
      type: string;
      title: string;
      description: string;
      link: string;
      file: File | null;
   }>({
      type: resource?.type ?? RESOURCE_TYPES[0].key,
      title: resource?.title ?? '',
      description: resource?.description ?? '',
      link: resource?.link ?? '',
      file: null,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      const url = resource ? route('learner-resources.update', resource.id) : route('learner-resources.store');
      post(url, { forceFormData: true, onSuccess: onDone });
   };

   return (
      <form onSubmit={handleSubmit} className="space-y-4">
         <div>
            <Label>Type</Label>
            <Select value={data.type} onValueChange={(value) => setData('type', value)}>
               <SelectTrigger>
                  <SelectValue />
               </SelectTrigger>
               <SelectContent>
                  {RESOURCE_TYPES.map((type) => (
                     <SelectItem key={type.key} value={type.key}>
                        {type.label}
                     </SelectItem>
                  ))}
               </SelectContent>
            </Select>
            <InputError message={errors.type} />
         </div>

         <div>
            <Label>Title</Label>
            <Input value={data.title} onChange={(e) => setData('title', e.target.value)} />
            <InputError message={errors.title} />
         </div>

         <div>
            <Label>Description</Label>
            <Textarea rows={3} value={data.description} onChange={(e) => setData('description', e.target.value)} />
            <InputError message={errors.description} />
         </div>

         <div>
            <Label>External Link (optional)</Label>
            <Input value={data.link} onChange={(e) => setData('link', e.target.value)} placeholder="https://..." />
            <InputError message={errors.link} />
         </div>

         <div>
            <Label>File {resource?.file_name && <span className="text-muted-foreground text-xs">(current: {resource.file_name})</span>}</Label>
            <Input type="file" onChange={(e) => setData('file', e.target.files?.[0] ?? null)} />
            <InputError message={errors.file} />
         </div>

         <DialogFooter>
            <Button type="submit" disabled={processing}>
               {processing ? 'Saving...' : 'Save'}
            </Button>
         </DialogFooter>
      </form>
   );
};

const Index = ({ resources }: Props) => {
   const [dialog, setDialog] = useState(false);
   const [editing, setEditing] = useState<LearnerResource | undefined>(undefined);

   const openCreate = () => {
      setEditing(undefined);
      setDialog(true);
   };

   const openEdit = (resource: LearnerResource) => {
      setEditing(resource);
      setDialog(true);
   };

   return (
      <>
         <Head title="Resources" />

         <div className="container mx-auto space-y-6 px-4 py-6">
            <div className="flex items-center justify-between">
               <div>
                  <h1 className="text-2xl font-bold">Resources</h1>
                  <p className="text-muted-foreground mt-1 text-sm">Manage downloadable resources for learners.</p>
               </div>
               <Button onClick={openCreate}>New Resource</Button>
            </div>

            <Card>
               <CardContent className="p-0">
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>Title</TableHead>
                           <TableHead>Type</TableHead>
                           <TableHead>File / Link</TableHead>
                           <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {resources.map((resource) => (
                           <TableRow key={resource.id}>
                              <TableCell className="font-medium">{resource.title}</TableCell>
                              <TableCell>{typeLabel(resource.type)}</TableCell>
                              <TableCell>
                                 {resource.file ? (
                                    <a href={resource.file} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                       {resource.file_name ?? 'File'}
                                    </a>
                                 ) : resource.link ? (
                                    <a href={resource.link} target="_blank" rel="noopener noreferrer" className="text-primary text-sm hover:underline">
                                       Link
                                    </a>
                                 ) : (
                                    '—'
                                 )}
                              </TableCell>
                              <TableCell className="space-x-2 text-right">
                                 <Button size="sm" variant="outline" onClick={() => openEdit(resource)}>
                                    Edit
                                 </Button>
                                 <Button size="sm" variant="destructive" onClick={() => router.delete(route('learner-resources.destroy', resource.id))}>
                                    Delete
                                 </Button>
                              </TableCell>
                           </TableRow>
                        ))}
                        {resources.length === 0 && (
                           <TableRow>
                              <TableCell colSpan={4} className="text-muted-foreground py-8 text-center text-sm">
                                 No resources yet.
                              </TableCell>
                           </TableRow>
                        )}
                     </TableBody>
                  </Table>
               </CardContent>
            </Card>
         </div>

         <Dialog open={dialog} onOpenChange={setDialog}>
            <DialogContent>
               <DialogHeader>
                  <DialogTitle>{editing ? 'Edit Resource' : 'New Resource'}</DialogTitle>
               </DialogHeader>
               <ResourceForm key={editing?.id ?? 'new'} resource={editing} onDone={() => setDialog(false)} />
            </DialogContent>
         </Dialog>
      </>
   );
};

Index.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Index;
