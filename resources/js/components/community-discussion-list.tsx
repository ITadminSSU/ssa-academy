import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { getStudentDashboardUrl } from '@/lib/dashboard';
import { CommunityDiscussion } from '@/types/page';
import { router } from '@inertiajs/react';
import { format } from 'date-fns';
import { CheckCircle2, MessageCircle, MessagesSquare, Pin, RotateCcw } from 'lucide-react';

type FilterKey = 'all' | 'unanswered' | 'mine' | 'resolved';

const FILTERS: { key: FilterKey; label: string }[] = [
   { key: 'all', label: 'Open' },
   { key: 'unanswered', label: 'Unanswered' },
   { key: 'mine', label: 'My questions' },
   { key: 'resolved', label: 'Resolved' },
];

type Props = {
   discussions: CommunityDiscussion[];
   communityCourses: Pick<Course, 'id' | 'title'>[];
   isTrainerView?: boolean;
   communityFilter?: string;
   communityCourseId?: number | null;
   baseUrl: string;
   userId: number;
   emptyMessage?: string;
};

export const openDiscussion = (discussion: CommunityDiscussion) => {
   if (!discussion.course || !discussion.lesson) {
      return;
   }

   if (discussion.player) {
      const url = route('course.player', {
         type: 'lesson',
         watch_history: discussion.player.watch_history_id,
         lesson_id: discussion.player.lesson_id,
      });

      router.get(`${url}?panel=forum`);
      return;
   }

   router.post(route('player.init.watch-history'), {
      course_id: discussion.course.id,
      lesson_id: discussion.lesson.id,
      panel: 'forum',
   });
};

const CommunityDiscussionList = ({
   discussions,
   communityCourses,
   isTrainerView = false,
   communityFilter = 'all',
   communityCourseId = null,
   baseUrl,
   emptyMessage,
}: Props) => {
   const applyFilters = (filter: FilterKey, courseId?: string | null) => {
      const params: Record<string, string> = {};

      if (filter !== 'all') {
         params.filter = filter;
      }

      const selectedCourse = courseId ?? (communityCourseId ? String(communityCourseId) : '');
      if (selectedCourse && selectedCourse !== 'all') {
         params.course_id = selectedCourse;
      }

      const query = new URLSearchParams(params).toString();
      router.get(query ? `${baseUrl}?${query}` : baseUrl, {}, { preserveState: true, preserveScroll: true });
   };

   const moderate = (discussion: CommunityDiscussion, action: 'resolve' | 'reopen') => {
      const routeName = action === 'resolve' ? 'course-forums.resolve' : 'course-forums.reopen';
      router.post(route(routeName, discussion.id), {}, { preserveScroll: true });
   };

   return (
      <div className="space-y-6">
         <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap gap-2">
               {FILTERS.map((item) => (
                  <Button
                     key={item.key}
                     type="button"
                     size="sm"
                     variant={communityFilter === item.key ? 'default' : 'outline'}
                     onClick={() => applyFilters(item.key)}
                  >
                     {item.label}
                  </Button>
               ))}
            </div>

            {communityCourses.length > 0 ? (
               <Select
                  value={communityCourseId ? String(communityCourseId) : 'all'}
                  onValueChange={(value) => applyFilters(communityFilter as FilterKey, value)}
               >
                  <SelectTrigger className="w-full sm:w-[220px]">
                     <SelectValue placeholder="All courses" />
                  </SelectTrigger>
                  <SelectContent>
                     <SelectItem value="all">All courses</SelectItem>
                     {communityCourses.map((course) => (
                        <SelectItem key={course.id} value={String(course.id)}>
                           {course.title}
                        </SelectItem>
                     ))}
                  </SelectContent>
               </Select>
            ) : null}
         </div>

         {discussions.length > 0 ? (
            <div className="space-y-4">
               {discussions.map((discussion) => (
                  <Card key={discussion.id} className="border">
                     <CardContent className="space-y-4 p-5">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                           <div className="flex min-w-0 items-start gap-3">
                              <Avatar className="h-10 w-10 shrink-0">
                                 <AvatarImage src={discussion.author?.photo || ''} alt={discussion.author?.name || ''} />
                                 <AvatarFallback>{discussion.author?.name?.charAt(0) || '?'}</AvatarFallback>
                              </Avatar>

                              <div className="min-w-0 space-y-1">
                                 <div className="flex flex-wrap items-center gap-2">
                                    <p className="font-semibold">{discussion.title}</p>
                                    {discussion.needs_reply ? <Badge variant="destructive">Needs reply</Badge> : null}
                                    {discussion.is_resolved ? <Badge variant="secondary">Resolved</Badge> : null}
                                    {discussion.is_mine ? <Badge variant="outline">Your question</Badge> : null}
                                    {discussion.has_instructor_reply ? (
                                       <Badge variant="outline">Instructor replied</Badge>
                                    ) : null}
                                 </div>

                                 <p className="text-muted-foreground text-sm">
                                    {discussion.author?.name}
                                    {discussion.created_at
                                       ? ` · ${format(new Date(discussion.created_at), 'MMM d, yyyy h:mm a')}`
                                       : ''}
                                 </p>

                                 <p className="text-muted-foreground text-sm">
                                    <span className="text-foreground font-medium">{discussion.course?.title}</span>
                                    {discussion.lesson?.title ? ` · ${discussion.lesson.title}` : ''}
                                 </p>

                                 {discussion.excerpt ? (
                                    <p className="text-muted-foreground line-clamp-2 text-sm">{discussion.excerpt}</p>
                                 ) : null}

                                 {discussion.pinned_reply ? (
                                    <div className="bg-muted/50 mt-3 rounded-md border p-3">
                                       <div className="text-muted-foreground mb-1 flex items-center gap-1 text-xs font-medium">
                                          <Pin className="h-3 w-3" />
                                          Pinned answer
                                          {discussion.pinned_reply.author?.name
                                             ? ` · ${discussion.pinned_reply.author.name}`
                                             : ''}
                                       </div>
                                       <div
                                          className="prose prose-sm dark:prose-invert max-w-none text-sm"
                                          dangerouslySetInnerHTML={{ __html: discussion.pinned_reply.description }}
                                       />
                                    </div>
                                 ) : null}
                              </div>
                           </div>

                           <div className="flex shrink-0 flex-wrap gap-2">
                              {discussion.can_moderate && !discussion.is_resolved ? (
                                 <Button type="button" variant="secondary" size="sm" onClick={() => moderate(discussion, 'resolve')}>
                                    <CheckCircle2 className="mr-2 h-4 w-4" />
                                    Resolve
                                 </Button>
                              ) : null}

                              {discussion.can_moderate && discussion.is_resolved ? (
                                 <Button type="button" variant="outline" size="sm" onClick={() => moderate(discussion, 'reopen')}>
                                    <RotateCcw className="mr-2 h-4 w-4" />
                                    Reopen
                                 </Button>
                              ) : null}

                              <Button type="button" variant="outline" size="sm" onClick={() => openDiscussion(discussion)}>
                                 <MessageCircle className="mr-2 h-4 w-4" />
                                 Open thread
                              </Button>
                           </div>
                        </div>

                        <div className="text-muted-foreground flex items-center gap-2 text-xs">
                           <MessageCircle className="h-3.5 w-3.5" />
                           <span>
                              {discussion.replies_count} {discussion.replies_count === 1 ? 'reply' : 'replies'}
                           </span>
                        </div>
                     </CardContent>
                  </Card>
               ))}
            </div>
         ) : (
            <Card className="border">
               <CardContent className="flex flex-col items-center justify-center gap-4 p-16 text-center">
                  <div className="bg-primary/10 text-primary flex h-16 w-16 items-center justify-center rounded-full">
                     <MessagesSquare className="h-8 w-8" />
                  </div>
                  <div>
                     <h2 className="text-lg font-semibold">No discussions yet</h2>
                     <p className="text-muted-foreground mx-auto mt-1 max-w-md text-sm">
                        {emptyMessage ??
                           (communityFilter === 'unanswered'
                              ? 'No unanswered questions right now.'
                              : communityFilter === 'resolved'
                                ? 'No resolved threads yet.'
                                : communityFilter === 'mine'
                                  ? 'You have not posted any questions yet. Open a lesson and use the Forum tab to ask.'
                                  : isTrainerView
                                    ? 'When learners ask questions in course lesson forums, they will appear here.'
                                    : 'When discussions are started in your courses, they will appear here.')}
                     </p>
                  </div>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

export default CommunityDiscussionList;
