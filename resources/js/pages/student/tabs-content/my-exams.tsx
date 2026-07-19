import ExamCard2 from '@/components/exam/exam-card-2';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { StudentDashboardProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';

const MyExams = () => {
   const { examEnrollments } = usePage<StudentDashboardProps>().props;

   return examEnrollments && examEnrollments.length > 0 ? (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">My Exams</h1>
            <p className="text-muted-foreground mt-1 text-sm">Open an exam to view attempts, resources, and your certificate.</p>
         </div>

         <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 md:grid-cols-2 lg:grid-cols-3">
            {examEnrollments.map((enrollment) => (
               <ExamCard2 key={enrollment.id} enrollment={enrollment} />
            ))}
         </div>
      </div>
   ) : (
      <Card className="flex flex-col items-center justify-center gap-4 p-10 text-center">
         <p className="text-muted-foreground">You haven't enrolled in any exams yet.</p>
         <Button asChild>
            <Link href={route('exams.browse')}>Browse Exams</Link>
         </Button>
      </Card>
   );
};

export default MyExams;
