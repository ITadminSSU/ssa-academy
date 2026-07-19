const DEFAULTS = {
   course_audience: 'Course audience',
   course_audience_internal: 'Internal (employees only)',
   course_audience_public: 'Public (catalog visible to everyone)',
   course_audience_both: 'Both (internal employees and public learners)',
} as const;

type InputLabels = Partial<Record<keyof typeof DEFAULTS, string | undefined>>;

export function courseAudienceFieldLabel(input: InputLabels): string {
   return input.course_audience || DEFAULTS.course_audience;
}

export function courseAudienceOptionLabel(input: InputLabels, audience: string): string {
   if (audience === 'internal') {
      return input.course_audience_internal || DEFAULTS.course_audience_internal;
   }

   if (audience === 'public') {
      return input.course_audience_public || DEFAULTS.course_audience_public;
   }

   return input.course_audience_both || DEFAULTS.course_audience_both;
}
