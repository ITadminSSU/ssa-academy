import ButtonGradientPrimary from '@/components/button-gradient-primary';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Award, PlayCircle } from 'lucide-react';

interface Props {
   className?: string;
   enrollment: CourseEnrollment;
}

const CourseCardProgress = ({ enrollment, className }: Props) => {
   const { course, watch_history, completion } = enrollment;
   const percent = Number(completion?.completion ?? 0);
   const isComplete = percent >= 100;
   const finalExam = course.final_exam;

   const courseShowUrl = route('student.course.show', { id: course.id, tab: 'modules' });
   const finalExamUrl = finalExam
      ? route('student.exam.show', { id: finalExam.id, tab: 'attempts' })
      : null;
   const continueUrl = watch_history
      ? route('course.player', {
           type: watch_history.current_watching_type,
           watch_history: watch_history.id,
           lesson_id: watch_history.current_watching_id,
        })
      : courseShowUrl;

   return (
      <Card className={cn('flex flex-col overflow-hidden shadow-sm', className)}>
         <CardHeader className="p-0">
            <Link className="p-2 pb-0" href={courseShowUrl}>
               <div className="relative h-[200px] w-full overflow-hidden rounded-lg">
                  <img
                     src={course.thumbnail || '/assets/images/blank-image.jpg'}
                     alt={course.title}
                     className="h-full w-full object-cover transition-transform duration-300 hover:scale-105"
                     onError={(e) => {
                        (e.target as HTMLImageElement).src = '/assets/images/blank-image.jpg';
                     }}
                  />
                  {isComplete && (
                     <span className="absolute right-2 top-2 rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-medium text-white">
                        Completed
                     </span>
                  )}
               </div>
            </Link>
         </CardHeader>

         <CardContent className="flex flex-1 flex-col p-4">
            <div className="mb-3 flex items-center gap-2">
               <Avatar className="h-5 w-5">
                  <AvatarImage src={course.instructor.user.photo || ''} alt={course.instructor.user.name} className="object-cover" />
                  <AvatarFallback>IM</AvatarFallback>
               </Avatar>
               <p className="text-xs font-medium">{course.instructor.user.name}</p>
            </div>

            <Link href={courseShowUrl}>
               <p className="hover:text-secondary-foreground line-clamp-2 text-sm font-semibold">{course.title}</p>
            </Link>

            <div className="mt-auto space-y-3 pt-4">
               <div className="space-y-1.5">
                  <div className="text-muted-foreground flex items-center justify-between text-xs font-medium">
                     <span>Progress</span>
                     <span>{percent}%</span>
                  </div>
                  <Progress value={percent} className="h-1.5" />
               </div>

               {isComplete && finalExamUrl ? (
                  <Button asChild variant="outline" className="w-full">
                     <Link href={finalExamUrl}>
                        <Award className="h-4 w-4" />
                        Take Exam
                     </Link>
                  </Button>
               ) : isComplete ? (
                  <Button asChild variant="outline" className="w-full">
                     <Link href={courseShowUrl}>
                        <Award className="h-4 w-4" />
                        View Course
                     </Link>
                  </Button>
               ) : (
                  <ButtonGradientPrimary asChild shadow={false} containerClass="w-full" className="to-primary-light hover:to-primary-light h-9 w-full">
                     <Link href={continueUrl}>
                        <PlayCircle className="h-4 w-4" />
                        Continue Learning
                     </Link>
                  </ButtonGradientPrimary>
               )}
            </div>
         </CardContent>
      </Card>
   );
};

export default CourseCardProgress;
