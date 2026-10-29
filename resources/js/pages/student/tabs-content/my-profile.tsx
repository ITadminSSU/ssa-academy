import AvatarCropDialog from '@/components/avatar-crop-dialog';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import ProfileSetupBanner from '@/components/profile-setup-banner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAvatarCrop } from '@/hooks/use-avatar-crop';
import { onHandleChange } from '@/lib/inertia';
import { SharedData } from '@/types/global';
import { StudentDashboardProps } from '@/types/page';
import { useForm, usePage } from '@inertiajs/react';
import { Camera } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

interface SocialLink {
   host: string;
   profile_link: string;
}

type SocialLinksMap = {
   website: string;
   facebook: string;
   twitter: string;
   linkedin: string;
};

const MyProfile = () => {
   const { auth, errors, translate, profileAnswers, profileSetup, profileDocuments } = usePage<StudentDashboardProps>().props;
   const { button, input, common } = translate;
   const user = auth.user;
   const [userPhoto, setUserPhoto] = useState(user.photo);
   const photoFileRef = useRef<File | null>(null);
   const cvInputRef = useRef<HTMLInputElement>(null);
   const idInputRef = useRef<HTMLInputElement>(null);
   const [removePhoto, setRemovePhoto] = useState(false);
   const [socialLinks, setSocialLinks] = useState<SocialLinksMap>({
      website: '',
      facebook: '',
      twitter: '',
      linkedin: '',
   });

   const parseSocialLinks = useCallback((socialLinksData: unknown) => {
      try {
         if (!socialLinksData) return;

         const links: SocialLink[] = typeof socialLinksData === 'string' ? JSON.parse(socialLinksData) : (socialLinksData as SocialLink[]);

         const linkMap: SocialLinksMap = {
            website: '',
            facebook: '',
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
         // Failed to parse social links
      }
   }, []);

   useEffect(() => {
      parseSocialLinks(user.social_links);
   }, [user.social_links, parseSocialLinks]);

   const formatSocialLinks = useCallback((links: SocialLinksMap): string => {
      const formattedLinks = Object.entries(links)
         .filter(([_, value]) => value)
         .map(([host, profile_link]) => ({ host, profile_link }));

      return JSON.stringify(formattedLinks);
   }, []);

   const updateSocialLink = useCallback((platform: keyof SocialLinksMap, value: string) => {
      setSocialLinks((prev) => ({
         ...prev,
         [platform]: value,
      }));
   }, []);

   const answers = profileAnswers;
   const professionalTypes = profileSetup?.professionalTypes ?? [];
   const softwareOptions = profileSetup?.estimatingSoftwareOptions ?? [];
   const experienceOptions = profileSetup?.constructionExperienceOptions ?? [];

   const { data, post, setData, processing } = useForm({
      name: user.name || '',
      photo: null as File | null,
      social_links: null as string | null,
      professional_type_id: answers?.professional_type_id ?? '',
      professional_type_other: answers?.professional_type_other ?? '',
      estimating_software: answers?.estimating_software ?? ([] as string[]),
      estimating_software_other: answers?.estimating_software_other ?? '',
      construction_experience: answers?.construction_experience ?? '',
      worked_as_construction_va: (answers?.worked_as_construction_va === null || answers?.worked_as_construction_va === undefined
         ? ''
         : answers.worked_as_construction_va
           ? '1'
           : '0') as '' | '1' | '0',
      referred_by: answers?.referred_by ?? '',
      referrer_is_employee: (answers?.referrer_is_employee === null || answers?.referrer_is_employee === undefined
         ? ''
         : answers.referrer_is_employee
           ? '1'
           : '0') as '' | '1' | '0',
      cv_resume: null as File | null,
      id_document: null as File | null,
   });

   const selectedProfessionalType = professionalTypes.find((type) => type.id.toString() === data.professional_type_id);
   const isOtherSelected = selectedProfessionalType?.name.toLowerCase() === 'other';
   const hasOthersSoftware = data.estimating_software.includes('Others');
   const hasNoneSoftware = data.estimating_software.includes('None');

   const toggleSoftware = (option: string, checked: boolean) => {
      if (option === 'None' && checked) {
         setData({
            ...data,
            estimating_software: ['None'],
            estimating_software_other: '',
         });
         return;
      }

      let next = data.estimating_software.filter((item) => item !== 'None');

      if (checked) {
         next = [...next, option];
      } else {
         next = next.filter((item) => item !== option);
      }

      setData({
         ...data,
         estimating_software: next,
         estimating_software_other: next.includes('Others') ? data.estimating_software_other : '',
      });
   };

   useEffect(() => {
      if (!data.photo && !photoFileRef.current && !removePhoto) {
         setUserPhoto(user.photo);
      }
   }, [user.photo, data.photo, removePhoto]);

   useEffect(() => {
      setData('social_links', formatSocialLinks(socialLinks));
   }, [socialLinks, formatSocialLinks, setData]);

   const handlePhotoReady = useCallback(
      (file: File, previewUrl: string) => {
         photoFileRef.current = file;
         setRemovePhoto(false);
         setData('photo', file);
         setUserPhoto(previewUrl);
      },
      [setData],
   );

   const handleRemovePhoto = useCallback(() => {
      photoFileRef.current = null;
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
      const photo = photoFileRef.current ?? data.photo;

      post(route('student.profile.update'), {
         forceFormData: true,
         preserveScroll: true,
         transform: (form) => {
            const payload: Record<string, unknown> = {
               ...form,
               photo: removePhoto ? null : photo,
               remove_photo: removePhoto ? 1 : 0,
               social_links: form.social_links ?? formatSocialLinks(socialLinks),
               worked_as_construction_va: form.worked_as_construction_va === '' ? '' : form.worked_as_construction_va === '1',
               referrer_is_employee: form.referred_by.trim() && form.referrer_is_employee !== '' ? form.referrer_is_employee === '1' : '',
            };

            if (!form.cv_resume) {
               delete payload.cv_resume;
            }

            if (!form.id_document) {
               delete payload.id_document;
            }

            return payload;
         },
         onSuccess: (page) => {
            photoFileRef.current = null;
            setRemovePhoto(false);
            setData((form) => ({
               ...form,
               photo: null,
               cv_resume: null,
               id_document: null,
            }));
            if (cvInputRef.current) {
               cvInputRef.current.value = '';
            }
            if (idInputRef.current) {
               idInputRef.current.value = '';
            }
            setUserPhoto((page.props as SharedData).auth?.user?.photo ?? null);
            toast.success('Profile updated successfully');
         },
         onError: (formErrors) => {
            const message =
               formErrors.photo ||
               formErrors.name ||
               formErrors.cv_resume ||
               formErrors.id_document ||
               formErrors.professional_type_id ||
               formErrors.estimating_software ||
               formErrors.construction_experience ||
               formErrors.worked_as_construction_va ||
               formErrors.referrer_is_employee ||
               'Failed to update profile';
            toast.error(message);
         },
      });
   };

   const showRemove = Boolean(userPhoto || photoFileRef.current || user.photo);

   return (
      <>
         <div className="mb-6">
            <ProfileSetupBanner onProfile />
         </div>
         <form onSubmit={handleSubmit} className="bg-card grid grid-cols-1 gap-6 rounded-lg p-6 md:grid-cols-2">
            <div className="col-span-full space-y-2">
               <div className="border-border h-[150px] w-[150px] rounded-full border border-dashed p-1.5">
                  <div className="border-border relative h-full w-full overflow-hidden rounded-full border">
                     <img
                        alt={`${auth.user.name}'s profile`}
                        src={userPhoto || '/assets/icons/avatar.png'}
                        className="h-full w-full content-center object-cover"
                        onError={(event) => {
                           event.currentTarget.src = '/assets/icons/avatar.png';
                        }}
                     />

                     <label
                        htmlFor="formFile"
                        className="text-primary-foreground absolute right-0 bottom-0 flex h-full w-full cursor-pointer flex-col items-center justify-center gap-2 bg-black/50 opacity-0 transition-opacity duration-300 hover:opacity-100"
                     >
                        <Camera className="h-7 w-7" />
                        <span className="text-xs">{button.upload}</span>
                     </label>

                     <input
                        ref={fileInputRef}
                        hidden
                        type="file"
                        id="formFile"
                        name="photo"
                        accept="image/jpeg,image/png,image/jpg"
                        onChange={handleFileSelect}
                     />
                  </div>
               </div>

               {showRemove && !removePhoto && (
                  <Button type="button" variant="ghost" size="sm" className="text-destructive hover:text-destructive px-0" onClick={handleRemovePhoto}>
                     {common.remove_photo}
                  </Button>
               )}

               {errors.photo && <p className="mt-1 text-sm text-red-500">{errors.photo}</p>}
            </div>

            <div className="space-y-4">
               <Label>{input.name}</Label>
               <Input type="text" name="name" value={data.name} onChange={(e) => onHandleChange(e, setData)} placeholder={input.full_name_placeholder} />
               <InputError message={errors.name} />
            </div>

            <div className="space-y-4">
               <Label>{input.website}</Label>
               <Input
                  type="url"
                  name="website"
                  value={socialLinks.website}
                  onChange={(e) => updateSocialLink('website', e.target.value)}
                  placeholder={input.https_placeholder}
               />
            </div>

            <div>
               <Label>{input.facebook}</Label>
               <Input
                  type="url"
                  value={socialLinks.facebook}
                  onChange={(e) => updateSocialLink('facebook', e.target.value)}
                  placeholder={input.https_placeholder}
               />
            </div>

            <div>
               <Label>{input.twitter}</Label>
               <Input
                  type="url"
                  value={socialLinks.twitter}
                  onChange={(e) => updateSocialLink('twitter', e.target.value)}
                  placeholder={input.https_placeholder}
               />
            </div>

            <div>
               <Label>{input.linkedin}</Label>
               <Input
                  type="url"
                  value={socialLinks.linkedin}
                  onChange={(e) => updateSocialLink('linkedin', e.target.value)}
                  placeholder={input.https_placeholder}
               />
            </div>

            <div className="col-span-full space-y-4 border-t pt-6">
               <div>
                  <h2 className="text-lg font-semibold">Documents</h2>
                  <p className="text-muted-foreground text-sm">
                     Upload your CV/resume and government ID. These stay on your account and are used to release course certificates. You can save this profile without them.
                  </p>
               </div>

               <div className="grid gap-4 md:grid-cols-2">
                  <div className="grid gap-2">
                     <Label htmlFor="cv_resume">CV / resume</Label>
                     {profileDocuments?.cv && (
                        <p className="text-sm">
                           Current file: {profileDocuments.cv.file_name}{' '}
                           <a href={profileDocuments.cv.view_url} target="_blank" rel="noreferrer" className="font-medium underline">
                              View
                           </a>
                        </p>
                     )}
                     <input
                        ref={cvInputRef}
                        id="cv_resume"
                        type="file"
                        accept=".pdf,.doc,.docx,application/pdf"
                        onChange={(event) => setData('cv_resume', event.target.files?.[0] ?? null)}
                     />
                     <p className="text-muted-foreground text-xs">PDF, DOC, or DOCX up to 10 MB. A new file replaces the saved one.</p>
                     <InputError message={errors.cv_resume} />
                  </div>

                  <div className="grid gap-2">
                     <Label htmlFor="id_document">Government ID</Label>
                     {profileDocuments?.id && (
                        <p className="text-sm">
                           Current file: {profileDocuments.id.file_name}{' '}
                           <a href={profileDocuments.id.view_url} target="_blank" rel="noreferrer" className="font-medium underline">
                              View
                           </a>
                        </p>
                     )}
                     <input
                        ref={idInputRef}
                        id="id_document"
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                        onChange={(event) => setData('id_document', event.target.files?.[0] ?? null)}
                     />
                     <p className="text-muted-foreground text-xs">JPG, PNG, WEBP, or PDF up to 10 MB. A new file replaces the saved one.</p>
                     <InputError message={errors.id_document} />
                  </div>
               </div>
            </div>

            <div id="profile-questions" className="col-span-full space-y-6 border-t pt-6">
               <div>
                  <h2 className="text-lg font-semibold">Background</h2>
                  <p className="text-muted-foreground text-sm">These answers stay on your profile and are used by the candidate record.</p>
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="professional_type_id">
                     Professional type <span className="text-destructive">*</span>
                  </Label>
                  <Select
                     value={data.professional_type_id || undefined}
                     onValueChange={(value) => {
                        const selected = professionalTypes.find((type) => type.id.toString() === value);
                        setData({
                           ...data,
                           professional_type_id: value,
                           professional_type_other: selected?.name.toLowerCase() === 'other' ? data.professional_type_other : '',
                        });
                     }}
                     disabled={processing}
                  >
                     <SelectTrigger id="professional_type_id">
                        <SelectValue placeholder="Select your professional type" />
                     </SelectTrigger>
                     <SelectContent>
                        {professionalTypes.map((type) => (
                           <SelectItem key={type.id} value={type.id.toString()}>
                              {type.name}
                           </SelectItem>
                        ))}
                     </SelectContent>
                  </Select>
                  <InputError message={errors.professional_type_id} />
               </div>

               {isOtherSelected && (
                  <div className="grid gap-2">
                     <Label htmlFor="professional_type_other">
                        Please specify your professional type <span className="text-destructive">*</span>
                     </Label>
                     <Input
                        id="professional_type_other"
                        value={data.professional_type_other}
                        onChange={(e) => setData('professional_type_other', e.target.value)}
                        disabled={processing}
                        placeholder="Enter your professional type"
                     />
                     <InputError message={errors.professional_type_other} />
                  </div>
               )}

               <fieldset className="space-y-3">
                  <legend className="text-sm font-medium">
                     Which estimating software have you used? <span className="text-destructive">*</span>
                  </legend>
                  <div className="grid gap-2 sm:grid-cols-2">
                     {softwareOptions.map((option) => {
                        const id = `software-${option}`;
                        const checked = data.estimating_software.includes(option);

                        return (
                           <div key={option} className="flex items-start gap-2">
                              <Checkbox
                                 id={id}
                                 checked={checked}
                                 disabled={processing || (hasNoneSoftware && option !== 'None')}
                                 onCheckedChange={(value) => toggleSoftware(option, value === true)}
                              />
                              <Label htmlFor={id} className="font-normal">
                                 {option}
                              </Label>
                           </div>
                        );
                     })}
                  </div>
                  {hasOthersSoftware && (
                     <Input
                        id="estimating_software_other"
                        value={data.estimating_software_other}
                        onChange={(e) => setData('estimating_software_other', e.target.value)}
                        disabled={processing}
                        placeholder="Please specify"
                     />
                  )}
                  <InputError message={errors.estimating_software || errors['estimating_software.0']} />
                  <InputError message={errors.estimating_software_other} />
               </fieldset>

               <fieldset className="space-y-3">
                  <legend className="text-sm font-medium">
                     Years of construction experience <span className="text-destructive">*</span>
                  </legend>
                  <RadioGroup
                     value={data.construction_experience}
                     onValueChange={(value) => setData('construction_experience', value)}
                     disabled={processing}
                     className="grid gap-2 sm:grid-cols-2"
                  >
                     {experienceOptions.map((option) => {
                        const id = `experience-${option}`;

                        return (
                           <div key={option} className="flex items-center gap-2">
                              <RadioGroupItem value={option} id={id} />
                              <Label htmlFor={id} className="font-normal">
                                 {option}
                              </Label>
                           </div>
                        );
                     })}
                  </RadioGroup>
                  <InputError message={errors.construction_experience} />
               </fieldset>

               <fieldset className="space-y-3">
                  <legend className="text-sm font-medium">
                     Have you worked as a construction virtual assistant? <span className="text-destructive">*</span>
                  </legend>
                  <RadioGroup
                     value={data.worked_as_construction_va}
                     onValueChange={(value) => setData('worked_as_construction_va', value as '1' | '0')}
                     disabled={processing}
                     className="flex gap-6"
                  >
                     <div className="flex items-center gap-2">
                        <RadioGroupItem value="1" id="va-yes" />
                        <Label htmlFor="va-yes" className="font-normal">
                           Yes
                        </Label>
                     </div>
                     <div className="flex items-center gap-2">
                        <RadioGroupItem value="0" id="va-no" />
                        <Label htmlFor="va-no" className="font-normal">
                           No
                        </Label>
                     </div>
                  </RadioGroup>
                  <InputError message={errors.worked_as_construction_va} />
               </fieldset>

               <div className="grid gap-2">
                  <Label htmlFor="referred_by">Who referred you?</Label>
                  <Input
                     id="referred_by"
                     value={data.referred_by}
                     onChange={(e) => {
                        const value = e.target.value;
                        setData({
                           ...data,
                           referred_by: value,
                           referrer_is_employee: value.trim() ? data.referrer_is_employee : '',
                        });
                     }}
                     disabled={processing}
                     placeholder="Optional"
                  />
                  <InputError message={errors.referred_by} />
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="referrer_is_employee">Is your referrer part of the SMARTSOURCING USA team?</Label>
                  <Select
                     value={data.referrer_is_employee || undefined}
                     onValueChange={(value) => setData('referrer_is_employee', value as '1' | '0')}
                     disabled={processing || !data.referred_by.trim()}
                  >
                     <SelectTrigger id="referrer_is_employee">
                        <SelectValue placeholder="Select an option" />
                     </SelectTrigger>
                     <SelectContent>
                        <SelectItem value="1">Yes</SelectItem>
                        <SelectItem value="0">No</SelectItem>
                     </SelectContent>
                  </Select>
                  <p className="text-muted-foreground text-xs">Required only after you enter a referrer name.</p>
                  <InputError message={errors.referrer_is_employee} />
               </div>
            </div>

            <div className="col-span-full flex items-center justify-end pt-2">
               <LoadingButton loading={processing} className="col-span-full">
                  {button.update}
               </LoadingButton>
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

export default MyProfile;
