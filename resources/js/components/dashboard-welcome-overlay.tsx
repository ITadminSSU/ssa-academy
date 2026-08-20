import ButtonGradientPrimary from '@/components/button-gradient-primary';
import { Button } from '@/components/ui/button';
import { preloadPlayerJs } from '@/lib/bunny-player-js';
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

/** Flip Bunny/YouTube-style mute query flags for embed playback after a user tap. */
const withEmbedMuteState = (url: string, muted: boolean): string => {
   try {
      const parsed = new URL(url, window.location.origin);
      if (muted) {
         parsed.searchParams.set('muted', 'true');
         parsed.searchParams.set('mute', '1');
         parsed.searchParams.set('autoplay', 'true');
      } else {
         parsed.searchParams.set('muted', 'false');
         parsed.searchParams.set('mute', '0');
         parsed.searchParams.set('autoplay', 'true');
      }
      parsed.searchParams.set('preload', 'true');
      parsed.searchParams.set('playerjs', 'true');
      return parsed.toString();
   } catch {
      return url;
   }
};

const DashboardWelcomeOverlay = ({ overlay }: Props) => {
   const [open, setOpen] = useState(true);
   // Browsers block unmuted autoplay — always start muted and require one tap for sound.
   const [muted, setMuted] = useState(true);
   const [showUnmutePrompt, setShowUnmutePrompt] = useState(true);
   const videoRef = useRef<HTMLVideoElement>(null);
   const iframeRef = useRef<HTMLIFrameElement>(null);

   const videoType = overlay.video_type ?? 'none';
   const videoUrl = overlay.video_url?.trim() || '';
   const hasVideo = videoType !== 'none' && videoUrl !== '';
   const [embedSrc, setEmbedSrc] = useState(() => (videoUrl ? withEmbedMuteState(videoUrl, true) : ''));

   useEffect(() => {
      if (!videoUrl) {
         setEmbedSrc('');
         return;
      }
      setEmbedSrc(withEmbedMuteState(videoUrl, true));
      setMuted(true);
      setShowUnmutePrompt(true);
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
      video.muted = muted;
      void video.play().catch(() => {
         // Autoplay may still be blocked; user can tap for sound.
      });
   }, [hasVideo, videoType, muted, videoUrl]);

   useEffect(() => {
      if (!hasVideo || videoType !== 'embed') {
         return;
      }

      void preloadPlayerJs();
   }, [hasVideo, videoType]);

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

   const unmuteViaPlayerJs = async () => {
      await preloadPlayerJs().catch(() => undefined);

      const iframe = iframeRef.current;
      if (!iframe || !window.playerjs?.Player) {
         return false;
      }

      return await new Promise<boolean>((resolve) => {
         try {
            const player = new window.playerjs.Player(iframe);
            let settled = false;

            const finish = (ok: boolean) => {
               if (settled) {
                  return;
               }
               settled = true;
               resolve(ok);
            };

            const applySound = () => {
               try {
                  player.unmute?.();
                  player.setVolume?.(1);
                  player.play?.();
                  finish(true);
               } catch {
                  finish(false);
               }
            };

            player.on('ready', applySound);
            // Some embeds are already ready before we bind.
            window.setTimeout(applySound, 150);
            window.setTimeout(() => finish(false), 1500);
         } catch {
            resolve(false);
         }
      });
   };

   const unmute = async () => {
      if (videoType === 'file' && videoRef.current) {
         videoRef.current.muted = false;
         videoRef.current.volume = 1;
         setMuted(false);
         setShowUnmutePrompt(false);
         try {
            await videoRef.current.play();
         } catch {
            // Ignore — prompt can stay if play fails.
         }
         return;
      }

      if (videoType === 'embed' && videoUrl) {
         // Reload embed unmuted inside the user-gesture click — most reliable for Bunny.
         const unmutedUrl = withEmbedMuteState(videoUrl, false);
         setEmbedSrc(unmutedUrl);
         setMuted(false);
         setShowUnmutePrompt(false);

         window.setTimeout(() => {
            void unmuteViaPlayerJs();
         }, 400);
      }
   };

   const remute = () => {
      setMuted(true);
      setShowUnmutePrompt(true);

      if (videoType === 'file' && videoRef.current) {
         videoRef.current.muted = true;
      }

      if (videoType === 'embed' && videoUrl) {
         setEmbedSrc(withEmbedMuteState(videoUrl, true));
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
                        muted={muted}
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
                        onClick={() => void unmute()}
                        className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-black/40 transition hover:bg-black/50"
                     >
                        <span className="flex h-20 w-20 items-center justify-center rounded-full bg-white text-[#0a1d37] shadow-lg">
                           <VolumeX className="h-8 w-8" />
                        </span>
                        <span className="rounded-full bg-black/60 px-5 py-2.5 text-base font-semibold tracking-wide text-white">
                           Tap for sound
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
