import CommunityDiscussionList from '@/components/community-discussion-list';
import { getStudentDashboardUrl } from '@/lib/dashboard';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const Community = () => {
   const {
      discussions = [],
      communityCourses = [],
      isTrainerView = false,
      communityFilter = 'all',
      communityCourseId = null,
      auth,
   } = usePage<StudentDashboardProps>().props;

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Community Discussion</h1>
            <p className="text-muted-foreground mt-1 text-sm">
               {isTrainerView
                  ? 'Questions from learners across your courses. Reply from the lesson forum in the course player.'
                  : 'Ask questions and learn from discussions across your enrolled courses.'}
            </p>
         </div>

         <CommunityDiscussionList
            discussions={discussions}
            communityCourses={communityCourses}
            isTrainerView={isTrainerView}
            communityFilter={communityFilter}
            communityCourseId={communityCourseId}
            baseUrl={getStudentDashboardUrl(auth.user!, 'community')}
            userId={auth.user!.id}
         />
      </div>
   );
};

export default Community;
