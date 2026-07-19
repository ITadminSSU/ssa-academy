import AnnouncementForm from '@/components/announcement-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useAuth } from '@/hooks/use-auth';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';
import { useState } from 'react';

const Announcements = () => {
   const { announcements = [] } = usePage<StudentDashboardProps>().props;
   const { isAdmin, isInstructor } = useAuth();
   const canManageAnnouncements = isAdmin || isInstructor;
   const [dialogOpen, setDialogOpen] = useState(false);

   return (
      <div className="space-y-6">
         <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
               <h1 className="text-2xl font-bold tracking-tight">Announcements</h1>
               <p className="text-muted-foreground mt-1 text-sm">Latest news and updates from your trainers.</p>
            </div>

            {canManageAnnouncements && (
               <Button onClick={() => setDialogOpen(true)}>
                  <Plus className="h-4 w-4" />
                  Add Announcement
               </Button>
            )}
         </div>

         {announcements.length > 0 ? (
            <div className="space-y-4">
               {announcements.map((announcement) => (
                  <Card key={announcement.id} className="border">
                     <CardContent className="space-y-2 p-5">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                           <h2 className="font-semibold">{announcement.title}</h2>
                           <span className="text-muted-foreground text-xs">
                              {announcement.created_at ? new Date(announcement.created_at).toLocaleDateString() : ''}
                           </span>
                        </div>
                        <p className="text-muted-foreground text-sm whitespace-pre-line">{announcement.body}</p>
                        {announcement.author?.name && (
                           <p className="text-muted-foreground text-xs">— {announcement.author.name}</p>
                        )}
                     </CardContent>
                  </Card>
               ))}
            </div>
         ) : (
            <Card className="border">
               <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                  <Megaphone className="text-muted-foreground h-10 w-10" />
                  <p className="text-muted-foreground text-sm">No announcements right now.</p>
                  {canManageAnnouncements && (
                     <Button variant="outline" onClick={() => setDialogOpen(true)}>
                        <Plus className="h-4 w-4" />
                        Add Announcement
                     </Button>
                  )}
               </CardContent>
            </Card>
         )}

         {canManageAnnouncements && (
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
               <DialogContent>
                  <DialogHeader>
                     <DialogTitle>New Announcement</DialogTitle>
                  </DialogHeader>
                  <AnnouncementForm onDone={() => setDialogOpen(false)} />
               </DialogContent>
            </Dialog>
         )}
      </div>
   );
};

export default Announcements;
