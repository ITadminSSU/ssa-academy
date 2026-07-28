export type LogoPlacement = 'navbar' | 'footer' | 'auth' | 'dashboard' | 'certificate';

export interface LogoSizeConfig {
   height: number;
   maxWidth: number;
}

export type LogoSizes = Record<LogoPlacement, LogoSizeConfig>;

export const DEFAULT_LOGO_SIZES: LogoSizes = {
   navbar: { height: 48, maxWidth: 120 },
   footer: { height: 96, maxWidth: 280 },
   auth: { height: 200, maxWidth: 576 },
   dashboard: { height: 112, maxWidth: 240 },
   certificate: { height: 80, maxWidth: 200 },
};

export const LOGO_PLACEMENTS: LogoPlacement[] = ['navbar', 'footer', 'auth', 'dashboard', 'certificate'];

export interface LogoPlacementConfig {
   placement: LogoPlacement;
   label: string;
   description: string;
   field: keyof Pick<SystemFields, 'logo_navbar' | 'logo_footer' | 'logo_auth' | 'logo_dashboard' | 'logo_certificate'>;
   uploadField: string;
   recommended: string;
   sizeLimits: { height: { min: number; max: number }; maxWidth: { min: number; max: number } };
}

export const LOGO_PLACEMENT_CONFIG: Record<LogoPlacement, LogoPlacementConfig> = {
   navbar: {
      placement: 'navbar',
      label: 'Navbar logo',
      description: 'Shown in the public site header (white bar with black logo frame).',
      field: 'logo_navbar',
      uploadField: 'new_logo_navbar',
      recommended: 'Use the BLACK wordmark with minimal padding for the best fit.',
      sizeLimits: { height: { min: 32, max: 80 }, maxWidth: { min: 80, max: 240 } },
   },
   footer: {
      placement: 'footer',
      label: 'Footer logo',
      description: 'Shown in the site footer above links and contact info.',
      field: 'logo_footer',
      uploadField: 'new_logo_footer',
      recommended: 'BLACK or tagline version works well on the light footer background.',
      sizeLimits: { height: { min: 48, max: 160 }, maxWidth: { min: 120, max: 400 } },
   },
   auth: {
      placement: 'auth',
      label: 'Login / Signup logo',
      description: 'Shown on the navy hero panel for login, register, and password reset.',
      field: 'logo_auth',
      uploadField: 'new_logo_auth',
      recommended: 'Use the WHITE logo for contrast on the navy background.',
      sizeLimits: { height: { min: 80, max: 320 }, maxWidth: { min: 160, max: 640 } },
   },
   dashboard: {
      placement: 'dashboard',
      label: 'Dashboard logo',
      description: 'Shown at the top of the admin, trainer, and learner sidebar.',
      field: 'logo_dashboard',
      uploadField: 'new_logo_dashboard',
      recommended: 'Use the WHITE wordmark centered in the dark sidebar.',
      sizeLimits: { height: { min: 64, max: 180 }, maxWidth: { min: 120, max: 320 } },
   },
   certificate: {
      placement: 'certificate',
      label: 'Certificate logo',
      description: 'Default logo on course and exam certificates when no template override exists.',
      field: 'logo_certificate',
      uploadField: 'new_logo_certificate',
      recommended: 'Stacked or full-color SSA logo with transparent background.',
      sizeLimits: { height: { min: 40, max: 160 }, maxWidth: { min: 80, max: 320 } },
   },
};

export function mergeLogoSizes(configured?: Partial<LogoSizes> | null): LogoSizes {
   return LOGO_PLACEMENTS.reduce((acc, placement) => {
      acc[placement] = {
         height: configured?.[placement]?.height ?? DEFAULT_LOGO_SIZES[placement].height,
         maxWidth: configured?.[placement]?.maxWidth ?? DEFAULT_LOGO_SIZES[placement].maxWidth,
      };
      return acc;
   }, {} as LogoSizes);
}

export function getPlacementLogoField(placement: LogoPlacement): keyof SystemFields {
   return LOGO_PLACEMENT_CONFIG[placement].field;
}

export function getPlacementUploadField(placement: LogoPlacement): string {
   return LOGO_PLACEMENT_CONFIG[placement].uploadField;
}
