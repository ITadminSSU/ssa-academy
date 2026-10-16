import ChunkedUploaderInput from '@/components/chunked-uploader-input';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { heroHeadlineParts, heroPhrases, isHeroVideoLink, unmatchedHeroPhrases } from '@/lib/course-hero-text';
import { onHandleChange } from '@/lib/inertia';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { CourseUpdateProps } from '../update';

const highlightText = (highlights: string[] | string | null | undefined): string => {
   if (Array.isArray(highlights)) {
      return highlights.join('\n');
   }

   return highlights ?? '';
};

const Hero = () => {
   const { course } = usePage<CourseUpdateProps>().props;
   const existingVideo = course.hero_video ?? '';
   const existingIsLink = existingVideo !== '' && isHeroVideoLink(existingVideo);
   const [photoPreview, setPhotoPreview] = useState(course.hero_photo || '');
   const [videoPreview, setVideoPreview] = useState(existingIsLink ? '' : existingVideo);
   const [videoFileSelected, setVideoFileSelected] = useState(false);
   const [startVideoUpload, setStartVideoUpload] = useState(false);
   const [videoUploaded, setVideoUploaded] = useState(false);
   const pendingVideoRef = useRef<string | null>(null);
   const submitAfterUpload = useRef(false);
   const localVideoRef = useRef<string | null>(null);

   const { data, setData, post, transform, errors, processing } = useForm({
      tab: 'hero',
      hero_eyebrow: course.hero_eyebrow ?? '',
      hero_headline: course.hero_headline ?? '',
      hero_highlights: highlightText(course.hero_highlights),
      hero_paragraph: course.hero_paragraph ?? '',
      hero_photo: null as File | null,
      hero_video_source: existingIsLink || existingVideo === '' ? 'link' : 'upload',
      hero_video_url: existingIsLink ? existingVideo : '',
      hero_video_stored: existingIsLink ? '' : existingVideo,
   });

   const displayedHeadline = data.hero_headline.trim() || course.title;
   const missing = unmatchedHeroPhrases(displayedHeadline, data.hero_highlights);
   const previewPhrases = heroPhrases(data.hero_highlights).filter((part) => !missing.includes(part));

   const syncSavedCourse = (page: { props: unknown }) => {
      const next = (page.props as CourseUpdateProps).course;
      const nextVideo = next.hero_video ?? '';
      const nextIsLink = nextVideo !== '' && isHeroVideoLink(nextVideo);

      if (localVideoRef.current) {
         URL.revokeObjectURL(localVideoRef.current);
         localVideoRef.current = null;
      }

      setPhotoPreview(next.hero_photo || '');
      setVideoPreview(nextIsLink ? '' : nextVideo);
      setVideoFileSelected(false);
      setStartVideoUpload(false);
      setVideoUploaded(false);
      submitAfterUpload.current = false;
      pendingVideoRef.current = null;
      setData((current) => ({
         ...current,
         hero_photo: null,
         hero_eyebrow: next.hero_eyebrow ?? '',
         hero_headline: next.hero_headline ?? '',
         hero_highlights: highlightText(next.hero_highlights),
         hero_paragraph: next.hero_paragraph ?? '',
         hero_video_source: nextIsLink || nextVideo === '' ? 'link' : 'upload',
         hero_video_url: nextIsLink ? nextVideo : '',
         hero_video_stored: nextIsLink ? '' : nextVideo,
      }));
   };

   const submitHero = (storedUrl?: string) => {
      transform((current) => ({
         ...current,
         hero_video_source: storedUrl ? 'upload' : current.hero_video_source,
         hero_video_stored: storedUrl ?? current.hero_video_stored,
         hero_photo: current.hero_photo,
      }));

      post(route('courses.update', { id: course.id }), {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: syncSavedCourse,
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
      submitHero(pendingVideoRef.current);
   }, [videoUploaded]);

   const handleSubmit = (event: React.FormEvent) => {
      event.preventDefault();

      if (data.hero_video_source === 'upload' && videoFileSelected) {
         setStartVideoUpload(true);
         return;
      }

      submitHero();
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-5">
            <div>
               <h2 className="text-lg font-semibold">Public hero</h2>
               <p className="text-muted-foreground mt-1 text-sm">
                  These fields appear on this course’s public page. Leave one blank to use the category, title, short description, banner, or the preview from the Media tab.
               </p>
            </div>

            <div>
               <Label htmlFor="hero_eyebrow">Eyebrow</Label>
               <Input
                  id="hero_eyebrow"
                  value={data.hero_eyebrow}
                  onChange={(event) => setData('hero_eyebrow', event.target.value)}
                  placeholder="Learn a high-demand skill"
                  maxLength={80}
               />
               <InputError message={errors.hero_eyebrow} />
            </div>

            <div>
               <Label htmlFor="hero_headline">Headline</Label>
               <Textarea
                  id="hero_headline"
                  rows={3}
                  value={data.hero_headline}
                  onChange={(event) => setData('hero_headline', event.target.value)}
                  placeholder={course.title}
                  maxLength={200}
               />
               <InputError message={errors.hero_headline} />
            </div>

            <div>
               <Label htmlFor="hero_highlights">Words to highlight</Label>
               <Textarea
                  id="hero_highlights"
                  rows={3}
                  value={data.hero_highlights}
                  onChange={(event) => setData('hero_highlights', event.target.value)}
                  placeholder={'One phrase per line, or separate phrases with commas'}
               />
               <p className="text-muted-foreground mt-1 text-xs">Each phrase must appear in the headline. Matching words use the accent color on the public page.</p>
               <InputError message={errors.hero_highlights} />
               {missing.length > 0 ? (
                  <p className="mt-2 text-sm text-amber-700">
                     These phrases are not in the headline, so they will not be highlighted: {missing.join(', ')}.
                  </p>
               ) : null}
            </div>

            <div className="rounded-md border p-4">
               <p className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">Preview</p>
               <p className="text-accent mt-3 text-xs font-extrabold tracking-[0.12em] uppercase">
                  {data.hero_eyebrow.trim() || course.course_category?.title || 'Category'}
               </p>
               <p className="mt-2 text-2xl font-semibold">
                  {heroHeadlineParts(displayedHeadline, previewPhrases).map((part, index) => (
                     <span key={`${part.text}-${index}`} className={part.accent ? 'text-accent' : undefined}>
                        {part.text}
                     </span>
                  ))}
               </p>
               <p className="text-muted-foreground mt-2 text-sm">{data.hero_paragraph.trim() || course.short_description}</p>
            </div>

            <div>
               <Label htmlFor="hero_paragraph">Paragraph</Label>
               <Textarea
                  id="hero_paragraph"
                  rows={5}
                  value={data.hero_paragraph}
                  onChange={(event) => setData('hero_paragraph', event.target.value)}
                  placeholder={course.short_description}
                  maxLength={2000}
               />
               <InputError message={errors.hero_paragraph} />
            </div>

            <div>
               <Label htmlFor="hero_photo">Hero photo</Label>
               <Input
                  id="hero_photo"
                  name="hero_photo"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                  onChange={(event) => onHandleChange(event, setData, setPhotoPreview)}
               />
               <p className="text-muted-foreground mt-1 text-xs">JPG, PNG, or WebP. Maximum 100 MB. Leave this empty to keep the current photo.</p>
               <InputError message={errors.hero_photo} />
               {photoPreview ? <img src={photoPreview} alt="" className="mt-3 aspect-video w-full max-w-md rounded-md object-cover" /> : null}
            </div>

            <div className="space-y-3">
               <div>
                  <Label>Preview video</Label>
                  <div className="mt-2 flex gap-2" role="group" aria-label="Preview video source">
                     <Button
                        type="button"
                        size="sm"
                        variant={data.hero_video_source === 'link' ? 'default' : 'outline'}
                        aria-pressed={data.hero_video_source === 'link'}
                        onClick={() => setData('hero_video_source', 'link')}
                     >
                        YouTube or Vimeo
                     </Button>
                     <Button
                        type="button"
                        size="sm"
                        variant={data.hero_video_source === 'upload' ? 'default' : 'outline'}
                        aria-pressed={data.hero_video_source === 'upload'}
                        onClick={() => setData('hero_video_source', 'upload')}
                     >
                        Upload
                     </Button>
                  </div>
               </div>

               {data.hero_video_source === 'link' ? (
                  <div>
                     <Label htmlFor="hero_video_url">Video link</Label>
                     <Input
                        id="hero_video_url"
                        value={data.hero_video_url}
                        onChange={(event) => setData('hero_video_url', event.target.value)}
                        placeholder="https://www.youtube.com/watch?v="
                     />
                     <p className="text-muted-foreground mt-1 text-xs">Leave this empty to use the preview already saved on the Media tab.</p>
                     <InputError message={errors.hero_video_url} />
                  </div>
               ) : (
                  <div>
                     <Label htmlFor="hero-video">Video file</Label>
                     <ChunkedUploaderInput
                        isSubmit={startVideoUpload}
                        courseId={course.id}
                        filetype="video"
                        accept=".mp4,.webm,.mov,video/mp4,video/webm,video/quicktime"
                        acceptError="Upload an MP4, WebM, or MOV video."
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
                           setData('hero_video_stored', fileData.file_url);
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
                     <p className="text-muted-foreground mt-1 text-xs">MP4, WebM, or MOV. Maximum 5 GB. Leave this empty to keep the current video.</p>
                     <InputError message={errors.hero_video_stored} />
                     {videoPreview ? <video src={videoPreview} controls className="mt-3 aspect-video w-full max-w-md rounded-md bg-black" /> : null}
                  </div>
               )}
            </div>

            <LoadingButton loading={processing || startVideoUpload}>Save public hero</LoadingButton>
         </form>
      </Card>
   );
};

export default Hero;
