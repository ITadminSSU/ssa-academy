import { ColumnDef } from '@tanstack/react-table';
import { enrollmentBillingColumns } from './enrollment-billing-columns';

const InstructorTableColumn = (
   enrollmentType: 'course' | 'exam',
   translate: LanguageTranslations,
): ColumnDef<CourseEnrollment | ExamEnrollment>[] => {
   const { table } = translate;

   return [
      {
         id: 'index',
         header: () => <div className="pl-4">#</div>,
         cell: ({ row }) => <div className="w-4 pl-4 text-center font-medium">{row.index + 1}</div>,
      },
      {
         id: 'name',
         header: table.name,
         cell: ({ row }) => {
            const user = row.original.user;
            return (
               <div className="flex items-center gap-3">
                  <div className="bg-muted h-12 w-12 overflow-hidden rounded-full">
                     {user.photo ? (
                        <img src={user.photo} alt={user.name} className="h-full w-full object-cover" />
                     ) : (
                        <div className="flex h-full w-full items-center justify-center bg-muted text-muted-foreground">
                           <span className="text-lg">{table.img_placeholder}</span>
                        </div>
                     )}
                  </div>
                  <div>
                     <p className="font-medium">{user.name}</p>
                     <p className="text-muted-foreground text-sm">{user.email}</p>
                  </div>
               </div>
            );
         },
      },
      {
         id: 'enrolled_course',
         header: () => (enrollmentType === 'course' ? table.enrolled_course : 'Enrolled Exam'),
         cell: ({ row }) => {
            const exam = row.original as ExamEnrollment;
            const course = row.original as CourseEnrollment;

            return (
               <div className="max-w-md">
                  <p className="line-clamp-1">{enrollmentType === 'course' ? course.course.title : exam.exam.title}</p>
               </div>
            );
         },
      },
      ...enrollmentBillingColumns(enrollmentType, translate),
   ];
};

export default InstructorTableColumn;
