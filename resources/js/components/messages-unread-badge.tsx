import { useMessagesUnreadCount } from '@/contexts/messages-realtime-context';
import { cn } from '@/lib/utils';

export default function MessagesUnreadBadge({ className }: { className?: string }) {
   const count = useMessagesUnreadCount();

   if (!count) {
      return null;
   }

   return (
      <span
         className={cn(
            'inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-semibold text-white',
            className,
         )}
      >
         {count}
      </span>
   );
}
