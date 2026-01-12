import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm, usePage } from '@inertiajs/react';
import { ReactNode, useState } from 'react';
import { Pencil, Plus, Trash2 } from 'lucide-react';

interface ProfessionalType {
   id: number;
   name: string;
   is_active: boolean;
   sort_order: number;
}

interface Props extends SharedData {
   professionalTypes: ProfessionalType[];
}

const Index = (props: Props) => {
   const { professionalTypes } = props;
   const [editingId, setEditingId] = useState<number | null>(null);
   const [createDialogOpen, setCreateDialogOpen] = useState(false);

   const createForm = useForm({
      name: '',
      is_active: true,
      sort_order: professionalTypes.length > 0 ? Math.max(...professionalTypes.map((t) => t.sort_order)) + 1 : 0,
   });

   const editForm = useForm({
      name: '',
      is_active: true,
      sort_order: 0,
   });

   const handleCreate = (e: React.FormEvent) => {
      e.preventDefault();
      createForm.post(route('professional-types.store'), {
         onSuccess: () => {
            setCreateDialogOpen(false);
            createForm.reset();
         },
      });
   };

   const handleEdit = (type: ProfessionalType) => {
      setEditingId(type.id);
      editForm.setData({
         name: type.name,
         is_active: type.is_active,
         sort_order: type.sort_order,
      });
   };

   const handleUpdate = (e: React.FormEvent) => {
      e.preventDefault();
      if (editingId) {
         editForm.put(route('professional-types.update', editingId), {
            onSuccess: () => {
               setEditingId(null);
               editForm.reset();
            },
         });
      }
   };

   const handleDelete = (id: number) => {
      if (confirm('Are you sure you want to delete this professional type?')) {
         editForm.delete(route('professional-types.destroy', id), {
            onSuccess: () => {
               if (editingId === id) {
                  setEditingId(null);
               }
            },
         });
      }
   };

   return (
      <>
         <Head title="Professional Types" />
         <div className="space-y-6">
            <div className="flex items-center justify-between">
               <h1 className="text-2xl font-bold">Professional Types</h1>
               <Dialog open={createDialogOpen} onOpenChange={setCreateDialogOpen}>
                  <DialogTrigger asChild>
                     <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Professional Type
                     </Button>
                  </DialogTrigger>
                  <DialogContent>
                     <DialogHeader>
                        <DialogTitle>Create Professional Type</DialogTitle>
                     </DialogHeader>
                     <form onSubmit={handleCreate} className="space-y-4">
                        <div>
                           <Label htmlFor="name">Name</Label>
                           <Input
                              id="name"
                              type="text"
                              required
                              value={createForm.data.name}
                              onChange={(e) => createForm.setData('name', e.target.value)}
                              placeholder="e.g., Architect, Civil Engineer"
                           />
                           <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                           <Label htmlFor="sort_order">Sort Order</Label>
                           <Input
                              id="sort_order"
                              type="number"
                              min="0"
                              value={createForm.data.sort_order}
                              onChange={(e) => createForm.setData('sort_order', parseInt(e.target.value) || 0)}
                           />
                           <InputError message={createForm.errors.sort_order} />
                        </div>
                        <div>
                           <Label htmlFor="is_active">Status</Label>
                           <Select
                              value={createForm.data.is_active ? '1' : '0'}
                              onValueChange={(value) => createForm.setData('is_active', value === '1')}
                           >
                              <SelectTrigger>
                                 <SelectValue />
                              </SelectTrigger>
                              <SelectContent>
                                 <SelectItem value="1">Active</SelectItem>
                                 <SelectItem value="0">Inactive</SelectItem>
                              </SelectContent>
                           </Select>
                           <InputError message={createForm.errors.is_active} />
                        </div>
                        <div className="flex justify-end gap-2">
                           <Button type="button" variant="outline" onClick={() => setCreateDialogOpen(false)}>
                              Cancel
                           </Button>
                           <LoadingButton loading={createForm.processing}>Create</LoadingButton>
                        </div>
                     </form>
                  </DialogContent>
               </Dialog>
            </div>

            <Card>
               <Table>
                  <TableHeader>
                     <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Sort Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                     </TableRow>
                  </TableHeader>
                  <TableBody>
                     {professionalTypes.length === 0 ? (
                        <TableRow>
                           <TableCell colSpan={4} className="text-center text-muted-foreground">
                              No professional types found. Create one to get started.
                           </TableCell>
                        </TableRow>
                     ) : (
                        professionalTypes.map((type) => (
                           <TableRow key={type.id}>
                              {editingId === type.id ? (
                                 <>
                                    <TableCell>
                                       <Input
                                          value={editForm.data.name}
                                          onChange={(e) => editForm.setData('name', e.target.value)}
                                          className="h-8"
                                       />
                                       <InputError message={editForm.errors.name} />
                                    </TableCell>
                                    <TableCell>
                                       <Input
                                          type="number"
                                          min="0"
                                          value={editForm.data.sort_order}
                                          onChange={(e) => editForm.setData('sort_order', parseInt(e.target.value) || 0)}
                                          className="h-8 w-20"
                                       />
                                       <InputError message={editForm.errors.sort_order} />
                                    </TableCell>
                                    <TableCell>
                                       <Select
                                          value={editForm.data.is_active ? '1' : '0'}
                                          onValueChange={(value) => editForm.setData('is_active', value === '1')}
                                       >
                                          <SelectTrigger className="h-8">
                                             <SelectValue />
                                          </SelectTrigger>
                                          <SelectContent>
                                             <SelectItem value="1">Active</SelectItem>
                                             <SelectItem value="0">Inactive</SelectItem>
                                          </SelectContent>
                                       </Select>
                                       <InputError message={editForm.errors.is_active} />
                                    </TableCell>
                                    <TableCell className="text-right">
                                       <div className="flex justify-end gap-2">
                                          <Button
                                             type="button"
                                             size="sm"
                                             onClick={handleUpdate}
                                             disabled={editForm.processing}
                                          >
                                             Save
                                          </Button>
                                          <Button
                                             type="button"
                                             size="sm"
                                             variant="outline"
                                             onClick={() => {
                                                setEditingId(null);
                                                editForm.reset();
                                             }}
                                          >
                                             Cancel
                                          </Button>
                                       </div>
                                    </TableCell>
                                 </>
                              ) : (
                                 <>
                                    <TableCell className="font-medium">{type.name}</TableCell>
                                    <TableCell>{type.sort_order}</TableCell>
                                    <TableCell>
                                       <span
                                          className={`inline-flex items-center rounded-full px-2 py-1 text-xs font-medium ${
                                             type.is_active
                                                ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200'
                                          }`}
                                       >
                                          {type.is_active ? 'Active' : 'Inactive'}
                                       </span>
                                    </TableCell>
                                    <TableCell className="text-right">
                                       <div className="flex justify-end gap-2">
                                          <Button
                                             type="button"
                                             size="sm"
                                             variant="outline"
                                             onClick={() => handleEdit(type)}
                                          >
                                             <Pencil className="h-4 w-4" />
                                          </Button>
                                          <Button
                                             type="button"
                                             size="sm"
                                             variant="outline"
                                             onClick={() => handleDelete(type.id)}
                                          >
                                             <Trash2 className="h-4 w-4 text-destructive" />
                                          </Button>
                                       </div>
                                    </TableCell>
                                 </>
                              )}
                           </TableRow>
                        ))
                     )}
                  </TableBody>
               </Table>
            </Card>
         </div>
      </>
   );
};

Index.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default Index;

