export interface DrywallReview {
   quote: string;
   name: string;
}

export interface DrywallLandingContent {
   countdown_ends_at: string;
   hero_photo: string;
   video_url: string | null;
   video_duration: string;
   coach_line: string;
   embed_html: string;
   reviews: DrywallReview[];
   overview: string[];
}

export interface DrywallLandingSettings {
   countdown_ends_at: string;
   hero_photo: string;
   video_url: string;
   video_duration: string;
   coach_line: string;
   embed_html: string;
   reviews: DrywallReview[];
   thanks_url: string;
   using_defaults: boolean;
}
