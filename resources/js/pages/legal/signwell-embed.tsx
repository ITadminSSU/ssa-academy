import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import Main from '@/layouts/main';
import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

interface Props {
   signingUrl: string;
   completeUrl: string;
   cancelUrl: string;
}

type SignWellEmbedInstance = {
   open: () => void;
   close?: () => void;
};

type SignWellEmbedConstructor = new (options: Record<string, unknown>) => SignWellEmbedInstance;

declare global {
   interface Window {
      SignWellEmbed?: SignWellEmbedConstructor;
   }
}

const SIGNWELL_SCRIPT = 'https://static.signwell.com/assets/embedded.js';

const loadSignWellScript = (): Promise<void> => {
   if (window.SignWellEmbed) {
      return Promise.resolve();
   }

   const existing = document.querySelector<HTMLScriptElement>(`script[src="${SIGNWELL_SCRIPT}"]`);

   if (existing) {
      return new Promise((resolve, reject) => {
         if (window.SignWellEmbed) {
            resolve();
            return;
         }

         existing.addEventListener('load', () => resolve(), { once: true });
         existing.addEventListener('error', () => reject(new Error('SignWell script failed to load')), { once: true });
      });
   }

   return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = SIGNWELL_SCRIPT;
      script.async = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('SignWell script failed to load'));
      document.body.appendChild(script);
   });
};

const SignWellEmbedPage = ({ signingUrl, completeUrl, cancelUrl }: Props) => {
   const finished = useRef(false);
   const embedRef = useRef<SignWellEmbedInstance | null>(null);
   const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading');
   const [errorMessage, setErrorMessage] = useState<string | null>(null);

   const goToComplete = () => {
      if (finished.current) {
         return;
      }

      finished.current = true;
      window.location.assign(completeUrl);
   };

   const goToCancel = () => {
      if (finished.current) {
         return;
      }

      window.location.assign(cancelUrl);
   };

   useEffect(() => {
      let cancelled = false;

      const start = async () => {
         try {
            await loadSignWellScript();

            if (cancelled) {
               return;
            }

            if (!window.SignWellEmbed) {
               throw new Error('SignWell embed is not available');
            }

            const container = document.getElementById('signwell-embed');

            if (container) {
               container.replaceChildren();
            }

            const embed = new window.SignWellEmbed({
               url: signingUrl,
               containerId: 'signwell-embed',
               redirectionUrl: completeUrl,
               allowRedirect: true,
               iframeRedirect: false,
               allowClose: true,
               allowDecline: true,
               declineRedirectionUrl: cancelUrl,
               events: {
                  completed: goToComplete,
                  complete: goToComplete,
                  declined: goToCancel,
                  error: () => {
                     if (!finished.current) {
                        setStatus('error');
                        setErrorMessage('SignWell could not open the agreement. Please try again.');
                     }
                  },
               },
            });

            embedRef.current = embed;
            embed.open();
            setStatus('ready');
         } catch (error) {
            if (cancelled) {
               return;
            }

            setStatus('error');
            setErrorMessage(error instanceof Error ? error.message : 'SignWell could not be loaded.');
         }
      };

      void start();

      return () => {
         cancelled = true;
         embedRef.current?.close?.();
      };
      // Signing URLs are stable for this page load.
      // eslint-disable-next-line react-hooks/exhaustive-deps
   }, [signingUrl, completeUrl, cancelUrl]);

   return (
      <Main>
         <Head title="Sign Student Agreement" />

         <div className="bg-background flex min-h-svh flex-col">
            <header className="border-border flex items-center justify-between gap-4 border-b px-4 py-3 md:px-6">
               <a href={route('home')} className="block max-w-[220px]">
                  <AppLogo className="h-10 w-auto" theme="light" />
               </a>
               <p className="text-muted-foreground hidden text-sm md:block">Sign the student agreement to continue to your dashboard.</p>
               <Button variant="outline" type="button" onClick={goToCancel}>
                  Cancel
               </Button>
            </header>

            <div className="flex flex-1 flex-col p-4 md:p-6">
               {status === 'loading' ? (
                  <p className="text-muted-foreground mb-4 text-sm">Loading the student agreement…</p>
               ) : null}

               {status === 'error' ? (
                  <div className="border-destructive/30 bg-destructive/5 mx-auto mb-4 max-w-xl space-y-3 rounded-lg border p-4 text-sm">
                     <p className="font-medium">We could not open the signing form.</p>
                     <p className="text-muted-foreground">{errorMessage}</p>
                     <div className="flex flex-wrap gap-2">
                        <Button type="button" onClick={() => window.location.reload()}>
                           Try again
                        </Button>
                        <Button variant="outline" type="button" onClick={goToComplete}>
                           I already signed
                        </Button>
                        <Button variant="ghost" type="button" onClick={goToCancel}>
                           Back
                        </Button>
                     </div>
                  </div>
               ) : null}

               <div id="signwell-embed" className="min-h-[720px] w-full flex-1 overflow-hidden rounded-lg border" />

               <div className="text-muted-foreground mx-auto mt-4 flex max-w-xl flex-col items-center gap-2 text-center text-xs">
                  <p>When you finish signing, you will be taken to your dashboard automatically.</p>
                  <button type="button" className="underline" onClick={goToComplete}>
                     I already finished signing
                  </button>
               </div>
            </div>
         </div>
      </Main>
   );
};

export default SignWellEmbedPage;
