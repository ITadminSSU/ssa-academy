export type DrywallReviewType = 'written' | 'image';

export interface DrywallReview {
   type: DrywallReviewType;
   quote: string;
   name: string;
   image: string | null;
   stored_image?: string | null;
}

export interface DrywallReviewDraft {
   type: DrywallReviewType;
   quote: string;
   name: string;
   existing_image: string;
   preview_url: string;
   image: File | null;
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

export const emptyReviewDraft = (): DrywallReviewDraft => ({
   type: 'written',
   quote: '',
   name: '',
   existing_image: '',
   preview_url: '',
   image: null,
});

export const toReviewDraft = (review: DrywallReview): DrywallReviewDraft => ({
   type: review.type === 'image' ? 'image' : 'written',
   quote: review.quote ?? '',
   name: review.name ?? '',
   existing_image: review.type === 'image' ? review.stored_image || review.image || '' : '',
   preview_url: review.type === 'image' ? review.image || '' : '',
   image: null,
});
