import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toDateTimeLocalValue } from '@/lib/course-launch';
import { onHandleChange } from '@/lib/inertia';
import { DrywallReview } from '@/pages/drywall-estimating/types';
import { useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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
   const [heroPreview, setHeroPreview] = useState(settings.hero_photo);

   const { data, setData, post, errors, processing } = useForm({
      tab: 'landing',
      countdown_ends_at: toDateTimeLocalValue(settings.countdown_ends_at, appTimezone),
      hero_photo: null as File | null,
      video_url: settings.video_url ?? '',
      video_duration: settings.video_duration,
      coach_line: settings.coach_line,
      embed_code: settings.embed_html,
      reviews: (settings.reviews.length ? settings.reviews : []) as DrywallReview[],
   });

   const updateReview = (index: number, field: keyof DrywallReview, value: string) => {
      setData(
         'reviews',
         data.reviews.map((review, reviewIndex) => (reviewIndex === index ? { ...review, [field]: value } : review)),
      );
   };

   const handleSubmit = (event: React.FormEvent) => {
      event.preventDefault();

      post(route('courses.update', { id: course.id }), {
         forceFormData: true,
         preserveScroll: true,
      });
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
                  <Button
                     type="button"
                     variant="outline"
                     size="sm"
                     onClick={() => setData('reviews', [...data.reviews, { quote: '', name: '' }])}
                  >
                     <Plus className="h-4 w-4" />
                     Add review
                  </Button>
               </div>

               {data.reviews.length === 0 ? <p className="text-muted-foreground text-sm">No reviews yet. Add one to show the carousel.</p> : null}

               {data.reviews.map((review, index) => (
                  <div key={index} className="space-y-2 rounded-lg border p-3">
                     <div className="flex items-center justify-between">
                        <p className="text-sm font-medium">Review {index + 1}</p>
                        <Button
                           type="button"
                           variant="ghost"
                           size="sm"
                           onClick={() =>
                              setData(
                                 'reviews',
                                 data.reviews.filter((_, reviewIndex) => reviewIndex !== index),
                              )
                           }
                        >
                           <Trash2 className="h-4 w-4" />
                           Remove
                        </Button>
                     </div>
                     <Textarea
                        rows={3}
                        value={review.quote}
                        onChange={(event) => updateReview(index, 'quote', event.target.value)}
                        placeholder="Quote"
                     />
                     <InputError message={errors[`reviews.${index}.quote` as 'reviews']} />
                     <Input
                        value={review.name}
                        onChange={(event) => updateReview(index, 'name', event.target.value)}
                        placeholder="Name"
                     />
                     <InputError message={errors[`reviews.${index}.name` as 'reviews']} />
                  </div>
               ))}
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

            <LoadingButton loading={processing}>Save landing page</LoadingButton>
         </form>
      </Card>
   );
};

export default Landing;
