export const PUBLIC_NAV = [
   { label: 'Academy', hash: 'academy' },
   { label: 'Courses', hash: 'courses' },
   { label: 'How It Works', hash: 'how-it-works' },
   { label: 'Why SmartSourcing', hash: 'why' },
   { label: 'Resources', hash: 'resources' },
   { label: 'FAQs', hash: 'faqs' },
] as const;

export type PublicNavHash = (typeof PUBLIC_NAV)[number]['hash'];

export const homeSectionHref = (hash: string, pathname?: string) => {
   const path = pathname ?? (typeof window !== 'undefined' ? window.location.pathname : '/');

   return path === '/' ? `#${hash}` : `${route('home')}#${hash}`;
};

export const courseTabFor = (course: Pick<Course, 'title' | 'short_description'> & { course_category?: { title?: string } | null }) => {
   const hay = `${course.title} ${course.course_category?.title ?? ''} ${course.short_description ?? ''}`.toLowerCase();

   if (/plan.?swift|bluebeam|revit|autocad|auto cad|procore|primavera|on-screen|zz takeoff|software/.test(hay)) {
      return 'software' as const;
   }

   if (/professional|communication|career|leadership|development|soft skill/.test(hay)) {
      return 'professional' as const;
   }

   return 'trade' as const;
};
