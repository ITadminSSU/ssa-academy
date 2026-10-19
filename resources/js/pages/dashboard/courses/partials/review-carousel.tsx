import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { nanoid } from 'nanoid';
import { useEffect, useRef, useState } from 'react';
import { CourseUpdateProps } from '../update';

type ReviewSlideDraft = {
   key: string;
   existingUrl: string;
   file: File | null;
   previewUrl: string;
};

const slidesFromUrls = (urls: string[] | null | undefined): ReviewSlideDraft[] =>
   (urls ?? [])
      .map((url) => url.trim())
      .filter((url) => url !== '')
      .map((url) => ({
         key: nanoid(),
         existingUrl: url,
         file: null,
         previewUrl: url,
      }));

const ReviewCarousel = () => {
   const { course } = usePage<CourseUpdateProps>().props;
   const [slides, setSlides] = useState<ReviewSlideDraft[]>(() => slidesFromUrls(course.review_carousel));
   const [fileKey, setFileKey] = useState(0);
   const slidesRef = useRef(slides);
   slidesRef.current = slides;

   useEffect(() => {
      return () => {
         slidesRef.current.forEach((slide) => {
            if (slide.file) {
               URL.revokeObjectURL(slide.previewUrl);
            }
         });
      };
   }, []);

   const { post, processing, errors, transform } = useForm({
      tab: 'review-carousel',
      slides: [] as { existing_url: string; image: File | null }[],
   });

   const addFiles = (list: FileList | null) => {
      if (!list?.length) {
         return;
      }

      const remaining = 24 - slides.length;

      if (remaining <= 0) {
         return;
      }

      const added = Array.from(list)
         .slice(0, remaining)
         .map((file) => ({
            key: nanoid(),
            existingUrl: '',
            file,
            previewUrl: URL.createObjectURL(file),
         }));

      setSlides((current) => [...current, ...added]);
      setFileKey((key) => key + 1);
   };

   const moveSlide = (index: number, direction: -1 | 1) => {
      setSlides((current) => {
         const target = index + direction;

         if (target < 0 || target >= current.length) {
            return current;
         }

         const next = [...current];
         const [item] = next.splice(index, 1);
         next.splice(target, 0, item);

         return next;
      });
   };

   const removeSlide = (index: number) => {
      setSlides((current) => {
         const slide = current[index];

         if (slide?.file) {
            URL.revokeObjectURL(slide.previewUrl);
         }

         return current.filter((_, slideIndex) => slideIndex !== index);
      });
   };

   const handleSubmit = (event: React.FormEvent) => {
      event.preventDefault();

      const payload = slides.map((slide) => ({
         existing_url: slide.existingUrl,
         image: slide.file,
      }));

      transform(() => ({
         tab: 'review-carousel',
         slides: payload,
      }));

      post(route('courses.update', { id: course.id }), {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: (page) => {
            slides.forEach((slide) => {
               if (slide.file) {
                  URL.revokeObjectURL(slide.previewUrl);
               }
            });
            const saved = (page.props as unknown as CourseUpdateProps).course.review_carousel;
            setSlides(slidesFromUrls(saved));
            setFileKey((key) => key + 1);
         },
      });
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-5">
            <div>
               <h2 className="text-lg font-semibold">Review carousel</h2>
               <p className="text-muted-foreground mt-1 text-sm">
                  Upload the review images you want on this course page. The public carousel shows three at a time on a desktop. It stays hidden until
                  at least one image is saved. This does not change the student reviews tab.
               </p>
            </div>

            <div>
               <Label htmlFor="review_carousel_images">Add images</Label>
               <Input
                  key={fileKey}
                  id="review_carousel_images"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                  multiple
                  onChange={(event) => addFiles(event.target.files)}
               />
               <p className="text-muted-foreground mt-1 text-xs">
                  JPG, PNG, or WebP. Maximum 100 MB each. Leave this empty to keep the images already saved. Removing one image does not remove the
                  others.
               </p>
               <InputError message={errors.slides as unknown as string} />
            </div>

            {slides.length === 0 ? (
               <p className="text-muted-foreground text-sm">No review images yet.</p>
            ) : (
               <ol className="space-y-3">
                  {slides.map((slide, index) => (
                     <li key={slide.key} className="border-border/70 flex gap-3 rounded-xl border bg-white p-3">
                        <img src={slide.previewUrl} alt="" className="border-border/70 h-24 w-24 shrink-0 rounded-lg border object-contain" />
                        <div className="min-w-0 flex-1 space-y-2">
                           <p className="text-sm font-medium">Image {index + 1}</p>
                           <p className="text-muted-foreground truncate text-xs">{slide.file ? slide.file.name : 'Saved image'}</p>
                           <InputError message={errors[`slides.${index}.image` as 'slides']} />
                           <InputError message={errors[`slides.${index}.existing_url` as 'slides']} />
                           <div className="flex flex-wrap gap-2">
                              <Button type="button" variant="outline" size="sm" disabled={index === 0} onClick={() => moveSlide(index, -1)}>
                                 <ArrowUp />
                                 Move up
                              </Button>
                              <Button
                                 type="button"
                                 variant="outline"
                                 size="sm"
                                 disabled={index === slides.length - 1}
                                 onClick={() => moveSlide(index, 1)}
                              >
                                 <ArrowDown />
                                 Move down
                              </Button>
                              <Button type="button" variant="outline" size="sm" onClick={() => removeSlide(index)}>
                                 <Trash2 />
                                 Remove
                              </Button>
                           </div>
                        </div>
                     </li>
                  ))}
               </ol>
            )}

            <LoadingButton loading={processing}>Save review carousel</LoadingButton>
         </form>
      </Card>
   );
};

export default ReviewCarousel;
