import AnnouncementForm from '@/components/announcement-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Head, router } from '@inertiajs/react';import { useState } from 'react';

interface Props extends SharedData {
   announcements: Announcement[];
}

const Index = ({ announcements }: Props) => {
   const [dialog, setDialog] = useState(false);
   const [editing, setEditing] = useState<Announcement | undefined>(undefined);

   const openCreate = () => {
      setEditing(undefined);
      setDialog(true);
   };

   const openEdit = (announcement: Announcement) => {
      setEditing(announcement);
      setDialog(true);
   };

   return (
      <>
         <Head title="Announcements" />

         <div className="container mx-auto space-y-6 px-4 py-6">
            <div className="flex items-center justify-between">
               <div>
                  <h1 className="text-2xl font-bold">Announcements</h1>
                  <p className="text-muted-foreground mt-1 text-sm">Push updates to your enrolled learners. Admins reach all internal and external students.</p>
               </div>
               <Button onClick={openCreate}>New Announcement</Button>
            </div>

            <Card>
               <CardContent className="p-0">
                  <Table>
                     <TableHeader>
                        <TableRow>
                           <TableHead>Title</TableHead>
                           <TableHead>Status</TableHead>
                           <TableHead>Author</TableHead>
                           <TableHead>Date</TableHead>
                           <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                     </TableHeader>
                     <TableBody>
                        {announcements.map((announcement) => (
                           <TableRow key={announcement.id}>
                              <TableCell className="font-medium">{announcement.title}</TableCell>
                              <TableCell>
                                 {announcement.is_published ? <Badge className="bg-emerald-600">Published</Badge> : <Badge variant="outline">Draft</Badge>}
                              </TableCell>
                              <TableCell>{announcement.author?.name ?? '—'}</TableCell>
                              <TableCell>{announcement.created_at ? new Date(announcement.created_at).toLocaleDateString() : '—'}</TableCell>
                              <TableCell className="space-x-2 text-right">
                                 <Button size="sm" variant="outline" onClick={() => openEdit(announcement)}>
                                    Edit
                                 </Button>
                                 <Button size="sm" variant="destructive" onClick={() => router.delete(route('announcements.destroy', announcement.id))}>
                                    Delete
                                 </Button>
                              </TableCell>
                           </TableRow>
                        ))}
                        {announcements.length === 0 && (
                           <TableRow>
                              <TableCell colSpan={5} className="text-muted-foreground py-8 text-center text-sm">
                                 No announcements yet.
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
                  <DialogTitle>{editing ? 'Edit Announcement' : 'New Announcement'}</DialogTitle>
               </DialogHeader>
               <AnnouncementForm key={editing?.id ?? 'new'} announcement={editing} onDone={() => setDialog(false)} />
            </DialogContent>
         </Dialog>
      </>
   );
};

Index.layout = (page: React.ReactNode) => <DashboardLayout children={page} />;

export default Index;
