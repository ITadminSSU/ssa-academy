import CourseCard7 from '@/components/cards/course-card-7';
import Tabs from '@/components/tabs';
import { Card } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { StudentCourseProps } from '@/types/page';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import Layout from './partials/layout';
import CourseAssignments from './tabs-content/course-assignments';
import CourseCertificate from './tabs-content/course-certificate';
import CourseModules from './tabs-content/course-modules';
import CourseQuizzes from './tabs-content/course-quizzes';
import CourseResources from './tabs-content/course-resources';
import SubscriptionAccessBanner from '@/pages/course-player/partials/subscription-access-banner';

const Course = (props: StudentCourseProps) => {
   const { tab, course, watchHistory, completion } = props;

   const tabs = [
      {
         value: 'modules',
         label: 'Modules',
      },
      {
         value: 'assignments',
         label: 'Assignments',
      },
      {
         value: 'quizzes',
         label: 'Quizzes',
      },
      {
         value: 'resources',
         label: 'Resources',
      },
      {
         value: 'certificate',
         label: 'Certificate',
      },
   ];

   const renderContent = () => {
      switch (tab) {
         case 'modules':
            return <CourseModules />;
         case 'assignments':
            return <CourseAssignments />;
         case 'quizzes':
            return <CourseQuizzes />;
         case 'resources':
            return <CourseResources />;
         case 'certificate':
            return <CourseCertificate />;
         default:
            return <></>;
      }
   };

   return (
      <>
         <Head title={course.title} />

         <CourseCard7 course={course} watch_history={watchHistory} completion={completion} />

         <SubscriptionAccessBanner />

         <Card className="mt-4">
            <Tabs value={tab} className="bg-card w-full overflow-hidden rounded-md">
               <div className="overflow-x-auto overflow-y-hidden">
                  <TabsList className="bg-transparent px-0 py-6">
                     {tabs.map(({ label, value }) => {
                        return (
                           <TabsTrigger
                              key={value}
                              value={value}
                              className="border-primary data-[state=active]:!bg-muted data-[state=active]:before:bg-primary relative flex cursor-pointer items-center justify-start gap-3 rounded-none bg-transparent px-8 py-4 text-start !shadow-none before:absolute before:right-0 before:bottom-0 before:left-0 before:h-1 before:rounded-t-xl data-[state=active]:before:content-['.']"
                              asChild
                           >
                              <Link
                                 href={route('student.course.show', {
                                    id: course.id,
                                    tab: value,
                                 })}
                              >
                                 <span>{label}</span>
                              </Link>
                           </TabsTrigger>
                        );
                     })}
                  </TabsList>
               </div>

               <Separator className="mt-[1px]" />

               <TabsContent value={tab} className="m-0 p-5">
                  {renderContent()}
               </TabsContent>
            </Tabs>
         </Card>
      </>
   );
};

Course.layout = (page: ReactNode) => <Layout children={page} tab="courses" />;

export default Course;
