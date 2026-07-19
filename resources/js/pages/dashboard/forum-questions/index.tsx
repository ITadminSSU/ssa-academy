import CommunityDiscussionList from '@/components/community-discussion-list';
import DashboardLayout from '@/layouts/dashboard/layout';
import { SharedData } from '@/types/global';
import { CommunityDiscussion } from '@/types/page';
import { Head, usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

interface Props extends SharedData {
   discussions: CommunityDiscussion[];
   communityCourses: Pick<Course, 'id' | 'title'>[];
   isTrainerView?: boolean;
   communityFilter?: string;
   communityCourseId?: number | null;
}

const ForumQuestionsIndex = () => {
   const {
      discussions = [],
      communityCourses = [],
      isTrainerView = true,
      communityFilter = 'unanswered',
      communityCourseId = null,
      auth,
   } = usePage<Props>().props;

   const baseUrl = auth.user.role === 'admin' ? route('admin.forum-questions.index') : route('trainer.forum-questions.index');

   return (
      <>
         <Head title="Forum Questions" />

         <div className="space-y-6">
            <div>
               <h1 className="text-2xl font-bold tracking-tight">Open Forum Questions</h1>
               <p className="text-muted-foreground mt-1 text-sm">
                  Review learner questions, mark threads resolved, and pin helpful answers from the lesson forum.
               </p>
            </div>

            <CommunityDiscussionList
               discussions={discussions}
               communityCourses={communityCourses}
               isTrainerView={isTrainerView}
               communityFilter={communityFilter}
               communityCourseId={communityCourseId}
               baseUrl={baseUrl}
               userId={auth.user.id}
            />
         </div>
      </>
   );
};

ForumQuestionsIndex.layout = (page: ReactNode) => <DashboardLayout>{page}</DashboardLayout>;

export default ForumQuestionsIndex;
