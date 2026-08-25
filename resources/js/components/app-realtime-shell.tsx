import { MessagesRealtimeProvider } from '@/contexts/messages-realtime-context';
import { ReverbConfig } from '@/lib/echo';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function AppRealtimeShell({ children }: { children: ReactNode }) {
   const { auth, reverb } = usePage<SharedData>().props;
   const userId = auth.user?.id ?? null;
   const messagingRole = ['student', 'instructor', 'admin'].includes(auth.user?.role ?? '');

   if (!userId || !messagingRole) {
      return <>{children}</>;
   }

   return (
      <MessagesRealtimeProvider
         reverb={reverb as ReverbConfig | undefined}
         userId={userId}
         initialUnreadCount={auth.messagesUnreadCount ?? 0}
      >
         {children}
      </MessagesRealtimeProvider>
   );
}
