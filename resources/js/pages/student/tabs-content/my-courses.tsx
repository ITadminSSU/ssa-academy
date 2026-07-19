import CourseCardProgress from '@/components/cards/course-card-progress';
import { Card } from '@/components/ui/card';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const MyCourses = () => {
   const { courseEnrollments, translate } = usePage<StudentDashboardProps>().props;
   const { frontend } = translate;

   return courseEnrollments && courseEnrollments.length > 0 ? (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">My Courses</h1>
            <p className="text-muted-foreground mt-1 text-sm">Track your progress and pick up where you left off.</p>
         </div>

         <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            {courseEnrollments.map((enrollment) => (
               <CourseCardProgress key={enrollment.id} enrollment={enrollment} />
            ))}
         </div>
      </div>
   ) : (
      <Card className="flex items-center justify-center p-6">
         <p>{frontend.no_courses_found}</p>
      </Card>
   );
};

export default MyCourses;
