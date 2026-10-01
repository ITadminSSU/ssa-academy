export const LEGAL_PAGE_SLUGS = [
   'cookie-policy',
   'terms-and-conditions',
   'privacy-policy',
   'refund-policy',
   'non-disclosure-agreement',
] as const;

const normalizeTitle = (value: string): string =>
   value
      .replace(/<[^>]+>/g, ' ')
      .replace(/&nbsp;/gi, ' ')
      .replace(/&amp;/gi, '&')
      .replace(/&#39;|&apos;/gi, "'")
      .replace(/&quot;/gi, '"')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, ' ')
      .trim();

const isRepeatedPageTitle = (headingHtml: string, pageName: string): boolean => {
   const heading = normalizeTitle(headingHtml);
   const name = normalizeTitle(pageName);

   if (heading === '' || name === '') {
      return false;
   }

   if (heading === name) {
      return true;
   }

   const withoutNda = heading.replace(/\bnda\b/g, ' ').replace(/\s+/g, ' ').trim();

   return withoutNda === name;
};

/** Drop leading headings that only repeat the page name. Body copy stays. */
export const stripLeadingPageTitle = (html: string, pageName: string): string => {
   let rest = html.trimStart();
   const heading = /^<h([1-6])\b[^>]*>([\s\S]*?)<\/h\1>/i;

   while (rest.length > 0) {
      const match = heading.exec(rest);

      if (!match || !isRepeatedPageTitle(match[2], pageName)) {
         break;
      }

      rest = rest.slice(match[0].length).trimStart();
   }

   return rest;
};
