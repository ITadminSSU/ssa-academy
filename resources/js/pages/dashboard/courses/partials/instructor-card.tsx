import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CourseUpdateProps } from '../update';

const InstructorCard = () => {
   const { course } = usePage<CourseUpdateProps>().props;
   const accountName = course.instructor?.user?.name?.trim() || '';
   const designation = course.instructor?.designation?.trim() || 'Trainer';
   const accountHeading = accountName ? `${accountName}, ${designation}` : designation;
   const accountPhoto = course.instructor?.user?.photo || '';
   const accountBiography = course.instructor?.biography?.trim() || '';
   const [photoInputKey, setPhotoInputKey] = useState(0);
   const [selectedPreview, setSelectedPreview] = useState('');

   const { data, setData, post, processing, errors } = useForm({
      tab: 'instructor-card',
      instructor_card_name: course.instructor_card_name ?? '',
      instructor_card_biography: course.instructor_card_biography ?? '',
      instructor_card_photo: null as File | null,
      instructor_card_remove_photo: false,
   });

   const savedPhoto = data.instructor_card_remove_photo ? '' : course.instructor_card_photo || '';
   const preview = selectedPreview || savedPhoto || accountPhoto;

   const syncSavedCourse = (page: { props: unknown }) => {
      const next = (page.props as CourseUpdateProps).course;

      setSelectedPreview('');
      setPhotoInputKey((key) => key + 1);
      setData({
         tab: 'instructor-card',
         instructor_card_name: next.instructor_card_name ?? '',
         instructor_card_biography: next.instructor_card_biography ?? '',
         instructor_card_photo: null,
         instructor_card_remove_photo: false,
      });
   };

   const handleSubmit = (event: React.FormEvent) => {
      event.preventDefault();

      post(route('courses.update', { id: course.id }), {
         forceFormData: true,
         preserveScroll: true,
         onSuccess: syncSavedCourse,
      });
   };

   return (
      <Card className="p-4 sm:p-6">
         <form onSubmit={handleSubmit} className="space-y-5">
            <div>
               <h2 className="text-lg font-semibold">Instructor card</h2>
               <p className="text-muted-foreground mt-1 text-sm">
                  These fields appear on this course’s instructor card. Leave the name or biography blank to use the instructor account. Leave the
                  photo empty to keep the current one.
               </p>
            </div>

            <div>
               <Label htmlFor="instructor_card_name">Display name</Label>
               <Input
                  id="instructor_card_name"
                  name="instructor_card_name"
                  value={data.instructor_card_name}
                  onChange={(event) => setData('instructor_card_name', event.target.value)}
                  placeholder={accountHeading}
                  maxLength={255}
               />
               <p className="text-muted-foreground mt-1 text-xs">Shown in the navy pill in uppercase. This does not rename the instructor account.</p>
               <InputError message={errors.instructor_card_name} />
            </div>

            <div>
               <Label htmlFor="instructor_card_biography">Biography</Label>
               <Textarea
                  id="instructor_card_biography"
                  name="instructor_card_biography"
                  rows={8}
                  value={data.instructor_card_biography}
                  onChange={(event) => setData('instructor_card_biography', event.target.value)}
                  placeholder={accountBiography || 'Write the biography for this course'}
                  maxLength={5000}
               />
               <p className="text-muted-foreground mt-1 text-xs">Put a blank line between paragraphs. The rating stays the instructor rating.</p>
               <InputError message={errors.instructor_card_biography} />
            </div>

            <div>
               <Label htmlFor="instructor_card_photo">Photo</Label>
               <Input
                  key={photoInputKey}
                  id="instructor_card_photo"
                  name="instructor_card_photo"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                  onChange={(event) => {
                     const file = event.target.files?.[0] ?? null;
                     setData((current) => ({
                        ...current,
                        instructor_card_remove_photo: false,
                        instructor_card_photo: file,
                     }));
                     setSelectedPreview(file ? URL.createObjectURL(file) : '');
                  }}
               />
               <p className="text-muted-foreground mt-1 text-xs">JPG, PNG, or WebP. Maximum 100 MB. Leave this empty to keep the current photo.</p>
               <InputError message={errors.instructor_card_photo} />
               {course.instructor_card_photo ? (
                  <label className="mt-3 flex items-center gap-2 text-sm">
                     <input
                        type="checkbox"
                        checked={data.instructor_card_remove_photo}
                        onChange={(event) => {
                           const remove = event.target.checked;
                           setData((current) => ({
                              ...current,
                              instructor_card_remove_photo: remove,
                              instructor_card_photo: remove ? null : current.instructor_card_photo,
                           }));
                           if (remove) {
                              setSelectedPreview('');
                              setPhotoInputKey((key) => key + 1);
                           }
                        }}
                     />
                     <span>Remove this photo and use the instructor account photo</span>
                  </label>
               ) : null}
               {preview ? <img src={preview} alt="" className="mt-3 h-40 w-40 rounded-full object-cover" /> : null}
            </div>

            <LoadingButton loading={processing}>Save instructor card</LoadingButton>
         </form>
      </Card>
   );
};

export default InstructorCard;
