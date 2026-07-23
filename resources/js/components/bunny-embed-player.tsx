import { preloadPlayerJs } from '@/lib/bunny-player-js';
import type { PlayerJsInstance } from '@/lib/bunny-player-js';
import { useEffect, useRef } from 'react';

interface BunnyEmbedPlayerProps {
   embedUrl: string;
   title?: string;
   onEnded?: () => void;
   onWatchProgress?: (currentTime: number, duration: number) => void;
}

const BunnyEmbedPlayer = ({ embedUrl, title = 'Lesson video', onEnded, onWatchProgress }: BunnyEmbedPlayerProps) => {
   const iframeRef = useRef<HTMLIFrameElement>(null);
   const lastReportedSecond = useRef(-1);
   const hasEnded = useRef(false);

   const iframeSrc = embedUrl.includes('playerjs=')
      ? embedUrl
      : `${embedUrl}${embedUrl.includes('?') ? '&' : '?'}playerjs=true`;

   useEffect(() => {
      void preloadPlayerJs();
   }, []);

   useEffect(() => {
      hasEnded.current = false;
      lastReportedSecond.current = -1;

      const iframe = iframeRef.current;
      if (!iframe) {
         return;
      }

      let player: PlayerJsInstance | null = null;
      let cancelled = false;

      const reportProgress = (currentTime: number, duration: number) => {
         if (!onWatchProgress || duration <= 0) {
            return;
         }

         const second = Math.floor(currentTime);
         if (second === lastReportedSecond.current) {
            return;
         }

         lastReportedSecond.current = second;
         onWatchProgress(second, duration);
      };

      const handleEnded = (duration = 0) => {
         if (hasEnded.current) {
            return;
         }

         hasEnded.current = true;

         if (onWatchProgress && duration > 0) {
            onWatchProgress(duration, duration);
         }

         onEnded?.();
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

            if (cancelled || !iframeRef.current || !window.playerjs?.Player) {
               return;
            }

            player = new window.playerjs.Player(iframeRef.current);

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
            player.off('timeupdate');
            player.off('ended');
            player.off('ready');
         }
      };
   }, [iframeSrc, onEnded, onWatchProgress]);

   return (
      <div className="bg-muted relative aspect-video w-full overflow-hidden rounded-lg">
         <iframe
            ref={iframeRef}
            src={iframeSrc}
            title={title}
            className="absolute inset-0 h-full w-full border-0"
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
            allowFullScreen
         />
      </div>
   );
};

export default BunnyEmbedPlayer;
