import { useEffect, useState } from 'react';

type SecureVideoDelivery = 'direct' | 'signed' | 'blob' | 'bunny_embed';

export interface SecureVideoPlayback {
   protected: boolean;
   stream_url: string;
   embed_url?: string;
   delivery: SecureVideoDelivery;
   expires_at: string | null;
   mime_type?: string;
   playback_token?: string | null;
}

interface UseSecureVideoStreamOptions {
   lessonId?: number;
   initialSrc?: string;
   secureStream?: boolean;
   initialPlayback?: SecureVideoPlayback | null;
}

const applyPlaybackPayload = (payload: SecureVideoPlayback) => ({
   delivery: payload.delivery,
   embedUrl: payload.delivery === 'bunny_embed' ? payload.embed_url || payload.stream_url : null,
   playbackUrl: payload.delivery === 'bunny_embed' ? null : payload.stream_url,
});

export function useSecureVideoStream({
   lessonId,
   initialSrc = '',
   secureStream = false,
   initialPlayback = null,
}: UseSecureVideoStreamOptions) {
   const hasInitialPlayback = Boolean(
      secureStream && initialPlayback && (initialPlayback.stream_url || initialPlayback.embed_url),
   );
   const initialState = hasInitialPlayback ? applyPlaybackPayload(initialPlayback!) : null;

   const [playbackUrl, setPlaybackUrl] = useState<string | null>(
      initialState?.playbackUrl ?? (secureStream ? null : initialSrc || null),
   );
   const [embedUrl, setEmbedUrl] = useState<string | null>(initialState?.embedUrl ?? null);
   const [delivery, setDelivery] = useState<SecureVideoDelivery | null>(
      initialState?.delivery ?? (secureStream ? null : 'direct'),
   );
   const [loading, setLoading] = useState(secureStream && !hasInitialPlayback);
   const [error, setError] = useState<string | null>(null);

   useEffect(() => {
      if (!secureStream) {
         setPlaybackUrl(initialSrc || null);
         setEmbedUrl(null);
         setDelivery('direct');
         setLoading(false);
         setError(null);
         return;
      }

      if (hasInitialPlayback && initialPlayback) {
         const next = applyPlaybackPayload(initialPlayback);
         setDelivery(next.delivery);
         setEmbedUrl(next.embedUrl);
         setPlaybackUrl(next.playbackUrl);
         setLoading(false);
         setError(null);
         return;
      }

      if (!lessonId) {
         setError('Protected lesson is missing an identifier.');
         setLoading(false);
         return;
      }

      let cancelled = false;
      let objectUrl: string | null = null;

      const loadProtectedStream = async () => {
         setLoading(true);
         setError(null);
         setPlaybackUrl(null);
         setEmbedUrl(null);
         setDelivery(null);

         try {
            const tokenResponse = await fetch(route('course.player.video.stream-url', { lesson: lessonId }), {
               credentials: 'same-origin',
               headers: {
                  Accept: 'application/json',
                  'X-Requested-With': 'XMLHttpRequest',
               },
            });

            if (!tokenResponse.ok) {
               let message = 'Unable to authorize video playback.';

               try {
                  const errorPayload = (await tokenResponse.json()) as { message?: string };
                  if (errorPayload.message) {
                     message = errorPayload.message;
                  }
               } catch {
                  // Ignore JSON parse errors and keep the default message.
               }

               throw new Error(message);
            }

            const payload = (await tokenResponse.json()) as SecureVideoPlayback;

            if (cancelled) {
               return;
            }

            if (!payload.stream_url && !payload.embed_url) {
               throw new Error('No secure stream was returned for this lesson.');
            }

            const next = applyPlaybackPayload(payload);
            setDelivery(next.delivery);
            setEmbedUrl(next.embedUrl);
            setPlaybackUrl(next.playbackUrl);

            if (payload.delivery === 'blob') {
               const playbackHeaders: HeadersInit = {
                  'X-Requested-With': 'XMLHttpRequest',
               };

               if (payload.playback_token) {
                  playbackHeaders['X-Playback-Token'] = payload.playback_token;
               }

               const videoResponse = await fetch(payload.stream_url, {
                  credentials: 'same-origin',
                  headers: playbackHeaders,
               });

               if (!videoResponse.ok) {
                  throw new Error('Unable to load protected video stream.');
               }

               const blob = await videoResponse.blob();
               objectUrl = URL.createObjectURL(blob);
               setPlaybackUrl(objectUrl);
            }
         } catch (streamError) {
            if (!cancelled) {
               setError(streamError instanceof Error ? streamError.message : 'Unable to load protected video.');
            }
         } finally {
            if (!cancelled) {
               setLoading(false);
            }
         }
      };

      loadProtectedStream();

      return () => {
         cancelled = true;

         if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
         }
      };
   }, [lessonId, initialSrc, secureStream, initialPlayback?.delivery, initialPlayback?.stream_url, initialPlayback?.embed_url]);

   return {
      playbackUrl,
      embedUrl,
      delivery,
      loading,
      error,
   };
}
