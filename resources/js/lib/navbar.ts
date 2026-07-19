type NavbarLinkItem = {
   slug?: string;
   value?: string;
   url?: string;
};

export function resolveNavbarItemHref(item: NavbarLinkItem): string {
   const path = item.value || item.url || '';

   if (item.slug === 'courses' || path === '/courses/all') {
      return route('category.courses', { category: 'all' });
   }

   if (item.slug === 'exams' || path === '/exams/all' || path === '/browse/exams') {
      return route('exams.browse');
   }

   if (item.slug === 'home' || path === '/' || path === '/home') {
      return route('home');
   }

   return path || '/';
}
