import ButtonGradientPrimary from '@/components/button-gradient-primary';
import { Button } from '@/components/ui/button';
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

const toBunnyIframeHost = (url: string): string =>
   url.replace('://player.mediadelivery.net/', '://iframe.mediadelivery.net/');

/** Unmuted autoplay URL — only assigned to the iframe inside a user tap. */
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

const DashboardWelcomeOverlay = ({ overlay }: Props) => {
   const [open, setOpen] = useState(true);
   const [started, setStarted] = useState(false);
   const [muted, setMuted] = useState(false);
   const videoRef = useRef<HTMLVideoElement>(null);
   const iframeRef = useRef<HTMLIFrameElement>(null);

   const videoType = overlay.video_type ?? 'none';
   const videoUrl = overlay.video_url?.trim() || '';
   const hasVideo = videoType !== 'none' && videoUrl !== '';
   const isFile = videoType === 'file';

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
    * Start playback WITH sound inside the user gesture.
    * Do not pre-load a muted iframe — browsers block later unmute across origins.
    */
   const startWithSound = () => {
      if (!hasVideo) {
         return;
      }

      if (isFile && videoRef.current) {
         const video = videoRef.current;
         video.muted = false;
         video.volume = 1;
         setMuted(false);
         setStarted(true);
         void video.play().catch(() => undefined);
         return;
      }

      // Embed: set iframe src synchronously in the click handler (preserves user gesture).
      const soundUrl = withSoundAutoplay(videoUrl);
      const iframe = iframeRef.current;
      if (iframe) {
         iframe.src = soundUrl;
      }
      setStarted(true);
      setMuted(false);
   };

   const toggleMuteFile = () => {
      if (!videoRef.current) {
         return;
      }

      const next = !videoRef.current.muted;
      videoRef.current.muted = next;
      setMuted(next);
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
                  {isFile ? (
                     <video
                        ref={videoRef}
                        className="h-full w-full object-cover"
                        src={videoUrl}
                        poster={overlay.poster_url || undefined}
                        playsInline
                        preload="metadata"
                        loop
                        controls={false}
                     />
                  ) : (
                     <iframe
                        ref={iframeRef}
                        // Empty until tap — loading muted first makes unmute impossible in Chrome.
                        src="about:blank"
                        title={overlay.headline || 'Welcome video'}
                        className={`h-full w-full border-0 ${!started ? 'pointer-events-none' : ''}`}
                        allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen"
                        allowFullScreen
                     />
                  )}

                  {!started ? (
                     <button
                        type="button"
                        onClick={startWithSound}
                        className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-3 bg-black/45 transition hover:bg-black/55"
                     >
                        {overlay.poster_url ? (
                           <img
                              src={overlay.poster_url}
                              alt=""
                              className="pointer-events-none absolute inset-0 h-full w-full object-cover opacity-60"
                           />
                        ) : null}
                        <span className="relative flex h-20 w-20 items-center justify-center rounded-full bg-white text-[#0a1d37] shadow-lg">
                           <VolumeX className="h-8 w-8" />
                        </span>
                        <span className="relative rounded-full bg-black/65 px-5 py-2.5 text-base font-semibold tracking-wide text-white">
                           Tap for sound
                        </span>
                     </button>
                  ) : isFile ? (
                     <button
                        type="button"
                        onClick={toggleMuteFile}
                        className="absolute bottom-3 left-3 z-20 flex items-center gap-2 rounded-full bg-black/55 px-3 py-2 text-xs font-medium text-white backdrop-blur-sm"
                     >
                        {muted ? <VolumeX className="h-4 w-4" /> : <Volume2 className="h-4 w-4" />}
                        {muted ? 'Unmute' : 'Mute'}
                     </button>
                  ) : null}
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
