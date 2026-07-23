function getDateTimeLocalParts(date: Date, timeZone: string) {
   const parts = new Intl.DateTimeFormat('en-GB', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
   }).formatToParts(date);

   const get = (type: Intl.DateTimeFormatPartTypes) => parts.find((part) => part.type === type)?.value ?? '00';

   return {
      year: get('year'),
      month: get('month'),
      day: get('day'),
      hour: get('hour') === '24' ? '00' : get('hour'),
      minute: get('minute'),
   };
}

export function toDateTimeLocalValue(value?: string | null, timeZone?: string): string {
   if (!value) {
      return '';
   }

   const date = new Date(value);

   if (Number.isNaN(date.getTime())) {
      return '';
   }

   const tz = timeZone || Intl.DateTimeFormat().resolvedOptions().timeZone;
   const parts = getDateTimeLocalParts(date, tz);

   return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

export function minDateTimeLocalValue(timeZone?: string): string {
   return toDateTimeLocalValue(new Date().toISOString(), timeZone);
}
export function isCourseComingSoon(course: Pick<Course, 'status' | 'launch_at' | 'is_coming_soon'>): boolean {
   if (typeof course.is_coming_soon === 'boolean') {
      return course.is_coming_soon;
   }

   if (course.status === 'upcoming') {
      return true;
   }

   if (!course.launch_at) {
      return false;
   }

   return new Date(course.launch_at) > new Date();
}

export function formatCourseLaunchDate(
   course: Pick<Course, 'launch_at'>,
   options: Intl.DateTimeFormatOptions = {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
   },
): string | null {
   if (!course.launch_at) {
      return null;
   }

   return new Date(course.launch_at).toLocaleDateString(undefined, options);
}

export function formatCourseLaunchDateTime(course: Pick<Course, 'launch_at'>): string | null {
   if (!course.launch_at) {
      return null;
   }

   return new Date(course.launch_at).toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
   });
}

export type LaunchCountdownParts = {
   days: number;
   hours: number;
   minutes: number;
   seconds: number;
   expired: boolean;
   totalMs: number;
};

export function getLaunchCountdownParts(launchAt?: string | null): LaunchCountdownParts | null {
   if (!launchAt) {
      return null;
   }

   const target = new Date(launchAt).getTime();

   if (Number.isNaN(target)) {
      return null;
   }

   const totalMs = target - Date.now();

   if (totalMs <= 0) {
      return {
         days: 0,
         hours: 0,
         minutes: 0,
         seconds: 0,
         expired: true,
         totalMs: 0,
      };
   }

   const days = Math.floor(totalMs / (1000 * 60 * 60 * 24));
   const hours = Math.floor((totalMs / (1000 * 60 * 60)) % 24);
   const minutes = Math.floor((totalMs / (1000 * 60)) % 60);
   const seconds = Math.floor((totalMs / 1000) % 60);

   return {
      days,
      hours,
      minutes,
      seconds,
      expired: false,
      totalMs,
   };
}

export function formatLaunchCountdownShort(launchAt?: string | null): string | null {
   const parts = getLaunchCountdownParts(launchAt);

   if (!parts || parts.expired) {
      return null;
   }

   if (parts.days > 0) {
      return `${parts.days} day${parts.days === 1 ? '' : 's'} left`;
   }

   if (parts.hours > 0) {
      return `${parts.hours} hour${parts.hours === 1 ? '' : 's'} left`;
   }

   if (parts.minutes > 0) {
      return `${parts.minutes} min left`;
   }

   return 'Launching soon';
}

export function canPreviewCourseBeforeLaunch(
   course: Pick<Course, 'status' | 'launch_at' | 'is_coming_soon' | 'can_preview_before_launch'>,
): boolean {
   return isCourseComingSoon(course) && Boolean(course.can_preview_before_launch);
}
