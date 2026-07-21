import { useEffect, useRef } from 'react';

interface BunnyEmbedPlayerProps {
   embedUrl: string;
   title?: string;
   onEnded?: () => void;
   onWatchProgress?: (currentTime: number, duration: number) => void;
}

const BunnyEmbedPlayer = ({ embedUrl, title = 'Lesson video', onEnded, onWatchProgress }: BunnyEmbedPlayerProps) => {
   const lastReportedSecond = useRef(-1);

   useEffect(() => {
      lastReportedSecond.current = -1;

      const handleMessage = (event: MessageEvent) => {
         if (typeof event.data !== 'object' || event.data === null) {
            return;
         }

         const payload = event.data as { event?: string; currentTime?: number; duration?: number };

         if (payload.event === 'timeupdate' && onWatchProgress) {
            const currentTime = Math.floor(payload.currentTime ?? 0);
            const duration = payload.duration ?? 0;

            if (duration > 0 && currentTime !== lastReportedSecond.current) {
               lastReportedSecond.current = currentTime;
               onWatchProgress(currentTime, duration);
            }
         }

         if (payload.event === 'ended') {
            if (onWatchProgress && (payload.duration ?? 0) > 0) {
               onWatchProgress(payload.duration as number, payload.duration as number);
            }

            onEnded?.();
         }
      };

      window.addEventListener('message', handleMessage);

      return () => window.removeEventListener('message', handleMessage);
   }, [embedUrl, onEnded, onWatchProgress]);

   return (
      <div className="bg-muted relative aspect-video w-full overflow-hidden rounded-lg">
         <iframe
            src={embedUrl}
            title={title}
            className="absolute inset-0 h-full w-full border-0"
            loading="lazy"
            allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture;"
            allowFullScreen
         />
      </div>
   );
};

export default BunnyEmbedPlayer;
