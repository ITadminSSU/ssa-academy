import { routeLastSegment } from '@/lib/route';

export function getDashboardUrl(auth: Auth): string {
   if (auth.dashboardUrl) {
      return auth.dashboardUrl;
   }

   if (!auth.user) {
      return route('login');
   }

   return route('dashboard');
}

export function isEmployeeLearner(user: User): boolean {
   const userType = user.user_type;

   return userType === 'employee' || String(userType) === 'employee';
}

export function getStudentDashboardUrl(user: User, tab = 'home'): string {
   const routeName = isEmployeeLearner(user) ? 'dashboard.internal' : 'dashboard.external';

   return route(routeName, { tab });
}

export function getLogoHref(auth: Auth): string {
   if (auth.user) {
      return getDashboardUrl(auth);
   }

   return route('category.courses', { category: 'all' });
}

export function getDashboardSlug(auth: Auth): string {
   return routeLastSegment(getDashboardUrl(auth));
}
