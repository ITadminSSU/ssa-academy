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

const tabFromCatalogLabel = (hay: string) => {
   if (/software/.test(hay)) {
      return 'software' as const;
   }

   if (/estimating/.test(hay)) {
      return 'trade' as const;
   }

   if (
      /independent.?contractor|career.?readiness|professional.?development|\bcareer\b|\breadiness\b|\bresume\b|\binterview\b/.test(
         hay,
      )
   ) {
      return 'professional' as const;
   }

   return null;
};

export const courseTabFor = (
   course: Pick<Course, 'title' | 'short_description'> & {
      course_category?: { title?: string; slug?: string } | null;
      course_category_child?: { title?: string; slug?: string } | null;
   },
) => {
   const parent = `${course.course_category?.title ?? ''} ${course.course_category?.slug ?? ''}`.toLowerCase();
   const fromParent = tabFromCatalogLabel(parent);

   if (fromParent) {
      return fromParent;
   }

   const child = `${course.course_category_child?.title ?? ''} ${course.course_category_child?.slug ?? ''}`.toLowerCase();
   const fromChild = tabFromCatalogLabel(child);

   if (fromChild) {
      return fromChild;
   }

   return 'other' as const;
};
