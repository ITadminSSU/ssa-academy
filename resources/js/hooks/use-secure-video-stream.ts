import { useEffect, useState } from 'react';

type SecureVideoDelivery = 'direct' | 'signed' | 'blob';

interface SecureVideoPlayback {
   protected: boolean;
   stream_url: string;
   delivery: SecureVideoDelivery;
   expires_at: string | null;
   mime_type?: string;
   playback_token?: string | null;
}

interface UseSecureVideoStreamOptions {
   lessonId?: number;
   initialSrc?: string;
   secureStream?: boolean;
}

export function useSecureVideoStream({ lessonId, initialSrc = '', secureStream = false }: UseSecureVideoStreamOptions) {
   const [playbackUrl, setPlaybackUrl] = useState<string | null>(secureStream ? null : initialSrc || null);
   const [loading, setLoading] = useState(secureStream);
   const [error, setError] = useState<string | null>(null);

   useEffect(() => {
      if (!secureStream) {
         setPlaybackUrl(initialSrc || null);
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

            if (!payload.stream_url) {
               throw new Error('No secure stream was returned for this lesson.');
            }

            const playbackHeaders: HeadersInit = {
               'X-Requested-With': 'XMLHttpRequest',
            };

            if (payload.playback_token) {
               playbackHeaders['X-Playback-Token'] = payload.playback_token;
            }

            if (payload.delivery === 'blob') {
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
            } else {
               setPlaybackUrl(payload.stream_url);
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
   }, [lessonId, initialSrc, secureStream]);

   return {
      playbackUrl,
      loading,
      error,
   };
}
