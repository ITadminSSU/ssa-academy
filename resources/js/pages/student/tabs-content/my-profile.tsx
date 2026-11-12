import AvatarCropDialog from '@/components/avatar-crop-dialog';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import ProfileSetupBanner from '@/components/profile-setup-banner';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAvatarCrop } from '@/hooks/use-avatar-crop';
import { onHandleChange } from '@/lib/inertia';
import { SharedData } from '@/types/global';
import { StudentDashboardProps } from '@/types/page';
import { useForm, usePage } from '@inertiajs/react';
import { Briefcase, Camera, Check, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const CRITERIA_ASSESSMENT_URL = 'https://www.ondemandassessment.com/o/JB-EXX9G89Q8/landing?u=1313719';
const ESTIMATOR_APPLICATION_URL = 'https://smartsourcing-usa.breezy.hr/p/9396bbe98a9a-evt-construction-estimators';

const facebookLink = (links: { host: string; profile_link: string }[] | null | undefined) => {
   if (!Array.isArray(links)) {
      return '';
   }

   return links.find((link) => link.host === 'facebook')?.profile_link ?? '';
};

const MyProfile = () => {
   const { auth, errors, translate, profileDocuments } = usePage<StudentDashboardProps>().props;
   const { button, common } = translate;
   const user = auth.user;
   const [userPhoto, setUserPhoto] = useState(user.photo);
   const photoFileRef = useRef<File | null>(null);
   const cvInputRef = useRef<HTMLInputElement>(null);
   const idInputRef = useRef<HTMLInputElement>(null);
   const assessmentInputRef = useRef<HTMLInputElement>(null);
   const [removePhoto, setRemovePhoto] = useState(false);

   const { data, post, setData, processing } = useForm({
      given_name: user.given_name ?? user.name.split(' ')[0] ?? '',
      last_name: user.given_name != null || user.last_name != null ? (user.last_name ?? '') : user.name.split(' ').slice(1).join(' '),
      photo: null as File | null,
      facebook: facebookLink(user.social_links),
      has_us_estimating_experience: user.has_us_estimating_experience === true,
      cv_resume: null as File | null,
      id_document: null as File | null,
      criteria_assessment: null as File | null,
   });

   useEffect(() => {
      if (window.location.hash === '#documents') {
         document.getElementById('documents')?.scrollIntoView();
      }
   }, []);

   useEffect(() => {
      if (!data.photo && !photoFileRef.current && !removePhoto) {
         setUserPhoto(user.photo);
      }
   }, [user.photo, data.photo, removePhoto]);

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
               facebook: form.facebook.trim(),
               has_us_estimating_experience: form.has_us_estimating_experience ? 1 : 0,
            };

            if (!form.cv_resume) {
               delete payload.cv_resume;
            }

            if (!form.id_document) {
               delete payload.id_document;
            }

            if (!form.criteria_assessment) {
               delete payload.criteria_assessment;
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
               criteria_assessment: null,
            }));
            if (cvInputRef.current) {
               cvInputRef.current.value = '';
            }
            if (idInputRef.current) {
               idInputRef.current.value = '';
            }
            if (assessmentInputRef.current) {
               assessmentInputRef.current.value = '';
            }
            setUserPhoto((page.props as SharedData).auth?.user?.photo ?? null);
            toast.success('Profile updated successfully');
         },
         onError: (formErrors) => {
            const message =
               formErrors.photo ||
               formErrors.given_name ||
               formErrors.last_name ||
               formErrors.facebook ||
               formErrors.cv_resume ||
               formErrors.id_document ||
               formErrors.criteria_assessment ||
               formErrors.has_us_estimating_experience ||
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
         <form onSubmit={handleSubmit} className="bg-card space-y-8 rounded-lg p-6">
            <div className="grid grid-cols-1 gap-6 md:grid-cols-[5.5rem_1fr_1fr]">
               <div className="space-y-2 md:row-span-2">
                  <div className="border-border h-20 w-20 rounded-full border border-dashed p-1">
                     <div className="border-border relative h-full w-full overflow-hidden rounded-full border">
                        <img
                           alt={`${auth.user.name}'s profile`}
                           src={userPhoto || '/assets/icons/avatar.png'}
                           className="h-full w-full object-cover"
                           onError={(event) => {
                              event.currentTarget.src = '/assets/icons/avatar.png';
                           }}
                        />

                        <label
                           htmlFor="formFile"
                           className="text-primary-foreground absolute right-0 bottom-0 flex h-full w-full cursor-pointer flex-col items-center justify-center gap-1 bg-black/50 opacity-0 transition-opacity duration-300 hover:opacity-100"
                        >
                           <Camera className="h-5 w-5" />
                           <span className="text-[10px]">{button.upload}</span>
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
                     <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        className="text-destructive hover:text-destructive h-auto px-0"
                        onClick={handleRemovePhoto}
                     >
                        {common.remove_photo}
                     </Button>
                  )}

                  {errors.photo && <p className="mt-1 text-sm text-red-500">{errors.photo}</p>}
               </div>

               <div className="space-y-2">
                  <Label htmlFor="given_name">Given name</Label>
                  <Input
                     id="given_name"
                     type="text"
                     name="given_name"
                     required
                     autoComplete="given-name"
                     value={data.given_name}
                     onChange={(e) => onHandleChange(e, setData)}
                     placeholder="Alexis"
                  />
                  <InputError message={errors.given_name} />
               </div>

               <div className="space-y-2">
                  <Label htmlFor="last_name">Last name</Label>
                  <Input
                     id="last_name"
                     type="text"
                     name="last_name"
                     autoComplete="family-name"
                     value={data.last_name}
                     onChange={(e) => onHandleChange(e, setData)}
                     placeholder="Pacapac"
                  />
                  <InputError message={errors.last_name} />
               </div>

               <div className="space-y-2">
                  <Label htmlFor="email">Email Address</Label>
                  <Input id="email" type="email" value={user.email} readOnly autoComplete="email" />
               </div>

               <div className="space-y-2">
                  <Label htmlFor="facebook">Facebook</Label>
                  <Input
                     id="facebook"
                     type="url"
                     name="facebook"
                     value={data.facebook}
                     onChange={(e) => setData('facebook', e.target.value)}
                     placeholder="https://example.com"
                  />
                  <InputError message={errors.facebook} />
               </div>
            </div>

            <div id="documents" className="scroll-mt-24 space-y-4 border-t pt-6">
               <div>
                  <h2 className="text-lg font-semibold">Documents</h2>
                  <p className="text-muted-foreground text-sm">
                     Upload your CV/resume and government ID. These stay on your account and are used to release course certificates. You can save
                     this profile without them.
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

            <div className="space-y-4">
               <a
                  href={CRITERIA_ASSESSMENT_URL}
                  target="_blank"
                  rel="noreferrer"
                  className="mx-auto flex w-full max-w-3xl flex-col items-center rounded-full bg-[#012447] px-6 py-3 text-center font-semibold tracking-wide text-white uppercase"
               >
                  <span>Criteria Assessment</span>
                  <span className="text-xs font-medium">[ Click this link to take a personality test ]</span>
               </a>
               <p className="text-muted-foreground text-center text-sm">
                  Discover your strengths, work preferences, and how you approach different situations through the Criteria Assessment.
               </p>
               <div className="flex flex-col items-center gap-2 text-sm">
                  <div className="flex flex-wrap items-center justify-center gap-3 text-[#1d4ed8]">
                     <span>Upload your result here</span>
                     <label htmlFor="criteria_assessment" className="inline-flex cursor-pointer items-center gap-1 font-medium">
                        <Upload className="h-4 w-4" />
                        Upload File
                     </label>
                  </div>
                  <input
                     ref={assessmentInputRef}
                     id="criteria_assessment"
                     hidden
                     type="file"
                     accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                     onChange={(event) => setData('criteria_assessment', event.target.files?.[0] ?? null)}
                  />
                  {data.criteria_assessment && <p className="text-sm">{data.criteria_assessment.name}</p>}
                  {profileDocuments?.assessment && (
                     <p className="text-sm">
                        Current file: {profileDocuments.assessment.file_name}{' '}
                        <a href={profileDocuments.assessment.view_url} target="_blank" rel="noreferrer" className="font-medium underline">
                           View
                        </a>
                     </p>
                  )}
                  <p className="text-muted-foreground text-center text-xs">
                     PDF, DOC, DOCX, JPG, PNG, or WEBP up to 10 MB. A new file replaces the saved one.
                  </p>
                  <InputError message={errors.criteria_assessment} />
               </div>
            </div>

            <div className="flex items-start gap-3">
               <Checkbox
                  id="has_us_estimating_experience"
                  checked={data.has_us_estimating_experience}
                  disabled={processing}
                  onCheckedChange={(value) => setData('has_us_estimating_experience', value === true)}
               />
               <Label htmlFor="has_us_estimating_experience" className="text-xs leading-5 font-semibold tracking-wide uppercase">
                  Do you have at least 1 year of U.S. estimating experience in roofing, siding, or drywall, or have you completed at least 50 U.S.
                  projects?
               </Label>
            </div>
            <InputError message={errors.has_us_estimating_experience} />

            <div className="space-y-6">
               <div className="flex items-center gap-4">
                  <span className="h-px flex-1 bg-[#f5b400]" />
                  <h2 className="text-center text-xl font-bold tracking-wide text-[#012447] uppercase md:text-2xl">
                     Ready for a <span className="text-[#f5b400]">direct</span> opportunity?
                  </h2>
                  <span className="h-px flex-1 bg-[#f5b400]" />
               </div>
               <p className="text-center text-sm text-[#012447]">Submit your profile for consideration for U.S. construction opportunities.</p>

               <div className="rounded-2xl border-2 border-[#012447] bg-[#fff7e8] p-6 md:p-8">
                  <div className="flex flex-col gap-6 md:flex-row md:items-start">
                     <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-xl border-2 border-[#012447] bg-white">
                        <Briefcase className="h-12 w-12 text-[#012447]" />
                     </div>
                     <div className="space-y-3 text-[#012447]">
                        <div>
                           <h3 className="text-xl font-bold tracking-wide uppercase">Direct Estimator Path</h3>
                           <p className="text-sm font-semibold tracking-wide uppercase">For career-focused estimators</p>
                        </div>
                        <div>
                           <p className="font-semibold">What you'll gain:</p>
                           <ul className="mt-2 space-y-2 text-sm">
                              <li className="flex items-center gap-2">
                                 <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#f5b400] text-[#012447]">
                                    <Check className="h-3 w-3" strokeWidth={3} />
                                 </span>
                                 Direct hiring consideration
                              </li>
                              <li className="flex items-center gap-2">
                                 <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#f5b400] text-[#012447]">
                                    <Check className="h-3 w-3" strokeWidth={3} />
                                 </span>
                                 Access to relevant job openings
                              </li>
                           </ul>
                        </div>
                     </div>
                  </div>
                  <a
                     href={ESTIMATOR_APPLICATION_URL}
                     target="_blank"
                     rel="noreferrer"
                     className="mt-6 flex w-full items-center justify-center rounded-full bg-[#f5b400] px-6 py-3 text-center text-sm font-bold tracking-wide text-[#012447] uppercase"
                  >
                     Apply as an estimator →
                  </a>
               </div>
            </div>

            <div className="flex items-center justify-end">
               <LoadingButton loading={processing}>{button.update}</LoadingButton>
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
