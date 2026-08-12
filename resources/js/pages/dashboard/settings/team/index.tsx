import DeleteModal from '@/components/inertia/delete-modal';
import ImageCropDialog from '@/components/image-crop-dialog';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useImageCrop } from '@/hooks/use-image-crop';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

interface TeamMember {
   id: number;
   name: string;
   role: string;
   photo: string | null;
   sort_order: number;
   is_active: boolean;
}

interface Props extends SharedData {
   teamMembers: TeamMember[];
}

type TeamMemberForm = {
   name: string;
   role: string;
   photo: File | null;
   sort_order: number;
   is_active: boolean;
};

const emptyForm = (members: TeamMember[]): TeamMemberForm => ({
   name: '',
   role: '',
   photo: null,
   sort_order: members.length > 0 ? Math.max(...members.map((member) => member.sort_order)) + 1 : 1,
   is_active: true,
});

const TeamSettings = ({ teamMembers }: Props) => {
   const [createOpen, setCreateOpen] = useState(false);
   const [editingMember, setEditingMember] = useState<TeamMember | null>(null);
   const [createPreview, setCreatePreview] = useState<string | null>(null);
   const [editPreview, setEditPreview] = useState<string | null>(null);

   const createForm = useForm<TeamMemberForm>(emptyForm(teamMembers));
   const editForm = useForm<TeamMemberForm>(emptyForm(teamMembers));

   const createCrop = useImageCrop({
      onPhotoReady: (file, previewUrl) => {
         createForm.setData('photo', file);
         setCreatePreview((current) => {
            if (current?.startsWith('blob:')) {
               URL.revokeObjectURL(current);
            }
            return previewUrl;
         });
      },
   });

   const editCrop = useImageCrop({
      onPhotoReady: (file, previewUrl) => {
         editForm.setData('photo', file);
         setEditPreview((current) => {
            if (current?.startsWith('blob:')) {
               URL.revokeObjectURL(current);
            }
            return previewUrl;
         });
      },
   });

   const resetCreatePhoto = () => {
      if (createPreview?.startsWith('blob:')) {
         URL.revokeObjectURL(createPreview);
      }
      setCreatePreview(null);
      createForm.setData('photo', null);
   };

   const resetEditPhoto = () => {
      if (editPreview?.startsWith('blob:')) {
         URL.revokeObjectURL(editPreview);
      }
      setEditPreview(null);
      editForm.setData('photo', null);
   };

   const openEdit = (member: TeamMember) => {
      resetEditPhoto();
      setEditingMember(member);
      editForm.setData({
         name: member.name,
         role: member.role,
         photo: null,
         sort_order: member.sort_order,
         is_active: member.is_active,
      });
      setEditPreview(member.photo);
   };

   const closeEdit = () => {
      resetEditPhoto();
      setEditingMember(null);
      editForm.reset();
   };

   const submitCreate = (event: FormEvent) => {
      event.preventDefault();

      if (!createForm.data.photo) {
         createForm.setError('photo', 'Please upload and crop a team photo.');
         return;
      }

      createForm.transform((data) => ({
         ...data,
         is_active: data.is_active ? 1 : 0,
      }));

      createForm.post(route('settings.team-members.store'), {
         forceFormData: true,
         onSuccess: () => {
            createForm.transform((data) => data);
            setCreateOpen(false);
            createForm.reset();
            resetCreatePhoto();
         },
      });
   };

   const submitEdit = (event: FormEvent) => {
      event.preventDefault();

      if (!editingMember) {
         return;
      }

      editForm.transform((data) => {
         const payload: Record<string, unknown> = {
            name: data.name,
            role: data.role,
            sort_order: data.sort_order,
            is_active: data.is_active ? 1 : 0,
         };

         if (data.photo) {
            payload.photo = data.photo;
         }

         return payload;
      });

      editForm.post(route('settings.team-members.update', editingMember.id), {
         forceFormData: true,
         onSuccess: () => {
            editForm.transform((data) => data);
            closeEdit();
         },
      });
   };

   const renderPhotoField = (
      form: typeof createForm,
      crop: typeof createCrop,
      preview: string | null,
      fallbackPhoto?: string | null,
   ) => (
      <div className="space-y-3">
         <Label>Photo</Label>
         <div className="flex items-center gap-4">
            <div className="bg-muted h-28 w-20 overflow-hidden rounded-lg border">
               {(preview || fallbackPhoto) && (
                  <img src={preview || fallbackPhoto || ''} alt="Team member preview" className="h-full w-full object-cover" />
               )}
            </div>
            <div>
               <input ref={crop.fileInputRef} type="file" accept="image/jpeg,image/png,image/jpg" className="hidden" onChange={crop.handleFileSelect} />
               <Button type="button" variant="outline" size="sm" onClick={() => crop.fileInputRef.current?.click()}>
                  <Upload className="mr-2 h-4 w-4" />
                  Upload & crop photo
               </Button>
               <p className="text-muted-foreground mt-2 text-xs">Portrait 3:4 crop. Saved as 480×640 JPG.</p>
            </div>
         </div>
         <InputError message={form.errors.photo} />
      </div>
   );

   return (
      <>
         <Head title="Our Team" />

         <div className="mx-auto space-y-6 md:px-3">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
               <div>
                  <h1 className="text-2xl font-bold">Our Team</h1>
                  <p className="text-muted-foreground text-sm">
                     Manage team members shown on the About Us page. Photos are cropped before upload.
                  </p>
               </div>

               <Dialog
                  open={createOpen}
                  onOpenChange={(open) => {
                     setCreateOpen(open);
                     if (!open) {
                        createForm.reset();
                        resetCreatePhoto();
                     }
                  }}
               >
                  <DialogTrigger asChild>
                     <Button>
                        <Plus className="mr-2 h-4 w-4" />
                        Add team member
                     </Button>
                  </DialogTrigger>
                  <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                     <DialogHeader>
                        <DialogTitle>Add team member</DialogTitle>
                     </DialogHeader>
                     <form onSubmit={submitCreate} className="space-y-4">
                        <div>
                           <Label htmlFor="create-name">Name</Label>
                           <Input id="create-name" value={createForm.data.name} onChange={(e) => createForm.setData('name', e.target.value)} required />
                           <InputError message={createForm.errors.name} />
                        </div>
                        <div>
                           <Label htmlFor="create-role">Role / title</Label>
                           <Input id="create-role" value={createForm.data.role} onChange={(e) => createForm.setData('role', e.target.value)} required />
                           <InputError message={createForm.errors.role} />
                        </div>
                        {renderPhotoField(createForm, createCrop, createPreview)}
                        <div>
                           <Label htmlFor="create-sort">Display order</Label>
                           <Input
                              id="create-sort"
                              type="number"
                              min={0}
                              value={createForm.data.sort_order}
                              onChange={(e) => createForm.setData('sort_order', Number(e.target.value))}
                           />
                        </div>
                        <div className="flex items-center justify-between rounded-lg border p-3">
                           <Label htmlFor="create-active">Visible on About page</Label>
                           <Switch id="create-active" checked={createForm.data.is_active} onCheckedChange={(checked) => createForm.setData('is_active', checked)} />
                        </div>
                        <LoadingButton loading={createForm.processing}>Save team member</LoadingButton>
                     </form>
                  </DialogContent>
               </Dialog>
            </div>

            <Card>
               <Table>
                  <TableHeader>
                     <TableRow>
                        <TableHead>Photo</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead>Order</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-right">Actions</TableHead>
                     </TableRow>
                  </TableHeader>
                  <TableBody>
                     {teamMembers.length === 0 ? (
                        <TableRow>
                           <TableCell colSpan={6} className="text-muted-foreground h-24 text-center">
                              No team members yet.
                           </TableCell>
                        </TableRow>
                     ) : (
                        teamMembers.map((member) => (
                           <TableRow key={member.id}>
                              <TableCell>
                                 {member.photo ? (
                                    <img src={member.photo} alt={member.name} className="h-14 w-10 rounded object-cover" />
                                 ) : (
                                    <div className="bg-muted h-14 w-10 rounded" />
                                 )}
                              </TableCell>
                              <TableCell className="font-medium">{member.name}</TableCell>
                              <TableCell>{member.role}</TableCell>
                              <TableCell>{member.sort_order}</TableCell>
                              <TableCell>{member.is_active ? 'Visible' : 'Hidden'}</TableCell>
                              <TableCell className="text-right">
                                 <div className="flex justify-end gap-2">
                                    <Button size="icon" variant="secondary" className="h-8 w-8" onClick={() => openEdit(member)}>
                                       <Pencil className="h-4 w-4" />
                                    </Button>
                                    <DeleteModal
                                       routePath={route('settings.team-members.destroy', member.id)}
                                       message={`Remove ${member.name} from the team section?`}
                                       actionComponent={
                                          <Button size="icon" variant="ghost" className="bg-destructive/8 hover:bg-destructive/6 h-8 w-8 p-0">
                                             <Trash2 className="text-destructive h-4 w-4" />
                                          </Button>
                                       }
                                    />
                                 </div>
                              </TableCell>
                           </TableRow>
                        ))
                     )}
                  </TableBody>
               </Table>
            </Card>
         </div>

         <ImageCropDialog
            open={createCrop.cropOpen}
            imageSrc={createCrop.cropImageSrc}
            onCancel={createCrop.handleCropCancel}
            onApply={createCrop.handleCropApply}
            title="Crop team photo"
            description="Position the portrait photo, then apply before saving."
            aspect={3 / 4}
            cropShape="rect"
            outputWidth={480}
            outputHeight={640}
            fileName="team-photo.jpg"
         />

         <ImageCropDialog
            open={editCrop.cropOpen}
            imageSrc={editCrop.cropImageSrc}
            onCancel={editCrop.handleCropCancel}
            onApply={editCrop.handleCropApply}
            title="Crop team photo"
            description="Position the portrait photo, then apply before saving."
            aspect={3 / 4}
            cropShape="rect"
            outputWidth={480}
            outputHeight={640}
            fileName="team-photo.jpg"
         />

         <Dialog open={Boolean(editingMember)} onOpenChange={(open) => !open && closeEdit()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
               <DialogHeader>
                  <DialogTitle>Edit team member</DialogTitle>
               </DialogHeader>
               {editingMember && (
                  <form onSubmit={submitEdit} className="space-y-4">
                     <div>
                        <Label htmlFor="edit-name">Name</Label>
                        <Input id="edit-name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} required />
                        <InputError message={editForm.errors.name} />
                     </div>
                     <div>
                        <Label htmlFor="edit-role">Role / title</Label>
                        <Input id="edit-role" value={editForm.data.role} onChange={(e) => editForm.setData('role', e.target.value)} required />
                        <InputError message={editForm.errors.role} />
                     </div>
                     {renderPhotoField(editForm, editCrop, editPreview, editingMember.photo)}
                     <div>
                        <Label htmlFor="edit-sort">Display order</Label>
                        <Input
                           id="edit-sort"
                           type="number"
                           min={0}
                           value={editForm.data.sort_order}
                           onChange={(e) => editForm.setData('sort_order', Number(e.target.value))}
                        />
                     </div>
                     <div className="flex items-center justify-between rounded-lg border p-3">
                        <Label htmlFor="edit-active">Visible on About page</Label>
                        <Switch id="edit-active" checked={editForm.data.is_active} onCheckedChange={(checked) => editForm.setData('is_active', checked)} />
                     </div>
                     <LoadingButton loading={editForm.processing}>Update team member</LoadingButton>
                  </form>
               )}
            </DialogContent>
         </Dialog>
      </>
   );
};

TeamSettings.layout = (page: ReactNode) => <DashboardLayout children={page} />;

export default TeamSettings;
