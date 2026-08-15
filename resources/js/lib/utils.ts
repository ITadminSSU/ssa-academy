import currencies from '@/data/currencies';
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
   return twMerge(clsx(inputs));
}

/**
 * Formats bytes into a human-readable string (KB, MB, GB, etc.)
 * @param bytes - The number of bytes to format
 * @param decimals - Number of decimal places to include
 * @returns Formatted string with appropriate size unit
 */
export function formatBytes(bytes: number, decimals: number = 2): string {
   if (bytes === 0) return '0 Bytes';

   const k = 1024;
   const dm = decimals < 0 ? 0 : decimals;
   const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

   const i = Math.floor(Math.log(bytes) / Math.log(k));

   return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

type DurationFormat = 'hhmmss' | 'readable';

const parseDurationToSeconds = (value?: string | number | null): number => {
   if (value == null || value === '') {
      return 0;
   }

   if (typeof value === 'number' && Number.isFinite(value)) {
      return Math.max(0, Math.floor(value));
   }

   const raw = String(value).trim();
   if (!raw || raw === '00:00:00') {
      return 0;
   }

   // Plain seconds
   if (/^\d+(\.\d+)?$/.test(raw)) {
      return Math.max(0, Math.floor(Number(raw)));
   }

   const parts = raw.split(':').map((part) => Number(part) || 0);

   if (parts.length === 3) {
      return parts[0] * 3600 + parts[1] * 60 + Math.floor(parts[2]);
   }

   if (parts.length === 2) {
      return parts[0] * 60 + Math.floor(parts[1]);
   }

   return 0;
};

const formatDurationSeconds = (totalSeconds: number, format: DurationFormat): string => {
   const safe = Math.max(0, Math.floor(totalSeconds));
   const hours = Math.floor(safe / 3600);
   const minutes = Math.floor((safe % 3600) / 60);
   const seconds = safe % 60;

   if (format === 'readable') {
      if (safe <= 0) {
         return '—';
      }

      if (hours > 0 && minutes > 0) {
         return `${hours}hr ${minutes}min`;
      }

      if (hours > 0) {
         return `${hours}hr`;
      }

      if (minutes > 0) {
         return `${minutes}min`;
      }

      return `${seconds}sec`;
   }

   return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
};

// Calculate total duration from all section lessons and quizzes
export const getCourseDuration = (course: Course, format: DurationFormat = 'hhmmss'): string => {
   const sections = course?.sections ?? [];

   const totalSeconds = sections.reduce((totalTime, section) => {
      const lessonSeconds = (section.section_lessons ?? []).reduce((sectionTime, lesson) => {
         return sectionTime + parseDurationToSeconds(lesson.duration);
      }, 0);

      const quizSeconds = (section.section_quizzes ?? []).reduce((sectionTime, quiz) => {
         const fromClock = parseDurationToSeconds(quiz.duration);
         if (fromClock > 0) {
            return sectionTime + fromClock;
         }

         // Fallback when duration string is missing but H/M/S fields exist
         const hours = Number(quiz.hours) || 0;
         const minutes = Number(quiz.minutes) || 0;
         const seconds = Number(quiz.seconds) || 0;

         return sectionTime + hours * 3600 + minutes * 60 + seconds;
      }, 0);

      return totalTime + lessonSeconds + quizSeconds;
   }, 0);

   return formatDurationSeconds(totalSeconds, format);
};

// Get completed content like lessons or quizzes
export const getCompletedContents = (watchHistory: WatchHistory): CompletedContent[] => {
   try {
      let completed: unknown = watchHistory?.completed_watching;

      if (typeof completed === 'string') {
         completed = JSON.parse(completed);
         if (typeof completed === 'string') {
            completed = JSON.parse(completed);
         }
      }

      if (!Array.isArray(completed)) {
         return [];
      }

      // Legacy double-encode sometimes yields a single JSON string element.
      if (typeof completed[0] === 'string') {
         completed = JSON.parse(completed[0]);
      }

      if (!Array.isArray(completed)) {
         return [];
      }

      return completed.filter(
         (item): item is CompletedContent =>
            Boolean(item) && typeof item === 'object' && 'id' in item && 'type' in item,
      );
   } catch {
      return [];
   }
};

// Add completion calculation
export const getCourseCompletion = (course: Course, completed: CompletedContent[]) => {
   const sections = course.sections ?? [];

   const totalItems = sections.reduce(
      (total, section) => total + (section.section_lessons?.length ?? 0) + (section.section_quizzes?.length ?? 0),
      0,
   );

   const completedItems = sections.reduce((total, section) => {
      const completedLessons = (section.section_lessons ?? []).filter((lesson) =>
         completed.some((item) => String(item.id) === String(lesson.id) && item.type === 'lesson'),
      ).length;

      const completedQuizzes = (section.section_quizzes ?? []).filter((quiz) =>
         completed.some((item) => String(item.id) === String(quiz.id) && item.type === 'quiz'),
      ).length;

      return total + completedLessons + completedQuizzes;
   }, 0);

   const percentage = totalItems > 0 ? ((completedItems / totalItems) * 100).toFixed(2) : '0.00';

   return {
      percentage,
      totalContents: totalItems,
      completedContents: completedItems,
   };
};

// export function disableRouterProgress() {
//     router.on('start', () => nProgress.remove());
//     router.on('finish', () => nProgress.remove());
// }

// Function to convert color to specified opacity
export const getColorWithOpacity = (color: string, opacity: number = 0.1) => {
   // Handle RGBA colors
   if (color.startsWith('rgba(')) {
      const match = color.match(/rgba\((\d+),(\d+),(\d+),([^)]+)\)/);
      if (match) {
         const [, r, g, b] = match;
         return `rgba(${r},${g},${b},${opacity})`;
      }
   }

   // Handle RGB colors
   if (color.startsWith('rgb(')) {
      const match = color.match(/rgb\((\d+),(\d+),(\d+)\)/);
      if (match) {
         const [, r, g, b] = match;
         return `rgba(${r},${g},${b},${opacity})`;
      }
   }

   // Handle HEX colors
   if (color.startsWith('#')) {
      const hex = color.replace('#', '');
      const r = parseInt(hex.substr(0, 2), 16);
      const g = parseInt(hex.substr(2, 2), 16);
      const b = parseInt(hex.substr(4, 2), 16);
      return `rgba(${r},${g},${b},${opacity})`;
   }

   // Handle named colors (basic ones)
   const namedColors: Record<string, string> = {
      red: 'rgba(255,0,0,',
      green: 'rgba(0,128,0,',
      blue: 'rgba(0,0,255,',
      yellow: 'rgba(255,255,0,',
      purple: 'rgba(128,0,128,',
      orange: 'rgba(255,165,0,',
      pink: 'rgba(255,192,203,',
      black: 'rgba(0,0,0,',
      white: 'rgba(255,255,255,',
   };

   if (namedColors[color.toLowerCase()]) {
      return `${namedColors[color.toLowerCase()]}${opacity})`;
   }

   return color;
};

export const systemCurrency = (currency: string) => {
   return currencies.find((item) => item.value == currency);
};

export function getReadingTime(description: string): string {
   // Remove HTML tags
   const plainText = description.replace(/<[^>]*>/g, '');
   // Count words
   const wordCount = plainText.trim().split(/\s+/).length;
   const wordsPerMinute = 200;
   const minutes = Math.ceil(wordCount / wordsPerMinute);

   return `${minutes} min read`;
}

// Auto-generate slug from title
export const generateSlug = (title: string) => {
   return title
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '-')
      .replace(/-+/g, '-')
      .replace(/^-+|-+$/g, '');
};

// Helper to handle file download
export const handleDownload = async (resource: LessonResource, e: React.MouseEvent) => {
   e.preventDefault();
   try {
      // For non-link resources, use the download endpoint
      const url = route('resources.download', resource.id);
      window.open(url, '_blank');
   } catch (error) {
      // Fallback to direct download if the endpoint fails
      window.open(resource.resource, '_blank');
   }
};
