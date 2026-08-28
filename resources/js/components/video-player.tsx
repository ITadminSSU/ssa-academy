import BunnyEmbedPlayer from '@/components/bunny-embed-player';
import { useSecureVideoStream, type SecureVideoPlayback } from '@/hooks/use-secure-video-stream';
import { useVideoPlayerGuards } from '@/hooks/use-video-player-guards';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import { memo, useEffect, useMemo, useRef, useState } from 'react';

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
   lessonId?: number | string;
   initialPlayback?: SecureVideoPlayback | null;
}

type PlyrSource = {
   type: 'video' | 'audio';
   sources: Array<{
      src: string;
      type?: string;
      provider?: 'youtube' | 'vimeo';
   }>;
};

const PLYR_OPTIONS: Plyr.Options = {
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
};

function buildPlyrSource(playbackUrl: string, sourceKind: 'video' | 'audio', sourceMimeType: string): PlyrSource | null {
   if (!playbackUrl) {
      return null;
   }

   const isYouTube = playbackUrl.includes('youtube.com') || playbackUrl.includes('youtu.be');
   const isVimeo = playbackUrl.includes('vimeo.com');

   if (isYouTube) {
      const match = playbackUrl.match(/^.*(youtu.be\/|v\/|embed\/|watch\?v=|&v=)([^#&?]*).*/);
      const videoId = match && match[2].length === 11 ? match[2] : null;

      if (!videoId) {
         return null;
      }

      return {
         type: 'video',
         sources: [{ src: videoId, provider: 'youtube' }],
      };
   }

   if (isVimeo) {
      const vimeoId = playbackUrl.split('/').pop()?.split('?')[0];

      if (!vimeoId) {
         return null;
      }

      return {
         type: 'video',
         sources: [{ src: vimeoId, provider: 'vimeo' }],
      };
   }

   return {
      type: sourceKind,
      sources: [
         {
            src: playbackUrl,
            type: sourceMimeType,
         },
      ],
   };
}

function mountPlyrTarget(host: HTMLDivElement, processedSource: PlyrSource, protectDownload: boolean): HTMLElement {
   const source = processedSource.sources[0];
   const provider = source?.provider;

   if (provider === 'youtube' || provider === 'vimeo') {
      const embed = document.createElement('div');
      embed.setAttribute('data-plyr-provider', provider);
      embed.setAttribute('data-plyr-embed-id', source.src);
      host.appendChild(embed);
      return embed;
   }

   const video = document.createElement('video');
   video.playsInline = true;
   video.controls = true;

   if (protectDownload) {
      video.setAttribute('controlsList', 'nodownload noremoteplayback');
      video.setAttribute('disablePictureInPicture', 'true');
      video.oncontextmenu = (event) => event.preventDefault();
   }

   const sourceEl = document.createElement('source');
   sourceEl.src = source.src;

   if (source.type) {
      sourceEl.type = source.type;
   }

   video.appendChild(sourceEl);
   host.appendChild(video);

   return video;
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
   const containerRef = useRef<HTMLDivElement>(null);
   const hostRef = useRef<HTMLDivElement>(null);
   const onEndedRef = useRef(onEnded);
   const onWatchProgressRef = useRef(onWatchProgress);
   const lastReportedSecond = useRef(-1);
   const [playbackError, setPlaybackError] = useState<string | null>(null);

   onEndedRef.current = onEnded;
   onWatchProgressRef.current = onWatchProgress;

   const initialSrc = source.sources[0]?.src ?? '';
   const sourceKind = source.type;
   const sourceMimeType = source.sources[0]?.type ?? 'video/mp4';

   const { playbackUrl, embedUrl, delivery, loading, error } = useSecureVideoStream({
      lessonId,
      initialSrc,
      secureStream,
      initialPlayback,
   });

   useVideoPlayerGuards(containerRef, protectDownload);

   const processedSource = useMemo(
      () => buildPlyrSource(playbackUrl ?? '', sourceKind, sourceMimeType),
      [playbackUrl, sourceKind, sourceMimeType],
   );

   const sourceKey = processedSource
      ? `${processedSource.sources[0]?.provider ?? 'html5'}:${processedSource.sources[0]?.src}:${processedSource.sources[0]?.type ?? ''}`
      : '';

   const playbackFailedMessage =
      translate?.frontend?.video_playback_failed ||
      'This video file could not be loaded. Please ask your trainer to re-upload the lesson video.';

   useEffect(() => {
      lastReportedSecond.current = -1;
      setPlaybackError(null);
   }, [sourceKey]);

   // Plyr rewrites its own DOM. React only owns the empty host node; everything
   // inside is created and destroyed here so a source change never hits insertBefore.
   useEffect(() => {
      const host = hostRef.current;

      if (!host || !processedSource) {
         return;
      }

      host.replaceChildren();

      const target = mountPlyrTarget(host, processedSource, protectDownload);
      const player = new Plyr(target, PLYR_OPTIONS);

      const handleTimeUpdate = () => {
         const currentTime = Math.floor(player.currentTime ?? 0);
         const duration = player.duration ?? 0;

         if (currentTime !== lastReportedSecond.current && duration > 0) {
            lastReportedSecond.current = currentTime;
            onWatchProgressRef.current?.(currentTime, duration);
         }
      };

      const handleEnded = () => {
         const duration = player.duration > 0 ? player.duration : player.currentTime > 0 ? player.currentTime : 1;
         onWatchProgressRef.current?.(duration, duration);
         onEndedRef.current?.();
      };

      const handleError = () => {
         setPlaybackError(playbackFailedMessage);
      };

      player.on('timeupdate', handleTimeUpdate);
      player.on('ended', handleEnded);
      player.on('error', handleError);

      return () => {
         player.off('timeupdate', handleTimeUpdate);
         player.off('ended', handleEnded);
         player.off('error', handleError);

         try {
            player.destroy();
         } catch {
            // Plyr can throw if its node was already removed.
         }

         host.replaceChildren();
      };
   }, [sourceKey, processedSource, protectDownload, playbackFailedMessage]);

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
            key={embedUrl}
            embedUrl={embedUrl}
            onEnded={() => onEndedRef.current?.()}
            onWatchProgress={(currentTime, duration) => onWatchProgressRef.current?.(currentTime, duration)}
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
         onContextMenu={protectDownload ? (event) => event.preventDefault() : undefined}
      >
         <div ref={hostRef} className="w-full" />
      </div>
   );
};

export default memo(VideoPlayer);
