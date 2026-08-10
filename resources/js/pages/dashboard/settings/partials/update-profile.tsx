import AvatarCropDialog from '@/components/avatar-crop-dialog';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import TagInput from '@/components/tag-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useAvatarCrop } from '@/hooks/use-avatar-crop';
import { onHandleChange } from '@/lib/inertia';
import { SharedData } from '@/types/global';
import { useForm, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';

interface SocialLink {
   host: string;
   profile_link: string;
}

type SocialLinksMap = {
   website: string;
   github: string;
   twitter: string;
   linkedin: string;
};

const UpdateProfile = ({ instructor }: { instructor: Instructor }) => {
   const { auth, errors, translate } = usePage<SharedData>().props;
   const { button, common } = translate;
   const user = auth.user;
   const [userPhoto, setUserPhoto] = useState(user.photo);
   const [removePhoto, setRemovePhoto] = useState(false);

   const [socialLinks, setSocialLinks] = useState<SocialLinksMap>({
      website: '',
      github: '',
      twitter: '',
      linkedin: '',
   });

   const parseSocialLinks = useCallback((socialLinksData: unknown) => {
      try {
         if (!socialLinksData) return;

         const links: SocialLink[] = typeof socialLinksData === 'string' ? JSON.parse(socialLinksData) : (socialLinksData as SocialLink[]);

         const linkMap: SocialLinksMap = {
            website: '',
            github: '',
            twitter: '',
            linkedin: '',
         };

         links.forEach((link) => {
            if (link.host in linkMap) {
               linkMap[link.host as keyof SocialLinksMap] = link.profile_link;
            }
         });

         setSocialLinks(linkMap);
      } catch (error) {
         toast.error('Failed to parse social links');
      }
   }, []);

   useEffect(() => {
      parseSocialLinks(user.social_links);
   }, [user.social_links, parseSocialLinks]);

   const formatSocialLinks = useCallback((links: SocialLinksMap): any[] => {
      const formattedLinks = Object.entries(links)
         .filter(([_, value]) => value)
         .map(([host, profile_link]) => ({ host, profile_link }));

      return formattedLinks;
   }, []);

   const updateSocialLink = useCallback((platform: keyof SocialLinksMap, value: string) => {
      setSocialLinks((prev) => ({
         ...prev,
         [platform]: value,
      }));
   }, []);

   // Parse the options and answers if they're strings
   const initialOptions = instructor?.skills ? (typeof instructor.skills === 'string' ? JSON.parse(instructor.skills) : instructor.skills) : [];

   const { data, post, setData, processing } = useForm({
      name: user.name || '',
      photo: null as File | null,
      social_links: [] as any[],
      user_id: user.id,
      skills: initialOptions,
      designation: instructor?.designation || '',
      biography: instructor?.biography || '',
      resume: null,
   });

   useEffect(() => {
      if (!data.photo && !removePhoto) {
         setUserPhoto(user.photo);
      }
   }, [user.photo, data.photo, removePhoto]);

   useEffect(() => {
      setData('social_links', formatSocialLinks(socialLinks));
   }, [socialLinks, formatSocialLinks, setData]);

   const handlePhotoReady = useCallback(
      (file: File, previewUrl: string) => {
         setRemovePhoto(false);
         setData('photo', file);
         setUserPhoto(previewUrl);
      },
      [setData],
   );

   const handleRemovePhoto = useCallback(() => {
      setData('photo', null);
      setUserPhoto(null);
      setRemovePhoto(true);
   }, [setData]);

   const { fileInputRef, cropOpen, cropImageSrc, handleFileSelect, handleCropCancel, handleCropApply } = useAvatarCrop({
      onPhotoReady: handlePhotoReady,
      invalidTypeMessage: common.invalid_image_type,
      tooLargeMessage: common.image_too_large,
   });

   const handleSubmit = (e: React.FormEvent) => {
      e.preventDefault();
      post(route('account.profile'), {
         forceFormData: true,
         preserveScroll: true,
         transform: (form) => ({
            ...form,
            photo: removePhoto ? null : form.photo,
            remove_photo: removePhoto ? 1 : 0,
         }),
         onSuccess: () => {
            setData('photo', null);
            setRemovePhoto(false);
         },
      });
   };

   const showRemove = Boolean(userPhoto || user.photo);

   return (
      <>
         <form onSubmit={handleSubmit} className="bg-card space-y-6 rounded-lg border p-6 shadow">
            <div className="flex flex-col items-center gap-6 sm:flex-row">
               <div className="flex w-full flex-col items-center space-y-3 text-center md:max-w-[160px]">
                  <div className="relative mb-4 h-[100px] w-[100px]">
                     {userPhoto ? (
                        <img
                           alt={`${user.name}'s profile`}
                           src={userPhoto}
                           className="h-[100px] w-[100px] rounded-full object-cover"
                           onError={(event) => {
                              event.currentTarget.src = '/assets/icons/avatar.png';
                           }}
                        />
                     ) : (
                        <div className="h-[100px] w-[100px] rounded-full bg-muted"></div>
                     )}

                     <label htmlFor="formFileSm" className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-muted">
                           <Camera className="h-6 w-6 text-muted-foreground" />
                        </div>
                     </label>
                     <input
                        ref={fileInputRef}
                        hidden
                        type="file"
                        id="formFileSm"
                        name="photo"
                        accept="image/jpeg,image/png,image/jpg"
                        onChange={handleFileSelect}
                     />
                  </div>

                  {showRemove && !removePhoto && (
                     <Button type="button" variant="ghost" size="sm" className="text-destructive hover:text-destructive" onClick={handleRemovePhoto}>
                        {common.remove_photo}
                     </Button>
                  )}

                  <small className="text-xs text-muted-foreground">Allowed: JPG, JPEG, PNG. Maximum 15MB (saved as 512×512).</small>

                  {errors.photo && <p className="mt-1 text-sm text-red-500">{errors.photo}</p>}
               </div>

               <div className="grid w-full grid-cols-1 gap-6 md:grid-cols-2">
                  <div>
                     <Label>Website</Label>
                     <Input
                        type="url"
                        name="website"
                        value={socialLinks.website}
                        onChange={(e) => updateSocialLink('website', e.target.value)}
                        className="mt-1"
                        placeholder="https://example.com"
                     />
                  </div>

                  <div>
                     <Label>GitHub</Label>
                     <Input
                        type="url"
                        name="github"
                        value={socialLinks.github}
                        onChange={(e) => updateSocialLink('github', e.target.value)}
                        className="mt-1"
                        placeholder="https://github.com/my-profile"
                     />
                  </div>

                  <div>
                     <Label>Twitter</Label>
                     <Input
                        type="url"
                        name="twitter"
                        value={socialLinks.twitter}
                        onChange={(e) => updateSocialLink('twitter', e.target.value)}
                        className="mt-1"
                        placeholder="https://twitter.com/my-profile"
                     />
                  </div>

                  <div>
                     <Label htmlFor="linkedin">LinkedIn</Label>
                     <Input
                        id="linkedin"
                        name="linkedin"
                        value={socialLinks.linkedin}
                        onChange={(e) => updateSocialLink('linkedin', e.target.value)}
                        className="mt-1"
                        placeholder="https://linkedin.com/my-profile"
                     />
                  </div>
               </div>
            </div>

            <div>
               <Label htmlFor="name">Full Name</Label>
               <Input id="name" name="name" value={data.name} onChange={(e) => onHandleChange(e, setData)} className="mt-1" placeholder="John Doe" />
               <InputError message={errors.name} />
            </div>

            {((user.role === 'admin' && user.instructor_id) || user.role === 'instructor') && (
               <>
                  <div>
                     <Label>Designation</Label>
                     <Input name="designation" value={data.designation} onChange={(e) => onHandleChange(e, setData)} placeholder="Software Engineer" />
                     <InputError message={errors.designation} />
                  </div>
                  {user.role === 'instructor' && (
                     <div>
                        <Label>Resume</Label>
                        <Input readOnly type="file" name="resume" onChange={(e) => onHandleChange(e, setData)} />
                        <InputError message={errors.resume} />
                     </div>
                  )}
                  <div>
                     <Label>Skills</Label>
                     <TagInput defaultTags={data.skills} placeholder="Enter the skills as a tag" onChange={(values: any) => setData('skills', values)} />
                  </div>
                  <div className="pb-3">
                     <Label>Biography</Label>
                     <Textarea
                        rows={5}
                        required
                        name="biography"
                        value={data.biography}
                        onChange={(e) => onHandleChange(e, setData)}
                        placeholder="Write about yourself"
                     />
                     <InputError message={errors.biography} />
                  </div>
               </>
            )}

            <div className="col-span-full pt-2">
               <LoadingButton loading={processing}>Save Changes</LoadingButton>
            </div>
         </form>

         <AvatarCropDialog
            open={cropOpen}
            imageSrc={cropImageSrc}
            onCancel={handleCropCancel}
            onApply={handleCropApply}
            title={common.crop_photo_title}
            description={common.crop_photo_description}
            zoomLabel={common.zoom}
            applyLabel={button.apply}
            cancelLabel={button.cancel}
            cropFailedMessage={common.crop_image_failed}
         />
      </>
   );
};

export default UpdateProfile;
