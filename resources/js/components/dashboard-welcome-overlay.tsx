import ButtonGradientPrimary from '@/components/button-gradient-primary';
import { Button } from '@/components/ui/button';
import { preloadPlayerJs, type PlayerJsInstance } from '@/lib/bunny-player-js';
import { Link, router } from '@inertiajs/react';
import { Volume2, VolumeX, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export interface DashboardWelcomeOverlayContent {
   campaign_id?: number;
   version: string;
   headline: string;
   body: string;
   cta_label: string;
   cta_url: string;
   poster_url: string;
   video_type?: 'none' | 'file' | 'embed';
   video_url?: string;
   autoplay_muted?: boolean;
   show_frequency?: 'until_dismissed' | 'every_home_visit';
}

interface Props {
   overlay: DashboardWelcomeOverlayContent;
}

/** Prefer iframe.mediadelivery.net — Bunny's Player.js docs use this host. */
const toBunnyEmbedHost = (url: string): string =>
   url.replace('://player.mediadelivery.net/', '://iframe.mediadelivery.net/');

/** Start muted so autoplay is allowed; we unmute only after a real user tap. */
const withMutedAutoplay = (url: string): string => {
   try {
      const parsed = new URL(toBunnyEmbedHost(url), window.location.origin);
      parsed.searchParams.set('autoplay', 'true');
      parsed.searchParams.set('muted', 'true');
      parsed.searchParams.set('preload', 'true');
      parsed.searchParams.set('responsive', 'true');
      parsed.searchParams.set('playerjs', 'true');
      // Unique src helps Player.js when multiple embeds exist.
      parsed.searchParams.set('ssu', String(Date.now()));
      return parsed.toString();
   } catch {
      return url;
   }
};

const DashboardWelcomeOverlay = ({ overlay }: Props) => {
   const [open, setOpen] = useState(true);
   const [muted, setMuted] = useState(true);
   const [showUnmutePrompt, setShowUnmutePrompt] = useState(true);
   const [playerReady, setPlayerReady] = useState(false);
   const videoRef = useRef<HTMLVideoElement>(null);
   const iframeRef = useRef<HTMLIFrameElement>(null);
   const playerRef = useRef<PlayerJsInstance | null>(null);

   const videoType = overlay.video_type ?? 'none';
   const videoUrl = overlay.video_url?.trim() || '';
   const hasVideo = videoType !== 'none' && videoUrl !== '';
   const [embedSrc, setEmbedSrc] = useState(() => (videoUrl ? withMutedAutoplay(videoUrl) : ''));

   useEffect(() => {
      if (!videoUrl) {
         setEmbedSrc('');
         return;
      }

      setEmbedSrc(withMutedAutoplay(videoUrl));
      setMuted(true);
      setShowUnmutePrompt(true);
      setPlayerReady(false);
      playerRef.current = null;
   }, [videoUrl]);

   useEffect(() => {
      if (!open) {
         return;
      }

      const previous = document.body.style.overflow;
      document.body.style.overflow = 'hidden';

      return () => {
         document.body.style.overflow = previous;
      };
   }, [open]);

   useEffect(() => {
      if (!hasVideo || videoType !== 'file' || !videoRef.current) {
         return;
      }

      const video = videoRef.current;
      video.muted = true;
      void video.play().catch(() => undefined);
   }, [hasVideo, videoType, videoUrl]);

   useEffect(() => {
      if (!hasVideo || videoType !== 'embed' || !embedSrc) {
         return;
      }

      let cancelled = false;

      const bindPlayer = async () => {
         try {
            await preloadPlayerJs();
         } catch {
            return;
         }

         if (cancelled || !iframeRef.current || !window.playerjs?.Player) {
            return;
         }

         const player = new window.playerjs.Player(iframeRef.current);
         playerRef.current = player;

         player.on('ready', () => {
            if (cancelled) {
               return;
            }
            setPlayerReady(true);
            // Keep muted until the user taps — only ensure playback continues.
            try {
               player.mute?.();
               player.play?.();
            } catch {
               // Ignore.
            }
         });
      };

      void bindPlayer();

      return () => {
         cancelled = true;
         playerRef.current = null;
         setPlayerReady(false);
      };
   }, [hasVideo, videoType, embedSrc]);

   if (!open) {
      return null;
   }

   const dismiss = () => {
      setOpen(false);

      router.post(
         route('student.dashboard-welcome-overlay.dismiss'),
         {
            version: overlay.version,
            campaign_id: overlay.campaign_id ?? null,
         },
         { preserveScroll: true, preserveState: true },
      );
   };

   /**
    * Must stay synchronous inside the click handler.
    * Reloading the iframe (React setState src) loses the user gesture and Chrome keeps it muted.
    */
   const unmute = () => {
      if (videoType === 'file' && videoRef.current) {
         const video = videoRef.current;
         video.muted = false;
         video.volume = 1;
         void video.play().catch(() => undefined);
         setMuted(false);
         setShowUnmutePrompt(false);
         return;
      }

      if (videoType !== 'embed') {
         return;
      }

      const player = playerRef.current;

      try {
         player?.unmute?.();
         player?.setVolume?.(1);
         player?.play?.();
      } catch {
         // Fall through — UI still updates; user can retry.
      }

      // Confirm mute state when Player.js supports getMuted.
      const maybeGetMuted = player as PlayerJsInstance & {
         getMuted?: (cb: (isMuted: boolean) => void) => void;
      };

      if (typeof maybeGetMuted?.getMuted === 'function') {
         maybeGetMuted.getMuted((isMuted) => {
            if (!isMuted) {
               setMuted(false);
               setShowUnmutePrompt(false);
               return;
            }
            // Still muted — keep prompt visible so they can tap again once ready.
            setShowUnmutePrompt(true);
            setMuted(true);
         });
      } else {
         setMuted(false);
         setShowUnmutePrompt(false);
      }

      // If player was not ready yet, keep trying briefly while gesture context may still apply.
      if (!playerReady) {
         window.setTimeout(() => {
            try {
               playerRef.current?.unmute?.();
               playerRef.current?.setVolume?.(1);
               playerRef.current?.play?.();
            } catch {
               // Ignore.
            }
         }, 200);
         window.setTimeout(() => {
            try {
               playerRef.current?.unmute?.();
               playerRef.current?.setVolume?.(1);
               playerRef.current?.play?.();
               setMuted(false);
               setShowUnmutePrompt(false);
            } catch {
               // Ignore.
            }
         }, 600);
      } else {
         setMuted(false);
         setShowUnmutePrompt(false);
      }
   };

   const remute = () => {
      setMuted(true);
      setShowUnmutePrompt(true);

      if (videoType === 'file' && videoRef.current) {
         videoRef.current.muted = true;
         return;
      }

      try {
         playerRef.current?.mute?.();
      } catch {
         // Ignore.
      }
   };

   const ctaIsExternal =
      /^https?:/i.test(overlay.cta_url) || overlay.cta_url.startsWith('mailto:') || overlay.cta_url.startsWith('tel:');

   return (
      <div
         className="fixed inset-0 z-[80] flex items-center justify-center p-4 sm:p-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="dashboard-welcome-overlay-title"
      >
         <button
            type="button"
            className="absolute inset-0 bg-[#021433]/72 backdrop-blur-[2px]"
            aria-label="Close welcome message"
            onClick={dismiss}
         />

         <div className="relative z-10 w-full max-w-5xl overflow-hidden rounded-2xl border border-white/15 bg-[#0a1d37] text-white shadow-2xl">
            <button
               type="button"
               onClick={dismiss}
               className="absolute top-3 right-3 z-30 rounded-full bg-black/35 p-2 text-white/90 transition hover:bg-black/50"
               aria-label="Close"
            >
               <X className="h-4 w-4" />
            </button>

            {hasVideo ? (
               <div className="relative aspect-video w-full overflow-hidden bg-black sm:min-h-[28rem]">
                  {videoType === 'file' ? (
                     <video
                        ref={videoRef}
                        className="h-full w-full object-cover"
                        src={videoUrl}
                        poster={overlay.poster_url || undefined}
                        playsInline
                        muted
                        autoPlay
                        loop
                        controls={false}
                     />
                  ) : (
                     <iframe
                        key={embedSrc}
                        ref={iframeRef}
                        src={embedSrc || videoUrl}
                        title={overlay.headline || 'Welcome video'}
                        className={`h-full w-full border-0 ${showUnmutePrompt ? 'pointer-events-none' : ''}`}
                        allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowFullScreen
                     />
                  )}

                  {showUnmutePrompt ? (
                     <button
                        type="button"
                        onClick={unmute}
                        className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-black/40 transition hover:bg-black/50"
                     >
                        <span className="flex h-20 w-20 items-center justify-center rounded-full bg-white text-[#0a1d37] shadow-lg">
                           <VolumeX className="h-8 w-8" />
                        </span>
                        <span className="rounded-full bg-black/60 px-5 py-2.5 text-base font-semibold tracking-wide text-white">
                           {playerReady || videoType === 'file' ? 'Tap for sound' : 'Loading… tap for sound'}
                        </span>
                     </button>
                  ) : (
                     <button
                        type="button"
                        onClick={remute}
                        className="absolute bottom-3 left-3 z-20 flex items-center gap-2 rounded-full bg-black/55 px-3 py-2 text-xs font-medium text-white backdrop-blur-sm"
                     >
                        <Volume2 className="h-4 w-4" />
                        Mute
                     </button>
                  )}
               </div>
            ) : overlay.poster_url ? (
               <div className="aspect-video w-full overflow-hidden bg-black/40 sm:min-h-[28rem]">
                  <img src={overlay.poster_url} alt="" className="h-full w-full object-cover" />
               </div>
            ) : null}

            <div className="space-y-4 p-6 sm:p-8">
               {overlay.headline ? (
                  <h2 id="dashboard-welcome-overlay-title" className="text-2xl font-bold tracking-tight sm:text-3xl">
                     {overlay.headline}
                  </h2>
               ) : (
                  <h2 id="dashboard-welcome-overlay-title" className="sr-only">
                     Welcome
                  </h2>
               )}

               {overlay.body ? <p className="text-sm leading-relaxed text-white/85 sm:text-base">{overlay.body}</p> : null}

               <div className="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center">
                  {overlay.cta_label ? (
                     ctaIsExternal ? (
                        <ButtonGradientPrimary asChild shadow={false} className="h-11 min-w-[180px] px-6 text-base font-semibold">
                           <a href={overlay.cta_url} target="_blank" rel="noopener noreferrer" onClick={dismiss}>
                              {overlay.cta_label}
                           </a>
                        </ButtonGradientPrimary>
                     ) : (
                        <ButtonGradientPrimary asChild shadow={false} className="h-11 min-w-[180px] px-6 text-base font-semibold">
                           <Link href={overlay.cta_url} onClick={dismiss}>
                              {overlay.cta_label}
                           </Link>
                        </ButtonGradientPrimary>
                     )
                  ) : null}

                  <Button
                     type="button"
                     variant="ghost"
                     className="h-11 text-white/80 hover:bg-white/10 hover:text-white"
                     onClick={dismiss}
                  >
                     Continue to dashboard
                  </Button>
               </div>
            </div>
         </div>
      </div>
   );
};

export default DashboardWelcomeOverlay;
