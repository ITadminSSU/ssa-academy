import { preloadPlayerJs, type PlayerJsInstance } from '@/lib/bunny-player-js';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { Pause, Play } from 'lucide-react';
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
   const bunnyPlayerRef = useRef<PlayerJsInstance | null>(null);
   const lastToggleAt = useRef(0);
   const [started, setStarted] = useState(false);
   const [playing, setPlaying] = useState(false);
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
      setPlaying(false);
      setPosterFailed(false);
      bunnyPlayerRef.current = null;
   }, [videoUrl, posterUrl]);

   useEffect(() => {
      const video = fileVideoRef.current;

      if (!video) {
         return;
      }

      const onPlay = () => setPlaying(true);
      const onPause = () => setPlaying(false);

      video.addEventListener('play', onPlay);
      video.addEventListener('pause', onPause);

      return () => {
         video.removeEventListener('play', onPlay);
         video.removeEventListener('pause', onPause);
      };
   }, [fileSrc]);

   useEffect(() => {
      if (!started || !isBunnyEmbed || !iframeRef.current) {
         return;
      }

      const iframe = iframeRef.current;
      let cancelled = false;

      const bind = async () => {
         try {
            await preloadPlayerJs();

            if (cancelled || !iframe.isConnected || !window.playerjs?.Player) {
               return;
            }

            const player = new window.playerjs.Player(iframe);
            bunnyPlayerRef.current = player;
            player.on('ready', () => {
               if (cancelled) {
                  return;
               }

               player.on('play', () => setPlaying(true));
               player.on('pause', () => setPlaying(false));
            });
         } catch {
            // Native overlay still toggles pause/play if player.js is unavailable.
         }
      };

      if (iframe.src && iframe.src !== 'about:blank') {
         void bind();
      }

      iframe.addEventListener('load', bind);

      return () => {
         cancelled = true;
         iframe.removeEventListener('load', bind);
      };
   }, [started, isBunnyEmbed, rawUrl]);

   const pausePlayback = () => {
      fileVideoRef.current?.pause();
      bunnyPlayerRef.current?.pause?.();
      playerRef.current?.plyr?.pause?.();
      setPlaying(false);
   };

   const togglePlayback = (event: SyntheticEvent) => {
      event.preventDefault();
      event.stopPropagation();

      const now = Date.now();
      if (now - lastToggleAt.current < 350) {
         return;
      }
      lastToggleAt.current = now;

      if (playing) {
         pausePlayback();
         return;
      }

      if (fileSrc && fileVideoRef.current) {
         const video = fileVideoRef.current;
         video.muted = false;
         video.volume = 1;
         setStarted(true);
         void video.play().catch(() => undefined);
         return;
      }

      if (isBunnyEmbed && iframeRef.current) {
         if (!started) {
            iframeRef.current.src = withSoundAutoplay(rawUrl);
            setStarted(true);
            setPlaying(true);
            return;
         }

         bunnyPlayerRef.current?.unmute?.();
         bunnyPlayerRef.current?.play?.();
         setPlaying(true);
         return;
      }

      const player = playerRef.current?.plyr;
      if (player) {
         player.muted = false;
         player.volume = 1;
         setStarted(true);
         setPlaying(true);
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
               className="pointer-events-none h-full w-full border-0"
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

         <button
            type="button"
            onPointerUp={togglePlayback}
            onClick={togglePlayback}
            className={cn(
               'absolute inset-0 z-20 flex items-center justify-center transition',
               playing ? 'bg-transparent hover:bg-black/20' : 'bg-black/25 hover:bg-black/35',
            )}
            aria-label={playing ? 'Pause welcome video' : 'Play welcome video'}
         >
            <span
               className={cn(
                  'flex h-[4.25rem] w-[4.25rem] items-center justify-center rounded-full bg-black/55 text-white shadow-[0_8px_24px_rgba(0,0,0,0.35)] sm:h-20 sm:w-20',
                  playing && 'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100',
               )}
            >
               {playing ? (
                  <Pause className="h-8 w-8 sm:h-9 sm:w-9" />
               ) : (
                  <Play className="ml-1 h-8 w-8 fill-white sm:h-9 sm:w-9" />
               )}
            </span>
         </button>
      </div>
   );
};

export default HeroVideoPlayer;
