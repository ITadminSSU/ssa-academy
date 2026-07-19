import { openDiscussion } from '@/components/community-discussion-list';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CommunityDiscussion } from '@/types/page';
import { Link } from '@inertiajs/react';
import { MessageCircle, MessagesSquare } from 'lucide-react';

type Props = {
   openForumQuestions: number;
   forumPreview: CommunityDiscussion[];
   forumQueueUrl: string | null;
};

const OpenForumQuestions = ({ openForumQuestions, forumPreview, forumQueueUrl }: Props) => {
   if (!forumQueueUrl) {
      return null;
   }

   return (
      <Card className="ssu-surface-card">
         <CardHeader className="flex flex-row items-center justify-between gap-4 space-y-0 pb-4">
            <div className="space-y-1">
               <CardTitle className="flex items-center gap-2 text-lg">
                  <MessagesSquare className="h-5 w-5" />
                  Open forum questions
               </CardTitle>
               <p className="text-muted-foreground text-sm">
                  {openForumQuestions > 0
                     ? `${openForumQuestions} learner question${openForumQuestions === 1 ? '' : 's'} awaiting a reply.`
                     : 'No open learner questions right now.'}
               </p>
            </div>

            <Button asChild variant={openForumQuestions > 0 ? 'default' : 'outline'} size="sm">
               <Link href={forumQueueUrl}>View all</Link>
            </Button>
         </CardHeader>

         {forumPreview.length > 0 ? (
            <CardContent className="space-y-3 pt-0">
               {forumPreview.map((discussion) => (
                  <div key={discussion.id} className="flex items-start justify-between gap-3 rounded-lg border p-3">
                     <div className="min-w-0">
                        <p className="truncate font-medium">{discussion.title}</p>
                        <p className="text-muted-foreground truncate text-sm">
                           {discussion.course?.title}
                           {discussion.lesson?.title ? ` · ${discussion.lesson.title}` : ''}
                        </p>
                     </div>
                     <Button type="button" variant="ghost" size="sm" className="shrink-0" onClick={() => openDiscussion(discussion)}>
                        <MessageCircle className="mr-2 h-4 w-4" />
                        Reply
                     </Button>
                  </div>
               ))}
            </CardContent>
         ) : null}
      </Card>
   );
};

export default OpenForumQuestions;
