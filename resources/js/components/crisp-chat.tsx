import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

declare global {
   interface Window {
      $crisp?: unknown[];
      CRISP_WEBSITE_ID?: string;
   }
}

const loadCrisp = (websiteId: string) => {
   window.CRISP_WEBSITE_ID = websiteId;
   window.$crisp = window.$crisp ?? [];

   if (document.querySelector('script[data-ssu-crisp]')) {
      return;
   }

   const script = document.createElement('script');
   script.src = 'https://client.crisp.chat/l.js';
   script.async = true;
   script.dataset.ssuCrisp = 'true';
   document.head.appendChild(script);
};

const pushCrisp = (command: unknown[]) => {
   window.$crisp?.push(command);
};

const CrispChat = () => {
   const { component, props } = usePage<SharedData>();
   const websiteId = props.crisp?.websiteId?.trim() ?? '';
   const user = props.auth?.user ?? null;
   const lastUserId = useRef<string | number | null>(null);

   useEffect(() => {
      if (!websiteId) {
         return;
      }

      loadCrisp(websiteId);

      const hideOnPlayer = component.startsWith('course-player/');
      pushCrisp(['do', hideOnPlayer ? 'chat:hide' : 'chat:show']);

      const userId = user?.id ?? null;

      if (!userId && lastUserId.current) {
         pushCrisp(['do', 'session:reset']);
         lastUserId.current = null;
         return;
      }

      if (user && userId !== lastUserId.current) {
         lastUserId.current = userId;
         pushCrisp(['set', 'user:email', [user.email]]);
         pushCrisp(['set', 'user:nickname', [user.name]]);
         pushCrisp(['set', 'session:segments', [[user.role]]]);
         pushCrisp([
            'set',
            'session:data',
            [
               [
                  ['role', user.role],
                  ['user_id', String(user.id)],
               ],
            ],
         ]);
      }
   }, [websiteId, component, user]);

   return null;
};

export default CrispChat;
