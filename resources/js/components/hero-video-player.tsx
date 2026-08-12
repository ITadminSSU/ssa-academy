import { cn } from '@/lib/utils';
import { VolumeX } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Plyr, { APITypes } from 'plyr-react';
import 'plyr-react/plyr.css';

const DEFAULT_POSTER = '/assets/images/ssu-about/about-hero.png';

interface Props {
   videoUrl?: string | null;
   posterUrl?: string | null;
   className?: string;
}

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

   return {
      type: 'video' as const,
      sources: [
         {
            src: videoUrl,
            type: 'video/mp4',
         },
      ],
   };
}

const HeroVideoPlayer = ({ videoUrl, posterUrl, className }: Props) => {
   const playerRef = useRef<APITypes>(null);
   const [isMuted, setIsMuted] = useState(true);
   const [isHovering, setIsHovering] = useState(false);
   const [loadFailed, setLoadFailed] = useState(false);
   const poster = posterUrl || DEFAULT_POSTER;
   const hasVideo = Boolean(videoUrl?.trim());

   const plyrSource = useMemo(() => {
      if (!hasVideo || !videoUrl || loadFailed) {
         return null;
      }

      return buildPlyrSource(videoUrl);
   }, [hasVideo, videoUrl, loadFailed]);

   const plyrOptions = useMemo(
      () => ({
         ratio: '16:9',
         autoplay: true,
         muted: true,
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
         },
         vimeo: {
            byline: false,
            portrait: false,
            title: false,
         },
      }),
      [poster],
   );

   useEffect(() => {
      setIsMuted(true);
      setLoadFailed(false);
      setIsHovering(false);
   }, [videoUrl]);

   useEffect(() => {
      if (!plyrSource) {
         return;
      }

      let frame = 0;
      let attempts = 0;
      let player: NonNullable<APITypes['plyr']> | null = null;

      const handleVolumeChange = () => {
         if (player) {
            setIsMuted(Boolean(player.muted));
         }
      };

      const handleError = () => {
         setLoadFailed(true);
      };

      const bindPlayer = () => {
         const instance = playerRef.current?.plyr;

         if (!instance || typeof instance.on !== 'function') {
            attempts += 1;

            if (attempts < 120) {
               frame = window.requestAnimationFrame(bindPlayer);
            }

            return;
         }

         player = instance;
         player.muted = true;
         player.volume = 0;
         player.on('volumechange', handleVolumeChange);
         player.on('error', handleError);

         const playPromise = player.play?.();

         if (playPromise && typeof playPromise.catch === 'function') {
            playPromise.catch(() => {
               // Autoplay may be blocked until user interaction — poster remains visible.
            });
         }
      };

      bindPlayer();

      return () => {
         if (frame) {
            window.cancelAnimationFrame(frame);
         }

         if (player && typeof player.off === 'function') {
            player.off('volumechange', handleVolumeChange);
            player.off('error', handleError);
         }
      };
   }, [plyrSource]);

   const handleUnmute = () => {
      const player = playerRef.current?.plyr;

      if (!player) {
         return;
      }

      player.muted = false;
      player.volume = 1;
      setIsMuted(false);

      const playPromise = player.play?.();

      if (playPromise && typeof playPromise.catch === 'function') {
         playPromise.catch(() => undefined);
      }
   };

   if (!hasVideo || !plyrSource) {
      return (
         <div className={cn('overflow-hidden rounded-2xl border border-white/10 bg-white/5 shadow-sm', className)}>
            <img src={poster} alt="SSU Academy" className="aspect-video w-full object-cover" />
         </div>
      );
   }

   return (
      <div
         className={cn(
            'ssu-hero-video group relative overflow-hidden rounded-2xl border border-white/10 bg-black/20 shadow-sm',
            isHovering && 'ssu-hero-video--hover',
            className,
         )}
         onMouseEnter={() => setIsHovering(true)}
         onMouseLeave={() => setIsHovering(false)}
      >
         <Plyr ref={playerRef} options={plyrOptions} source={plyrSource} />

         {/* Visible while muted so users can unmute; removed after sound is on */}
         {isMuted && (
            <button
               type="button"
               onClick={handleUnmute}
               className="absolute right-4 bottom-4 z-20 inline-flex items-center gap-2 rounded-full border border-white/20 bg-black/70 px-4 py-2 text-sm font-medium text-white shadow-lg backdrop-blur-sm transition hover:scale-[1.02] hover:bg-black/80"
               aria-label="Unmute video"
            >
               <VolumeX className="h-4 w-4" />
               Tap for sound
            </button>
         )}
      </div>
   );
};

export default HeroVideoPlayer;
