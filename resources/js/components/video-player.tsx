import { useSecureVideoStream, type SecureVideoPlayback } from '@/hooks/use-secure-video-stream';
import { useVideoPlayerGuards } from '@/hooks/use-video-player-guards';
import BunnyEmbedPlayer from '@/components/bunny-embed-player';
import { cn } from '@/lib/utils';
import { useEffect, useMemo, useRef, useState } from 'react';
import Plyr, { APITypes } from 'plyr-react';
import 'plyr-react/plyr.css';

interface Props {
   source: {
      type: 'video' | 'audio';
      sources: Array<{
         src: string;
         type?: string;
         provider?: 'youtube' | 'vimeo' | 'html5';
      }>;
   };
   translate?: any;
   onEnded?: () => void;
   onWatchProgress?: (currentTime: number, duration: number) => void;
   protectDownload?: boolean;
   secureStream?: boolean;
   lessonId?: number;
   initialPlayback?: SecureVideoPlayback | null;
}

const VideoPlayer = ({
   source,
   translate,
   onEnded,
   onWatchProgress,
   protectDownload = false,
   secureStream = false,
   lessonId,
   initialPlayback = null,
}: Props) => {
   const playerRef = useRef<APITypes>(null);
   const containerRef = useRef<HTMLDivElement>(null);
   const initialSrc = source.sources[0]?.src ?? '';
   const [hasStarted, setHasStarted] = useState(false);
   const [playbackError, setPlaybackError] = useState<string | null>(null);
   const lastReportedSecond = useRef(-1);

   const { playbackUrl, embedUrl, delivery, loading, error } = useSecureVideoStream({
      lessonId,
      initialSrc,
      secureStream,
      initialPlayback,
   });

   useVideoPlayerGuards(containerRef, protectDownload);

   const plyrOptions = useMemo(
      () => ({
         ratio: '16:9',
         controls: ['play-large', 'play', 'progress', 'current-time', 'duration', 'mute', 'volume', 'settings', 'fullscreen'],
         settings: ['quality', 'speed'],
         speed: { selected: 1, options: [0.5, 0.75, 1, 1.25, 1.5, 2] },
         resetOnEnd: true,
         keyboard: { focused: true, global: false },
         displayDuration: true,
         tooltips: { controls: true, seek: true },
         i18n: {
            restart: 'Restart',
            rewind: 'Rewind {seektime}s',
            play: 'Play',
            pause: 'Pause',
            forward: 'Forward {seektime}s',
            played: 'Played',
            buffered: 'Buffered',
            currentTime: 'Current time',
            duration: 'Duration',
            volume: 'Volume',
            toggleMute: 'Toggle Mute',
            toggleCaptions: 'Toggle Captions',
            toggleFullscreen: 'Toggle Fullscreen',
         },
      }),
      [],
   );

   // Read the source as primitives so the memo below stays stable across parent
   // re-renders. The parent recreates the `source` object on every render (it
   // updates once a second from watch-progress), and depending on that object
   // identity would re-run this memo, hand Plyr a brand-new source every second,
   // and force plyr-react to tear down / re-create the player mid-playback —
   // which crashes with an insertBefore DOM error (white screen).
   const sourceKind = source.type;
   const sourceMimeType = source.sources[0]?.type ?? 'video/mp4';

   const processedSource = useMemo(() => {
      const videoSrc = playbackUrl ?? '';

      if (!videoSrc) {
         return null;
      }

      const isYouTube = videoSrc.includes('youtube.com') || videoSrc.includes('youtu.be');
      const isVimeo = videoSrc.includes('vimeo.com');

      if (isYouTube) {
         const regExp = /^.*(youtu.be\/|v\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
         const match = videoSrc.match(regExp);
         const videoId = match && match[2].length === 11 ? match[2] : null;

         if (!videoId) {
            return null;
         }

         return {
            type: 'video' as const,
            sources: [{ src: videoId, provider: 'youtube' as const }],
         };
      }

      if (isVimeo) {
         const vimeoId = videoSrc.split('/').pop()?.split('?')[0];
         if (!vimeoId) {
            return null;
         }

         return {
            type: 'video' as const,
            sources: [{ src: vimeoId, provider: 'vimeo' as const }],
         };
      }

      return {
         type: sourceKind,
         sources: [
            {
               src: videoSrc,
               type: sourceMimeType,
            },
         ],
      };
   }, [playbackUrl, sourceKind, sourceMimeType]);

   useEffect(() => {
      lastReportedSecond.current = -1;
      setHasStarted(false);
      setPlaybackError(null);
   }, [playbackUrl, processedSource]);

   // Bind media events on the actual Plyr instance. Plyr's `listeners` option
   // only covers control buttons, not media events like `timeupdate`/`ended`,
   // so watch-progress and auto-complete must be wired through `player.on()`.
   //
   // plyr-react creates the underlying Plyr instance asynchronously *after* the
   // source is set, so on the first render `playerRef.current.plyr` may not have
   // its media methods yet. We poll briefly until the instance is ready, then
   // bind, so the `ended` (auto-complete) listener is never missed.
   useEffect(() => {
      if (!processedSource) {
         return;
      }

      type PlyrInstance = NonNullable<APITypes['plyr']>;
      let player: PlyrInstance | null = null;
      let frame = 0;
      let attempts = 0;

      const handlePlay = () => setHasStarted(true);

      const handleTimeUpdate = () => {
         if (!player || !onWatchProgress) {
            return;
         }

         const currentTime = Math.floor(player.currentTime ?? 0);
         const duration = player.duration ?? 0;

         if (currentTime !== lastReportedSecond.current && duration > 0) {
            lastReportedSecond.current = currentTime;
            onWatchProgress(currentTime, duration);
         }
      };

      const handleEnded = () => {
         if (!player) {
            return;
         }

         const duration = player.duration > 0 ? player.duration : player.currentTime > 0 ? player.currentTime : 1;

         onWatchProgress?.(duration, duration);
         onEnded?.();
      };

      const bind = () => {
         const instance = playerRef.current?.plyr as PlyrInstance | undefined;

         // Wait until the real Plyr instance (with media event support) exists.
         if (!instance || typeof instance.on !== 'function') {
            attempts += 1;

            if (attempts < 120) {
               frame = window.requestAnimationFrame(bind);
            }

            return;
         }

         player = instance;
         player.on('play', handlePlay);
         player.on('playing', handlePlay);
         player.on('timeupdate', handleTimeUpdate);
         player.on('ended', handleEnded);
      };

      bind();

      return () => {
         if (frame) {
            window.cancelAnimationFrame(frame);
         }

         if (player && typeof player.off === 'function') {
            player.off('play', handlePlay);
            player.off('playing', handlePlay);
            player.off('timeupdate', handleTimeUpdate);
            player.off('ended', handleEnded);
         }
      };
   }, [onEnded, onWatchProgress, processedSource]);

   useEffect(() => {
      if (!protectDownload || !containerRef.current) {
         return;
      }

      const applyVideoGuards = () => {
         const video = containerRef.current?.querySelector('video');

         if (!video) {
            return;
         }

         video.setAttribute('controlsList', 'nodownload noremoteplayback');
         video.setAttribute('disablePictureInPicture', 'true');
         video.setAttribute('playsinline', 'true');
         video.removeAttribute('srcset');
         video.oncontextmenu = (event) => event.preventDefault();
      };

      applyVideoGuards();

      const observer = new MutationObserver(applyVideoGuards);
      observer.observe(containerRef.current, { childList: true, subtree: true });

      return () => observer.disconnect();
   }, [protectDownload, processedSource]);

   useEffect(() => {
      if (!processedSource || !containerRef.current) {
         return;
      }

      const handleMediaError = () => {
         setPlaybackError(
            translate?.frontend?.video_playback_failed ||
               'This video file could not be loaded. Please ask your trainer to re-upload the lesson video.',
         );
      };

      const bindMediaError = () => {
         const video = containerRef.current?.querySelector('video');

         if (!video) {
            return false;
         }

         video.addEventListener('error', handleMediaError);
         return true;
      };

      if (!bindMediaError()) {
         const observer = new MutationObserver(() => {
            if (bindMediaError()) {
               observer.disconnect();
            }
         });

         observer.observe(containerRef.current, { childList: true, subtree: true });

         return () => observer.disconnect();
      }

      return () => {
         const video = containerRef.current?.querySelector('video');
         video?.removeEventListener('error', handleMediaError);
      };
   }, [processedSource, translate]);

   if (loading) {
      return (
         <div className="bg-muted flex min-h-[40vh] items-center justify-center p-8">
            <p>{translate?.frontend?.loading_video || 'Loading secure lesson video...'}</p>
         </div>
      );
   }

   if (error || playbackError) {
      return (
         <div className="bg-muted flex min-h-[40vh] items-center justify-center p-8">
            <p>{error || playbackError}</p>
         </div>
      );
   }

   if (delivery === 'bunny_embed' && embedUrl) {
      return (
         <BunnyEmbedPlayer
            embedUrl={embedUrl}
            onEnded={onEnded}
            onWatchProgress={onWatchProgress}
         />
      );
   }

   if (!processedSource) {
      return (
         <div className="bg-muted flex min-h-[40vh] items-center justify-center p-8">
            <p>{translate?.frontend?.no_video_available || 'No video available'}</p>
         </div>
      );
   }

   return (
      <div
         ref={containerRef}
         className="bg-muted relative w-full select-none [&_video]:pointer-events-auto"
         style={{ WebkitUserSelect: 'none', userSelect: 'none' } as React.CSSProperties}
         onContextMenu={protectDownload ? (e) => e.preventDefault() : undefined}
      >
         {/* Empty placeholder kept always mounted next to Plyr. Conditionally
             mounting/unmounting a node next to Plyr's externally managed DOM
             makes React's insertBefore fail and white-screens the page, so we
             keep this node present but render nothing inside it. */}
         <div className="pointer-events-none absolute inset-0 z-10" aria-hidden="true" />
         <Plyr ref={playerRef} options={plyrOptions} source={processedSource} />
      </div>
   );
};

export default VideoPlayer;
