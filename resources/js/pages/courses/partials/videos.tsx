import { Separator } from '@/components/ui/separator';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { Video } from 'lucide-react';

const videoTypes = ['video', 'video_url'];

const Videos = ({ course }: { course: Course }) => {
   const { props } = usePage<SharedData>();
   const { translate } = props;
   const { frontend } = translate;

   const videos = course.sections.flatMap((section) =>
      section.section_lessons.filter((lesson) => videoTypes.includes(lesson.lesson_type)),
   );

   return (
      <>
         <h6 className="mb-4 text-xl font-semibold">{frontend.course_curriculum}</h6>

         <Separator className="my-6" />

         {videos.length > 0 ? (
            <div className="space-y-1">
               {videos.map((lesson, index) => (
                  <div key={lesson.id} className="flex items-center justify-between gap-3 rounded-lg border px-4 py-3">
                     <div className="flex items-center gap-3">
                        <div className="bg-secondary flex h-7 w-7 items-center justify-center rounded-full text-sm font-medium">
                           {index + 1}
                        </div>
                        <div className="flex items-center gap-2">
                           <Video className="h-4 w-4" />
                           <p>{lesson.title}</p>
                        </div>
                     </div>

                     {lesson.duration && <span className="text-muted-foreground text-sm">{lesson.duration}</span>}
                  </div>
               ))}
            </div>
         ) : (
            <div className="px-4 py-3 text-center">
               <p>{frontend.there_is_no_lesson_added}</p>
            </div>
         )}
      </>
   );
};

export default Videos;
