import TableFilter from '@/components/table/table-filter';
import TableFooter from '@/components/table/table-footer';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { Fragment, ReactNode, useState } from 'react';

interface AssessmentScore {
   type: 'quiz' | 'assignment' | 'practical_activity';
   title: string;
   score_percent: number;
}

interface CourseScore {
   course_id: number;
   course_title: string;
   score_percent: number;
   graded_assessments: number;
   course_size: number;
   assessments: AssessmentScore[];
}

interface TopPerformerRow {
   rank: number;
   is_top_performer: boolean;
   average_score_percent: number;
   courses_enrolled: number;
   courses_graded: number;
   total_course_size: number;
   course_scores: CourseScore[];
   user: {
      id: number;
      name: string;
      email: string;
      photo: string | null;
   };
}

interface Props extends SharedData {
   performers: Pagination<TopPerformerRow>;
   top_limit: number;
   audience: 'staff' | 'learner';
}

const assessmentTypeLabel = (
   type: AssessmentScore['type'],
   dashboard: Record<string, string | undefined>,
): string => {
   if (type === 'practical_activity') {
      return dashboard.practical_activity ?? 'Practical activity';
   }

   if (type === 'quiz') {
      return dashboard.quiz ?? 'Quiz';
   }

   return dashboard.assignment ?? 'Assignment';
};

const TopPerformersIndex = ({ performers, top_limit, audience, translate, auth }: Props) => {
   const { dashboard } = translate;
   const [expandedUserId, setExpandedUserId] = useState<number | null>(null);

   const routeName =
      audience === 'learner'
         ? 'learner.top-performers'
         : auth.user.role === 'admin'
           ? 'admin.top-performers.index'
           : 'top-performers.index';

   const content = (
      <div className="space-y-6">
         <div>
            <h1 className="font-display text-2xl font-semibold tracking-tight">
               {dashboard.top_performers_title ?? 'Top Performers'}
            </h1>
            <p className="text-muted-foreground mt-1 text-sm">
               {dashboard.top_performers_description ??
                  'Learners ranked by a course-size-weighted average across all enrolled courses. Each course score includes quizzes, assignments, and practical activities. Top ranks are tagged as Top Performer.'}
            </p>
         </div>

         <Card>
            <CardHeader>
               <CardTitle className="text-base">
                  {dashboard.overall_course_leaderboard ?? 'Overall course leaderboard'}
               </CardTitle>
            </CardHeader>

            <TableFilter
               data={performers}
               title=""
               globalSearch
               tablePageSizes={[20, 50, 100]}
               routeName={routeName}
               className="border-b px-5 sm:px-7"
            />

            <CardContent className="p-0">
               <Table>
                  <TableHeader>
                     <TableRow>
                        <TableHead className="w-16">#</TableHead>
                        <TableHead>{dashboard.student ?? 'Learner'}</TableHead>
                        <TableHead>{dashboard.courses ?? 'Courses'}</TableHead>
                        <TableHead>{dashboard.average_score ?? 'Weighted average'}</TableHead>
                        <TableHead>{dashboard.status ?? 'Status'}</TableHead>
                     </TableRow>
                  </TableHeader>
                  <TableBody>
                     {performers.data.length === 0 ? (
                        <TableRow>
                           <TableCell colSpan={5} className="text-muted-foreground py-10 text-center">
                              {dashboard.no_graded_learners_yet ??
                                 'No learners with graded quiz, assignment, or practical activity work yet.'}
                           </TableCell>
                        </TableRow>
                     ) : (
                        performers.data.map((row) => (
                           <Fragment key={row.user.id}>
                              <TableRow
                                 className="cursor-pointer"
                                 onClick={() =>
                                    setExpandedUserId((current) => (current === row.user.id ? null : row.user.id))
                                 }
                              >
                                 <TableCell className="font-semibold tabular-nums">{row.rank}</TableCell>
                                 <TableCell>
                                    <div>
                                       <p className="font-medium">{row.user.name}</p>
                                       <p className="text-muted-foreground text-xs">{row.user.email}</p>
                                    </div>
                                 </TableCell>
                                 <TableCell className="tabular-nums">
                                    {row.courses_graded} / {row.courses_enrolled}
                                 </TableCell>
                                 <TableCell className="font-semibold tabular-nums">{row.average_score_percent}%</TableCell>
                                 <TableCell>
                                    {row.is_top_performer ? (
                                       <Badge className="bg-amber-500/15 text-amber-700 hover:bg-amber-500/20 dark:text-amber-300">
                                          {dashboard.top_performer ?? 'Top Performer'}
                                       </Badge>
                                    ) : (
                                       <span className="text-muted-foreground text-sm">—</span>
                                    )}
                                 </TableCell>
                              </TableRow>
                              {expandedUserId === row.user.id && (
                                 <TableRow key={`${row.user.id}-detail`}>
                                    <TableCell colSpan={5} className="bg-muted/30">
                                       <div className="space-y-4 py-2">
                                          <p className="text-sm font-medium">
                                             {dashboard.course_breakdown ?? 'Course breakdown'}
                                          </p>
                                          <div className="grid gap-3">
                                             {row.course_scores.map((course) => (
                                                <div
                                                   key={course.course_id}
                                                   className="rounded-md border bg-background px-3 py-3"
                                                >
                                                   <div className="flex flex-wrap items-center justify-between gap-2">
                                                      <p className="font-medium">{course.course_title}</p>
                                                      <div className="flex items-center gap-3 text-sm">
                                                         <span className="text-muted-foreground">
                                                            {dashboard.graded_assessments ?? 'Graded'}:{' '}
                                                            {course.graded_assessments} / {course.course_size}
                                                         </span>
                                                         <span className="font-semibold tabular-nums">
                                                            {course.score_percent}%
                                                         </span>
                                                      </div>
                                                   </div>
                                                   {course.assessments.length > 0 && (
                                                      <ul className="mt-2 space-y-1 border-t pt-2">
                                                         {course.assessments.map((assessment, index) => (
                                                            <li
                                                               key={`${course.course_id}-${assessment.type}-${index}`}
                                                               className="flex items-center justify-between gap-3 text-sm"
                                                            >
                                                               <span className="text-muted-foreground truncate">
                                                                  {assessmentTypeLabel(assessment.type, dashboard)}:{' '}
                                                                  {assessment.title}
                                                               </span>
                                                               <span className="font-medium tabular-nums">
                                                                  {assessment.score_percent}%
                                                               </span>
                                                            </li>
                                                         ))}
                                                      </ul>
                                                   )}
                                                </div>
                                             ))}
                                          </div>
                                       </div>
                                    </TableCell>
                                 </TableRow>
                              )}
                           </Fragment>
                        ))
                     )}
                  </TableBody>
               </Table>
            </CardContent>

            <TableFooter className="p-5 sm:p-7" routeName={routeName} paginationInfo={performers} />
         </Card>

         <p className="text-muted-foreground text-xs">
            {(dashboard.top_performers_footnote ??
               'Overall score weights each course by its number of assessments (quizzes, assignments, and practical activities). Top Performer tag is awarded to the top :limit ranked learners with graded work.')
               .replace(':limit', String(top_limit))}
         </p>
      </div>
   );

   return content;
};

TopPerformersIndex.layout = (page: ReactNode) => {
   const { audience, translate } = page.props as Props;

   return (
      <DashboardLayout
         variant={audience === 'learner' ? 'learner' : 'admin'}
         headTitle={translate.dashboard.top_performers_title ?? 'Top Performers'}
      >
         {page}
      </DashboardLayout>
   );
};

export default TopPerformersIndex;
