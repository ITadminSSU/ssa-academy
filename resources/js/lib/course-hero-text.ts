export interface CourseHeroContent {
   eyebrow: string;
   headline: string;
   highlights: string[];
   paragraph: string;
   photo: string | null;
   video: string | null;
}

export const heroPhrases = (highlights: string): string[] => {
   const text = highlights.trim();

   if (text === '') {
      return [];
   }

   const parts = /\r?\n/.test(text) ? text.split(/\r?\n/) : text.split(',');

   return parts.map((part) => part.trim()).filter((part) => part.length > 0);
};

export const unmatchedHeroPhrases = (headline: string, highlights: string): string[] => {
   const source = headline.toLowerCase();

   return heroPhrases(highlights).filter((part) => !source.includes(part.toLowerCase()));
};

export const heroHeadlineParts = (headline: string, highlights: string[]): { text: string; accent: boolean }[] => {
   const needles = [...highlights].map((part) => part.trim()).filter(Boolean).sort((a, b) => b.length - a.length);
   const parts: { text: string; accent: boolean }[] = [];
   const lower = headline.toLowerCase();
   let index = 0;

   while (index < headline.length) {
      const match = needles.find((needle) => lower.startsWith(needle.toLowerCase(), index));

      if (match) {
         parts.push({ text: headline.slice(index, index + match.length), accent: true });
         index += match.length;
         continue;
      }

      const next = needles
         .map((needle) => lower.indexOf(needle.toLowerCase(), index))
         .filter((at) => at >= 0)
         .sort((a, b) => a - b)[0];
      const end = next === undefined ? headline.length : next;
      parts.push({ text: headline.slice(index, end), accent: false });
      index = end;
   }

   return parts;
};

export const isHeroVideoLink = (url: string): boolean => /youtube\.com|youtu\.be|vimeo\.com/i.test(url);
