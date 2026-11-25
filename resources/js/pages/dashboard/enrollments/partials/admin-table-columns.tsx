import DeleteModal from '@/components/inertia/delete-modal';
import { Button } from '@/components/ui/button';
import { router, usePage } from '@inertiajs/react';
import EnrollmentStudentTagEditor from './enrollment-student-tag-editor';
import { ColumnDef } from '@tanstack/react-table';
import { Trash2 } from 'lucide-react';
import { enrollmentBillingColumns } from './enrollment-billing-columns';
import MoveCourseButton from './move-course-button';
import { EnrollmentListControls, EnrollmentSelectCell, EnrollmentSelectHeader, EnrollmentSortHeader } from './enrollment-list-controls';

const AdminTableColumn = (
   enrollmentType: 'course' | 'exam',
   translate: LanguageTranslations,
   deleteRoute: string,
   controls?: EnrollmentListControls,
): ColumnDef<CourseEnrollment | ExamEnrollment>[] => {
   const { table } = translate;

   return [
      ...(controls?.selection
         ? [
              {
                 id: 'select',
                 header: () => <EnrollmentSelectHeader selection={controls.selection!} />,
                 cell: ({ row }) => <EnrollmentSelectCell selection={controls.selection!} id={row.original.id} />,
              } satisfies ColumnDef<CourseEnrollment | ExamEnrollment>,
           ]
         : []),
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
                        <div className="bg-muted text-muted-foreground flex h-full w-full items-center justify-center">
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
         header: () =>
            enrollmentType === 'course' ? (
               <EnrollmentSortHeader label={table.enrolled_course} column="course" sort={controls?.sort} onSort={controls?.onSort} />
            ) : (
               'Enrolled Exam'
            ),
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
      ...enrollmentBillingColumns(enrollmentType, translate, controls),
      ...(enrollmentType === 'course'
         ? [
              {
                 id: 'student_tags',
                 header: () => <EnrollmentSortHeader label="Student Tags" column="tags" sort={controls?.sort} onSort={controls?.onSort} />,
                 cell: ({ row }) => <AdminStudentTags user={(row.original as CourseEnrollment).user} />,
              } satisfies ColumnDef<CourseEnrollment | ExamEnrollment>,
           ]
         : []),
      {
         id: 'actions',
         header: () => <div className="pr-4 text-end">{table.action}</div>,
         cell: ({ row }) => {
            const courseEnrollment = enrollmentType === 'course' ? (row.original as CourseEnrollment) : null;

            return (
               <div className="flex items-center justify-end gap-2 pr-4">
                  {courseEnrollment ? <MoveCourseButton enrollment={courseEnrollment} /> : null}
                  {courseEnrollment?.can_keep_course ? (
                     <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => {
                           if (
                              !window.confirm(
                                 'Stop Stripe billing for this student and keep their full access to the course?',
                              )
                           ) {
                              return;
                           }

                           router.post(route('enrollments.keep-course', courseEnrollment.id), {}, { preserveScroll: true });
                        }}
                     >
                        Keep course
                     </Button>
                  ) : courseEnrollment?.access_retained ? (
                     <span className="text-muted-foreground text-xs">Access kept</span>
                  ) : null}
                  <DeleteModal
                     routePath={route(deleteRoute, row.original.id)}
                     actionComponent={
                        <Button size="icon" variant="ghost" className="h-8 w-8 bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-500">
                           <Trash2 className="h-5 w-5" />
                        </Button>
                     }
                  />
               </div>
            );
         },
      },
   ];
};

const AdminStudentTags = ({
   user,
}: {
   user: CourseEnrollment['user'];
}) => {
   const { studentLabels = [] } = usePage<{ studentLabels?: { id: number; name: string; color: string }[] }>().props;

   return <EnrollmentStudentTagEditor userId={user.id} user={user} labels={studentLabels} />;
};

export default AdminTableColumn;
