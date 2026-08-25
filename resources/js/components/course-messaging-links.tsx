import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { MessageCircle, MessagesSquare } from 'lucide-react';

type Props = {
   courseId: number;
   canAccess?: boolean;
   compact?: boolean;
};

export default function CourseMessagingLinks({ courseId, canAccess = true, compact = false }: Props) {
   if (!canAccess) {
      return null;
   }

   return (
      <div className={compact ? 'flex flex-wrap gap-2' : 'mt-4 flex flex-wrap gap-3'}>
         <Button asChild variant="brand" size={compact ? 'sm' : 'default'}>
            <Link href={route('messages.dm', courseId)}>
               <MessageCircle className="h-4 w-4" />
               Message instructor
            </Link>
         </Button>
         <Button asChild variant="outline" size={compact ? 'sm' : 'default'}>
            <Link href={route('messages.group', courseId)}>
               <MessagesSquare className="h-4 w-4" />
               Class chat
            </Link>
         </Button>
      </div>
   );
}
