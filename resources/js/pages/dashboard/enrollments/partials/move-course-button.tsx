import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { useState } from 'react';

interface DestinationCourse {
   id: number;
   title: string;
   previous: boolean;
}

const MoveCourseButton = ({ enrollment }: { enrollment: CourseEnrollment }) => {
   const [open, setOpen] = useState(false);
   const [courses, setCourses] = useState<DestinationCourse[]>([]);
   const [courseId, setCourseId] = useState('');
   const [loading, setLoading] = useState(false);

   const openDialog = async () => {
      setCourseId('');
      setOpen(true);
      setLoading(true);

      try {
         const response = await axios.get<DestinationCourse[]>(route('enrollments.move-destinations', enrollment.id));
         setCourses(response.data);
      } catch {
         setCourses([]);
         window.alert('The course list could not be loaded.');
      } finally {
         setLoading(false);
      }
   };

   const move = () => {
      const chosen = courses.find((course) => String(course.id) === courseId);

      if (!chosen) {
         return;
      }

      const confirmed = window.confirm(
         `Move this student from ${enrollment.course.title} to ${chosen.title}? The current course will lock, the chosen course will open, the Stripe subscription and price stay the same, and saved progress on the locked course stays stored until the student is moved back.`,
      );

      if (!confirmed) {
         return;
      }

      router.post(
         route('enrollments.move', enrollment.id),
         { course_id: chosen.id },
         {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
         },
      );
   };

   if (enrollment.access_status === 'transferred') {
      return <span className="text-muted-foreground text-xs">Locked</span>;
   }

   return (
      <>
         <Button type="button" size="sm" variant="outline" onClick={openDialog}>
            Move course
         </Button>
         <Dialog open={open} onOpenChange={setOpen}>
            <DialogContent>
               <DialogHeader>
                  <DialogTitle>Move course</DialogTitle>
                  <DialogDescription>
                     The current course will lock, the chosen course will open, the Stripe subscription and price stay the same, and saved
                     progress on the locked course stays stored until the student is moved back.
                  </DialogDescription>
               </DialogHeader>
               <Select value={courseId || undefined} onValueChange={setCourseId} disabled={loading || courses.length === 0}>
                  <SelectTrigger>
                     <SelectValue placeholder={loading ? 'Loading courses' : 'Choose a course'} />
                  </SelectTrigger>
                  <SelectContent>
                     {courses.map((course) => (
                        <SelectItem key={course.id} value={String(course.id)}>
                           {course.previous ? `${course.title} (Previous course)` : course.title}
                        </SelectItem>
                     ))}
                  </SelectContent>
               </Select>
               <DialogFooter>
                  <Button type="button" variant="outline" onClick={() => setOpen(false)}>
                     Cancel
                  </Button>
                  <Button type="button" disabled={!courseId} onClick={move}>
                     Move course
                  </Button>
               </DialogFooter>
            </DialogContent>
         </Dialog>
      </>
   );
};

export default MoveCourseButton;
