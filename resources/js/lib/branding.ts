export const BRAND_NAME = import.meta.env.VITE_BRAND_NAME || 'Smart Sourcing Academy';
export const BRAND_SHORT_NAME = import.meta.env.VITE_BRAND_SHORT_NAME || 'SSU Academy';
export const BRAND_AUTHOR = import.meta.env.VITE_BRAND_AUTHOR || 'Smart Sourcing USA';
export const BRAND_TAGLINE = import.meta.env.VITE_BRAND_TAGLINE || 'Enterprise training for teams and professionals within the construction industry.';

export const BRAND_LOGOS = {
   icon: '/assets/branding/logo-icon.png',
   dark: '/assets/branding/logo-dark.png',
   light: '/assets/branding/logo-light.png',
   favicon: '/favicon.png',
} as const;

const LEGACY_NAMES = ['lms academy', 'mentor lms', 'mentor', 'ui lib', 'uilib'];

export function isLegacyBrandName(value?: string | null): boolean {
   if (!value) {
      return true;
   }

   const normalized = value.trim().toLowerCase();

   return LEGACY_NAMES.includes(normalized) || normalized.includes('mentor') || normalized.includes('uilib');
}

export function resolveSiteName(configured?: string | null): string {
   return isLegacyBrandName(configured) ? BRAND_NAME : configured || BRAND_NAME;
}

export function resolveAuthor(configured?: string | null): string {
   return isLegacyBrandName(configured) ? BRAND_AUTHOR : configured || BRAND_AUTHOR;
}

export function isLegacyLogo(path?: string | null): boolean {
   if (!path) {
      return true;
   }

   const normalized = path.toLowerCase();

   return normalized.includes('/assets/icons/logo') || normalized.includes('mentor') || normalized.includes('uilib');
}

export function resolveLogo(configured?: string | null, variant: keyof typeof BRAND_LOGOS = 'dark'): string {
   if (isLegacyLogo(configured)) {
      return BRAND_LOGOS[variant];
   }

   return configured || BRAND_LOGOS[variant];
}
