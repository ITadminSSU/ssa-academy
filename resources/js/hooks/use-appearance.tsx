import { useCallback, useEffect, useState } from 'react';

const setCookie = (name: string, value: string, days = 365) => {
   if (typeof document === 'undefined') {
      return;
   }

   const maxAge = days * 24 * 60 * 60;
   document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyTheme = (_appearance: Appearance) => {
   // Light mode is the only supported theme. Dark mode is disabled to avoid
   // unreadable text/color issues, so we never add the `dark` class.
   document.documentElement.classList.remove('dark');
};

export function resolveDefaultTheme(_configured?: string | null): Appearance {
   return 'light';
}

export function initializeTheme() {
   applyTheme('light');
}

export function useAppearance(defaultTheme: Appearance = 'light') {
   const [appearance, setAppearance] = useState<Appearance>(defaultTheme);

   const updateAppearance = useCallback((mode: Appearance) => {
      setAppearance(mode);

      // Store in localStorage for client-side persistence...
      localStorage.setItem('appearance', mode);

      // Store in cookie for SSR...
      setCookie('appearance', mode);

      applyTheme(mode);
   }, []);

   useEffect(() => {
      const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
      updateAppearance(savedAppearance || defaultTheme);
   }, [defaultTheme, updateAppearance]);

   return { appearance, updateAppearance } as const;
}
