import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { VolumeX } from 'lucide-react';
import { useEffect, useMemo, useRef, useState, type SyntheticEvent } from 'react';
import Plyr, { APITypes } from 'plyr-react';
import 'plyr-react/plyr.css';

const DEFAULT_POSTER = '/assets/images/ssu-about/about-hero.png';

interface Props {
   videoUrl?: string | null;
   posterUrl?: string | null;
   className?: string;
}

const extractBunnyVideoId = (url: string): string | null => {
   const match = url.match(/mediadelivery\.net\/(?:embed|play)\/[^/]+\/([a-f0-9-]+)/i);
   return match?.[1] ?? null;
};

const toBunnyIframeHost = (url: string): string =>
   url.replace('://player.mediadelivery.net/', '://iframe.mediadelivery.net/');

const withSoundAutoplay = (url: string): string => {
   try {
      const parsed = new URL(toBunnyIframeHost(url), window.location.origin);
      parsed.searchParams.delete('playsinline');
      parsed.searchParams.delete('mute');
      parsed.searchParams.set('autoplay', 'true');
      parsed.searchParams.set('muted', 'false');
      parsed.searchParams.set('preload', 'true');
      parsed.searchParams.set('responsive', 'true');
      parsed.searchParams.set('playerjs', 'true');
      return parsed.toString();
   } catch {
      return url;
   }
};

const bunnyMp4FromCdn = (videoId: string, cdnHostname?: string): string | null => {
   const host = (cdnHostname || '').replace(/^https?:\/\//i, '').replace(/\/$/, '');
   if (!host || !videoId) {
      return null;
   }
   return `https://${host}/${videoId}/play_720p.mp4`;
};

function buildPlyrSource(videoUrl: string) {
   const isYouTube = videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be');
   const isVimeo = videoUrl.includes('vimeo.com');

   if (isYouTube) {
      const regExp = /^.*(youtu.be\/|v\/|embed\/|watch\?v=|&v=)([^#&?]*).*/;
      const match = videoUrl.match(regExp);
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
      const vimeoId = videoUrl.split('/').pop()?.split('?')[0];

      if (!vimeoId) {
         return null;
      }

      return {
         type: 'video' as const,
         sources: [{ src: vimeoId, provider: 'vimeo' as const }],
      };
   }

   return null;
}

const HeroVideoPlayer = ({ videoUrl, posterUrl, className }: Props) => {
   const { bunnyStream } = usePage<SharedData>().props;
   const playerRef = useRef<APITypes>(null);
   const fileVideoRef = useRef<HTMLVideoElement>(null);
   const iframeRef = useRef<HTMLIFrameElement>(null);
   const [started, setStarted] = useState(false);
   const [posterFailed, setPosterFailed] = useState(false);

   const poster = (!posterFailed && posterUrl?.trim()) || DEFAULT_POSTER;
   const rawUrl = videoUrl?.trim() || '';
   const hasVideo = Boolean(rawUrl);

   const bunnyId = useMemo(() => (rawUrl ? extractBunnyVideoId(rawUrl) : null), [rawUrl]);
   const bunnyMp4 = useMemo(
      () => (bunnyId ? bunnyMp4FromCdn(bunnyId, bunnyStream?.cdn_hostname) : null),
      [bunnyId, bunnyStream?.cdn_hostname],
   );

   const isYouTubeOrVimeo =
      rawUrl.includes('youtube.com') || rawUrl.includes('youtu.be') || rawUrl.includes('vimeo.com');
   const isBunnyEmbed = Boolean(bunnyId) && !bunnyMp4;
   const isNativeFile = Boolean(bunnyMp4) || (!bunnyId && !isYouTubeOrVimeo && !rawUrl.includes('mediadelivery.net'));
   const fileSrc = bunnyMp4 || (isNativeFile ? rawUrl : '');

   const plyrSource = useMemo(() => {
      if (!hasVideo || !isYouTubeOrVimeo) {
         return null;
      }
      return buildPlyrSource(rawUrl);
   }, [hasVideo, isYouTubeOrVimeo, rawUrl]);

   const plyrOptions = useMemo(
      () => ({
         ratio: '16:9',
         autoplay: false,
         muted: false,
         loop: { active: true },
         playsinline: true,
         controls: ['mute', 'volume', 'fullscreen'],
         hideControls: true,
         clickToPlay: true,
         poster,
         youtube: {
            noCookie: true,
            rel: 0,
            showinfo: 0,
            iv_load_policy: 3,
            modestbranding: 1,
            playsinline: 1,
            mute: 0,
            autoplay: 0,
         },
         vimeo: {
            byline: false,
            portrait: false,
            title: false,
            muted: false,
            autoplay: false,
         },
      }),
      [poster],
   );

   useEffect(() => {
      setStarted(false);
      setPosterFailed(false);
   }, [videoUrl, posterUrl]);

   const startWithSound = (event: SyntheticEvent) => {
      event.preventDefault();
      event.stopPropagation();

      if (fileSrc && fileVideoRef.current) {
         const video = fileVideoRef.current;
         video.muted = false;
         video.volume = 1;
         setStarted(true);
         void video.play().catch(() => undefined);
         return;
      }

      if (isBunnyEmbed && iframeRef.current) {
         iframeRef.current.src = withSoundAutoplay(rawUrl);
         setStarted(true);
         return;
      }

      const player = playerRef.current?.plyr;
      if (player) {
         player.muted = false;
         player.volume = 1;
         setStarted(true);
         const playPromise = player.play?.();
         if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => undefined);
         }
      }
   };

   if (!hasVideo) {
      return (
         <div className={cn('h-full w-full overflow-hidden bg-black/30', className)}>
            <img
               src={poster}
               alt="SSU Academy"
               className="aspect-video h-full w-full object-cover"
               onError={() => {
                  if (!posterFailed) {
                     setPosterFailed(true);
                  }
               }}
            />
         </div>
      );
   }

   return (
      <div className={cn('ssu-hero-video group relative h-full w-full overflow-hidden bg-black/20', className)}>
         {fileSrc ? (
            <video
               ref={fileVideoRef}
               className="h-full w-full object-cover"
               src={fileSrc}
               poster={poster}
               playsInline
               preload="metadata"
               loop
               controls={false}
            />
         ) : isBunnyEmbed ? (
            <iframe
               ref={iframeRef}
               src="about:blank"
               title="SSU Academy hero video"
               className={`h-full w-full border-0 ${!started ? 'pointer-events-none' : ''}`}
               allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen"
               allowFullScreen
            />
         ) : plyrSource ? (
            <Plyr ref={playerRef} options={plyrOptions} source={plyrSource} />
         ) : (
            <img
               src={poster}
               alt="SSU Academy"
               className="aspect-video h-full w-full object-cover"
               onError={() => {
                  if (!posterFailed) {
                     setPosterFailed(true);
                  }
               }}
            />
         )}

         {!started && (
            <button
               type="button"
               onPointerUp={startWithSound}
               onClick={startWithSound}
               className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-black/40 transition hover:bg-black/50"
               aria-label="Tap for sound"
            >
               <span className="flex h-16 w-16 items-center justify-center rounded-full bg-white text-[#0a1d37] shadow-lg sm:h-20 sm:w-20">
                  <VolumeX className="h-7 w-7 sm:h-8 sm:w-8" />
               </span>
               <span className="rounded-full bg-black/65 px-4 py-2 text-sm font-semibold tracking-wide text-white sm:px-5 sm:py-2.5 sm:text-base">
                  Tap for sound
               </span>
            </button>
         )}
      </div>
   );
};

export default HeroVideoPlayer;
