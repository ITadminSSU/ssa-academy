import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toDateTimeLocalValue } from '@/lib/course-launch';
import { onHandleChange } from '@/lib/inertia';
import { DrywallReviewDraft, DrywallReviewType, DrywallVideoSource, emptyReviewDraft, toReviewDraft } from '@/pages/drywall-estimating/types';
import { useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { CourseUpdateProps } from '../update';

const Landing = () => {
   const { landing } = usePage<CourseUpdateProps>().props;

   if (!landing) {
      return null;
   }

   return <LandingForm />;
};

const LandingForm = () => {
   const { course, landing, appTimezone } = usePage<CourseUpdateProps>().props;
   const settings = landing!;
   const initialVideoSource: DrywallVideoSource = settings.video_source === 'upload' ? 'upload' : 'link';
   const [heroPreview, setHeroPreview] = useState(settings.hero_photo);
   const [imagePreviews, setImagePreviews] = useState<(string | null)[]>(() => settings.reviews.map(() => null));
   const [videoPreview, setVideoPreview] = useState(initialVideoSource === 'upload' ? settings.video_url : '');
   const [videoFileSelected, setVideoFileSelected] = useState(false);
   const [startVideoUpload, setStartVideoUpload] = useState(false);
   const [videoUploaded, setVideoUploaded] = useState(false);
   const previewsRef = useRef<(string | null)[]>([]);
   const pendingVideoRef = useRef<string | null>(null);
   const submitAfterUpload = useRef(false);
   const localVideoRef = useRef<string | null>(null);

   const { data, setData, post, transform, errors, processing } = useForm({
      tab: 'landing',
      countdown_ends_at: toDateTimeLocalValue(settings.countdown_ends_at, appTimezone),
      hero_photo: null as File | null,
      video_source: initialVideoSource,
      video_url: initialVideoSource === 'link' ? (settings.video_url ?? '') : '',
      video_stored_url: settings.video_stored_url ?? '',
      video_duration: settings.video_duration,
      coach_line: settings.coach_line,
      embed_code: settings.embed_html,
      reviews: (settings.reviews.length ? settings.reviews.map(toReviewDraft) : []) as DrywallReviewDraft[],
   });

   useEffect(() => {
      previewsRef.current = imagePreviews;
   }, [imagePreviews]);

   useEffect(() => {
      return () => {
         previewsRef.current.forEach((url) => {
            if (url) {
               URL.revokeObjectURL(url);
            }
         });
      };
   }, []);

   const updateReview = (index: number, patch: Partial<DrywallReviewDraft>) => {
      setData(
         'reviews',
         data.reviews.map((review, reviewIndex) => (reviewIndex === index ? { ...review, ...patch } : review)),
      );
   };

   const setReviewType = (index: number, type: DrywallReviewType) => {
      updateReview(index, { type });
   };

   const setReviewImage = (index: number, file: File | null) => {
      setImagePreviews((current) => {
         const next = [...current];
         if (next[index]) {
            URL.revokeObjectURL(next[index]);
         }
         next[index] = file ? URL.createObjectURL(file) : null;
         previewsRef.current = next;

         return next;
      });

      setData((current) => ({
         ...current,
         reviews: current.reviews.map((review, reviewIndex) => (reviewIndex === index ? { ...review, image: file } : review)),
      }));
   };

   const addReview = () => {
      setImagePreviews((current) => [...current, null]);
      setData('reviews', [...data.reviews, emptyReviewDraft()]);
   };

   const removeReview = (index: number) => {
      setImagePreviews((current) => {
         if (current[index]) {
            URL.revokeObjectURL(current[index]);
         }
         const next = current.filter((_, previewIndex) => previewIndex !== index);
         previewsRef.current = next;

         return next;
      });
      setData(
         'reviews',
         data.reviews.filter((_, reviewIndex) => reviewIndex !== index),
      );
   };

   const syncSavedLanding = (page: { props: unknown }) => {
      const nextLanding = (page.props as CourseUpdateProps).landing;
      if (!nextLanding) {
         return;
      }

      previewsRef.current.forEach((url) => {
         if (url) {
            URL.revokeObjectURL(url);
         }
      });
      const cleared = nextLanding.reviews.map(() => null);
      previewsRef.current = cleared;
      setImagePreviews(cleared);

      if (localVideoRef.current) {
         URL.revokeObjectURL(localVideoRef.current);
         localVideoRef.current = null;
      }

      const source: DrywallVideoSource = nextLanding.video_source === 'upload' ? 'upload' : 'link';
      setVideoPreview(source === 'upload' ? (nextLanding.video_url ?? '') : '');
      setVideoFileSelected(false);
      setStartVideoUpload(false);
      setVideoUploaded(false);
      submitAfterUpload.current = false;
      pendingVideoRef.current = null;
      setData((current) => ({
         ...current,
         video_source: source,
         video_url: source === 'link' ? (nextLanding.video_url ?? '') : '',
         video_stored_url: nextLanding.video_stored_url ?? '',
         reviews: nextLanding.reviews.map(toReviewDraft),
      }));
   };

   const submitLanding = (storedUrl?: string) => {
      const stored = storedUrl ?? null;
      transform((current) => ({
         ...current,
         video_source: stored ? 'upload' : current.video_source,
         video_stored_url: stored ?? current.video_stored_url,
      }));

      post(route('courses.update', { id: course.id }), {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: syncSavedLanding,
         onError: () => {
            submitAfterUpload.current = false;
            setStartVideoUpload(false);
            setVideoUploaded(false);
         },
      });
   };

   useEffect(() => {
      if (!videoUploaded || submitAfterUpload.current || !pendingVideoRef.current) {
         return;
      }

      submitAfterUpload.current = true;
      submitLanding(pendingVideoRef.current);
   }, [videoUploaded]);

   const handleSubmit = (event: React.FormEvent) => {
      event.preventDefault();

      if (data.video_source === 'upload' && videoFileSelected) {
         setStartVideoUpload(true);
         return;
      }

      submitLanding();
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-5">
            <div>
               <h2 className="text-lg font-semibold">Drywall Estimating landing page</h2>
               <p className="text-muted-foreground mt-1 text-sm">
                  These fields update /drywall-estimating. In GoHighLevel, set the form’s on-submit redirect to {settings.thanks_url}.
               </p>
            </div>

            <div>
               <Label htmlFor="countdown_ends_at">Discount ends</Label>
               <Input
                  id="countdown_ends_at"
                  type="datetime-local"
                  value={data.countdown_ends_at}
                  onChange={(event) => setData('countdown_ends_at', event.target.value)}
               />
               <InputError message={errors.countdown_ends_at} />
            </div>

            <div>
               <Label htmlFor="hero_photo">Hero photo</Label>
               <Input
                  id="hero_photo"
                  name="hero_photo"
                  type="file"
                  accept="image/*"
                  onChange={(event) => onHandleChange(event, setData, setHeroPreview)}
               />
               <p className="text-muted-foreground mt-1 text-xs">The laptop, blueprints, and hard hat photo behind the form. Maximum 10 MB.</p>
               <InputError message={errors.hero_photo} />
               {heroPreview ? <img src={heroPreview} alt="" className="mt-3 aspect-video w-full max-w-md rounded-md object-cover" /> : null}
            </div>

            <div className="space-y-3">
               <div>
                  <Label>Video</Label>
                  <div className="mt-2 flex gap-2" role="group" aria-label="Video source">
                     <Button
                        type="button"
                        size="sm"
                        variant={data.video_source === 'link' ? 'default' : 'outline'}
                        aria-pressed={data.video_source === 'link'}
                        onClick={() => setData('video_source', 'link')}
                     >
                        Link
                     </Button>
                     <Button
                        type="button"
                        size="sm"
                        variant={data.video_source === 'upload' ? 'default' : 'outline'}
                        aria-pressed={data.video_source === 'upload'}
                        onClick={() => setData('video_source', 'upload')}
                     >
                        Upload
                     </Button>
                  </div>
               </div>

               {data.video_source === 'link' ? (
                  <div>
                     <Label htmlFor="video_url">Video URL</Label>
                     <Input
                        id="video_url"
                        value={data.video_url}
                        onChange={(event) => setData('video_url', event.target.value)}
                        placeholder="https://"
                     />
                     <InputError message={errors.video_url} />
                  </div>
               ) : (
                  <div>
                     <Label htmlFor="landing-video">Video file</Label>
                     <ChunkedUploaderInput
                        isSubmit={startVideoUpload}
                        courseId={course.id}
                        filetype="video"
                        accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime"
                        acceptError="Upload an MP4, WEBM, or MOV video."
                        delayUpload
                        onFileSelected={(file) => {
                           setVideoFileSelected(true);
                           if (localVideoRef.current) {
                              URL.revokeObjectURL(localVideoRef.current);
                           }
                           const previewUrl = URL.createObjectURL(file);
                           localVideoRef.current = previewUrl;
                           setVideoPreview(previewUrl);
                        }}
                        onFileUploaded={(fileData) => {
                           pendingVideoRef.current = fileData.file_url;
                           setData('video_stored_url', fileData.file_url);
                           setVideoFileSelected(false);
                           setVideoUploaded(true);
                        }}
                        onError={() => {
                           submitAfterUpload.current = false;
                           setStartVideoUpload(false);
                           setVideoUploaded(false);
                        }}
                        onCancelUpload={() => {
                           submitAfterUpload.current = false;
                           setStartVideoUpload(false);
                           setVideoUploaded(false);
                        }}
                     />
                     <p className="text-muted-foreground mt-1 text-xs">Shown on the landing page. MP4, WEBM, or MOV. Maximum 5 GB.</p>
                     <InputError message={errors.video_url} />
                     {videoPreview ? (
                        <video src={videoPreview} controls className="mt-3 aspect-video w-full max-w-md rounded-md bg-black" />
                     ) : null}
                  </div>
               )}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
               <div>
                  <Label htmlFor="video_duration">Video duration</Label>
                  <Input id="video_duration" value={data.video_duration} onChange={(event) => setData('video_duration', event.target.value)} />
                  <InputError message={errors.video_duration} />
               </div>
               <div>
                  <Label htmlFor="coach_line">Coach line</Label>
                  <Input id="coach_line" value={data.coach_line} onChange={(event) => setData('coach_line', event.target.value)} />
                  <InputError message={errors.coach_line} />
               </div>
            </div>

            <div className="space-y-3">
               <div className="flex items-center justify-between gap-3">
                  <Label>Review carousel</Label>
                  <Button type="button" variant="outline" size="sm" onClick={addReview}>
                     <Plus className="h-4 w-4" />
                     Add review
                  </Button>
               </div>
               <p className="text-muted-foreground text-xs">Each card is either a written quote or an uploaded picture.</p>

               {data.reviews.length === 0 ? <p className="text-muted-foreground text-sm">No reviews yet. Add one to show the carousel.</p> : null}

               {data.reviews.map((review, index) => {
                  const preview = imagePreviews[index] || review.preview_url || review.existing_image;

                  return (
                     <div key={index} className="space-y-3 rounded-lg border p-3">
                        <div className="flex items-center justify-between gap-3">
                           <p className="text-sm font-medium">Review {index + 1}</p>
                           <Button type="button" variant="ghost" size="sm" onClick={() => removeReview(index)}>
                              <Trash2 className="h-4 w-4" />
                              Remove
                           </Button>
                        </div>

                        <div className="flex gap-2" role="group" aria-label={`Review ${index + 1} type`}>
                           <Button
                              type="button"
                              size="sm"
                              variant={review.type === 'written' ? 'default' : 'outline'}
                              aria-pressed={review.type === 'written'}
                              onClick={() => setReviewType(index, 'written')}
                           >
                              Written
                           </Button>
                           <Button
                              type="button"
                              size="sm"
                              variant={review.type === 'image' ? 'default' : 'outline'}
                              aria-pressed={review.type === 'image'}
                              onClick={() => setReviewType(index, 'image')}
                           >
                              Image
                           </Button>
                        </div>

                        {review.type === 'image' ? (
                           <>
                              <div>
                                 <Label htmlFor={`review-image-${index}`}>Picture</Label>
                                 <Input
                                    id={`review-image-${index}`}
                                    type="file"
                                    accept="image/*"
                                    onChange={(event) => setReviewImage(index, event.target.files?.[0] ?? null)}
                                 />
                                 <p className="text-muted-foreground mt-1 text-xs">Shown in the carousel instead of a quote. Maximum 10 MB.</p>
                                 <InputError message={errors[`reviews.${index}.image` as 'reviews']} />
                              </div>
                              {preview ? (
                                 <img src={preview} alt="" className="aspect-[4/3] w-full max-w-xs rounded-md object-cover" />
                              ) : null}
                              <div>
                                 <Label htmlFor={`review-caption-${index}`}>Caption</Label>
                                 <Input
                                    id={`review-caption-${index}`}
                                    value={review.name}
                                    onChange={(event) => updateReview(index, { name: event.target.value })}
                                    placeholder="Optional name"
                                 />
                                 <InputError message={errors[`reviews.${index}.name` as 'reviews']} />
                              </div>
                           </>
                        ) : (
                           <>
                              <Textarea
                                 rows={3}
                                 value={review.quote}
                                 onChange={(event) => updateReview(index, { quote: event.target.value })}
                                 placeholder="Quote"
                              />
                              <InputError message={errors[`reviews.${index}.quote` as 'reviews']} />
                              <Input
                                 value={review.name}
                                 onChange={(event) => updateReview(index, { name: event.target.value })}
                                 placeholder="Name"
                              />
                              <InputError message={errors[`reviews.${index}.name` as 'reviews']} />
                           </>
                        )}
                     </div>
                  );
               })}
            </div>

            <div>
               <Label htmlFor="embed_code">GoHighLevel embed code</Label>
               <Textarea
                  id="embed_code"
                  rows={8}
                  value={data.embed_code}
                  onChange={(event) => setData('embed_code', event.target.value)}
                  className="font-mono text-xs"
               />
               <InputError message={errors.embed_code} />
            </div>

            <LoadingButton loading={processing || startVideoUpload}>Save landing page</LoadingButton>
         </form>
      </Card>
   );
};

export default Landing;
