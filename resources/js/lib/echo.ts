import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export type ReverbConfig = {
   enabled: boolean;
   key: string;
   host: string;
   port: number;
   scheme: string;
   authEndpoint: string;
};

declare global {
   interface Window {
      Pusher: typeof Pusher;
      Echo?: Echo;
   }
}

export function createEcho(config: ReverbConfig): Echo | null {
   if (!config.enabled || !config.key) {
      return null;
   }

   window.Pusher = Pusher;

   const echo = new Echo({
      broadcaster: 'reverb',
      key: config.key,
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: config.authEndpoint,
      auth: {
         headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
         },
      },
   });

   window.Echo = echo;

   return echo;
}

export function disconnectEcho(): void {
   if (window.Echo) {
      window.Echo.disconnect();
      window.Echo = undefined;
   }
}
