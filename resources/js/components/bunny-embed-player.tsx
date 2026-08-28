import { preloadPlayerJs } from '@/lib/bunny-player-js';
import type { PlayerJsInstance } from '@/lib/bunny-player-js';
import { useEffect, useRef } from 'react';

interface BunnyEmbedPlayerProps {
   embedUrl: string;
   title?: string;
   onEnded?: () => void;
   onWatchProgress?: (currentTime: number, duration: number) => void;
}

function iframeSrcFor(embedUrl: string): string {
   return embedUrl.includes('playerjs=') ? embedUrl : `${embedUrl}${embedUrl.includes('?') ? '&' : '?'}playerjs=true`;
}

const BunnyEmbedPlayer = ({ embedUrl, title = 'Lesson video', onEnded, onWatchProgress }: BunnyEmbedPlayerProps) => {
   const hostRef = useRef<HTMLDivElement>(null);
   const onEndedRef = useRef(onEnded);
   const onWatchProgressRef = useRef(onWatchProgress);
   const lastReportedSecond = useRef(-1);
   const hasEnded = useRef(false);

   onEndedRef.current = onEnded;
   onWatchProgressRef.current = onWatchProgress;

   const iframeSrc = iframeSrcFor(embedUrl);

   useEffect(() => {
      void preloadPlayerJs();
   }, []);

   // React never owns the iframe node. Bunny/player.js mutate it; a React-managed
   // iframe is what throws insertBefore and shows the lesson error pane.
   useEffect(() => {
      const host = hostRef.current;

      if (!host) {
         return;
      }

      hasEnded.current = false;
      lastReportedSecond.current = -1;

      const iframe = document.createElement('iframe');
      iframe.src = iframeSrc;
      iframe.title = title;
      iframe.className = 'absolute inset-0 h-full w-full border-0';
      iframe.allow = 'accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;';
      iframe.allowFullscreen = true;
      host.replaceChildren(iframe);

      let player: PlayerJsInstance | null = null;
      let cancelled = false;

      const reportProgress = (currentTime: number, duration: number) => {
         if (!onWatchProgressRef.current || duration <= 0) {
            return;
         }

         const second = Math.floor(currentTime);
         if (second === lastReportedSecond.current) {
            return;
         }

         lastReportedSecond.current = second;
         onWatchProgressRef.current(second, duration);
      };

      const handleEnded = (duration = 0) => {
         if (hasEnded.current) {
            return;
         }

         hasEnded.current = true;

         if (onWatchProgressRef.current && duration > 0) {
            onWatchProgressRef.current(duration, duration);
         }

         onEndedRef.current?.();
      };

      const handleTimeUpdate = (currentTime: number, duration: number) => {
         reportProgress(currentTime, duration);

         if (duration > 0 && currentTime >= Math.max(duration - 1, duration * 0.98)) {
            handleEnded(duration);
         }
      };

      const bindPlayerJs = async () => {
         try {
            await preloadPlayerJs();

            if (cancelled || !iframe.isConnected || !window.playerjs?.Player) {
               return;
            }

            player = new window.playerjs.Player(iframe);

            player.on('ready', () => {
               if (cancelled || !player) {
                  return;
               }

               player.on('timeupdate', (data: unknown) => {
                  const timing = data as { seconds?: number; duration?: number };
                  handleTimeUpdate(timing?.seconds ?? 0, timing?.duration ?? 0);
               });

               player.on('ended', () => {
                  handleEnded();
               });
            });
         } catch {
            // Fall back to Bunny's native postMessage events below.
         }
      };

      const handleMessage = (event: MessageEvent) => {
         if (typeof event.data !== 'object' || event.data === null) {
            return;
         }

         const payload = event.data as {
            channel?: string;
            event?: string;
            currentTime?: number;
            duration?: number;
            seconds?: number;
            status?: { currentTime?: number; duration?: number };
         };

         if (payload.channel === 'bunnystream') {
            if (payload.event === 'timeupdate') {
               handleTimeUpdate(payload.status?.currentTime ?? 0, payload.status?.duration ?? 0);
            }

            if (payload.event === 'ended') {
               handleEnded(payload.status?.duration ?? 0);
            }

            return;
         }

         if (payload.event === 'timeupdate') {
            handleTimeUpdate(payload.currentTime ?? payload.seconds ?? 0, payload.duration ?? 0);
         }

         if (payload.event === 'ended') {
            handleEnded(payload.duration ?? 0);
         }
      };

      void bindPlayerJs();
      window.addEventListener('message', handleMessage);

      return () => {
         cancelled = true;
         window.removeEventListener('message', handleMessage);

         if (player) {
            try {
               player.off('timeupdate');
               player.off('ended');
               player.off('ready');
            } catch {
               // player.js can throw if the iframe was already detached.
            }
         }

         host.replaceChildren();
      };
   }, [iframeSrc, title]);

   return <div ref={hostRef} className="bg-muted relative aspect-video w-full overflow-hidden rounded-lg" />;
};

export default BunnyEmbedPlayer;
