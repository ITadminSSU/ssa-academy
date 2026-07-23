import type { Config, route as routeFn } from 'ziggy-js';

declare global {
   const route: typeof routeFn;
}

export interface PlatformFeatures {
   blog: boolean;
   job_circulars: boolean;
   careers_page: boolean;
   newsletters: boolean;
   exams_public_nav: boolean;
}

export interface Branding {
   name: string;
   short_name: string;
   author: string;
   tagline: string;
   keywords: string;
   description: string;
   logos?: {
      icon?: string;
      dark?: string;
      light?: string;
      favicon?: string;
   };
}

export interface LearnerNavCourse {
   id: number;
   title: string;
   slug: string;
}

export interface LearnerNavCategory {
   id: number;
   title: string;
   slug: string;
   courses: LearnerNavCourse[];
}

export interface LearnerNavGuide {
   id: number;
   key: string;
   title: string;
}

export interface LearnerNav {
   categories: LearnerNavCategory[];
   guides?: LearnerNavGuide[];
}

export interface SharedData {
   page: Page;
   auth: Auth;
   learnerNav?: LearnerNav | null;
   branding: Branding;
   features: PlatformFeatures;
   bunnyStream?: { enabled: boolean };
   customize: boolean;
   navbar: Navbar;
   footer: Footer;
   notifications: Notification[];
   system: Settings<SystemFields>;
   ziggy: Config & { location: string };
   flash: {
      error: string;
      warning: string;
      success: string;
   };
   langs: Language[];
   locale: string;
   direction: 'ltr';
   cartCount: number;
   appTimezone?: string;
   translate: LanguageTranslations;
   [key: string]: unknown;
}

// export type SharedData<T extends Record<string, unknown> = Record<string, unknown>> = T & {
//     auth: {
//         user: User;
//     };
//     ziggy: Config & { location: string };
//     flash: {
//         error: string;
//         warning: string;
//         success: string;
//     };
// };
