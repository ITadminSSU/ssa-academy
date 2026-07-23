export const PLAYER_JS_SRC = 'https://assets.mediadelivery.net/playerjs/playerjs-latest.min.js';

export type PlayerJsInstance = {
   on: (event: string, callback: (...args: unknown[]) => void) => void;
   off: (event: string, callback?: (...args: unknown[]) => void) => void;
};

type PlayerJsConstructor = new (iframe: HTMLIFrameElement) => PlayerJsInstance;

declare global {
   interface Window {
      playerjs?: {
         Player: PlayerJsConstructor;
      };
   }
}

let playerJsLoader: Promise<void> | null = null;

export const preloadPlayerJs = (): Promise<void> => {
   if (window.playerjs?.Player) {
      return Promise.resolve();
   }

   if (playerJsLoader) {
      return playerJsLoader;
   }

   playerJsLoader = new Promise((resolve, reject) => {
      const existing = document.querySelector<HTMLScriptElement>(`script[src="${PLAYER_JS_SRC}"]`);

      if (existing) {
         if (window.playerjs?.Player) {
            resolve();
            return;
         }

         existing.addEventListener('load', () => resolve(), { once: true });
         existing.addEventListener('error', () => reject(new Error('Failed to load player.js')), { once: true });
         return;
      }

      const link = document.createElement('link');
      link.rel = 'preload';
      link.as = 'script';
      link.href = PLAYER_JS_SRC;
      document.head.appendChild(link);

      const script = document.createElement('script');
      script.src = PLAYER_JS_SRC;
      script.async = true;
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load player.js'));
      document.head.appendChild(script);
   });

   return playerJsLoader;
};
