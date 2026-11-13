import AvatarCropDialog from '@/components/avatar-crop-dialog';
import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import ProfileSetupBanner from '@/components/profile-setup-banner';
import {
   AlertDialog,
   AlertDialogAction,
   AlertDialogCancel,
   AlertDialogContent,
   AlertDialogDescription,
   AlertDialogFooter,
   AlertDialogHeader,
   AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAvatarCrop } from '@/hooks/use-avatar-crop';
import { onHandleChange } from '@/lib/inertia';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { StudentDashboardProps } from '@/types/page';
import { useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Camera, Check, Upload } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';

const CRITERIA_ASSESSMENT_URL = 'https://www.ondemandassessment.com/o/JB-EXX9G89Q8/landing?u=1313719';
const ESTIMATOR_APPLICATION_URL = 'https://smartsourcing-usa.breezy.hr/p/9396bbe98a9a-evt-construction-estimators';
const DOCUMENT_MAX_BYTES = 5 * 1024 * 1024 * 1024;

type DocumentKey = 'cv' | 'id' | 'assessment';
type SavedDocument = { file_name: string; view_url: string };
type UploadProgress = { name: string; progress: number };
type PendingConfirm = { action: 'replace'; key: DocumentKey; file: File } | { action: 'remove'; key: DocumentKey };

const DOCUMENT_SPECS: Record<
   DocumentKey,
   {
      field: 'cv_resume' | 'id_document' | 'criteria_assessment';
      label: string;
      extensions: string[];
      typeError: string;
      sizeError: string;
   }
> = {
   cv: {
      field: 'cv_resume',
      label: 'CV / resume',
      extensions: ['pdf', 'doc', 'docx'],
      typeError: 'CV / resume must be a PDF, DOC, or DOCX file.',
      sizeError: 'CV / resume must not be larger than 5 GB.',
   },
   id: {
      field: 'id_document',
      label: 'Government ID',
      extensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
      typeError: 'ID must be a JPG, PNG, WEBP, or PDF file.',
      sizeError: 'ID must not be larger than 5 GB.',
   },
   assessment: {
      field: 'criteria_assessment',
      label: 'Criteria Assessment result',
      extensions: ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],
      typeError: 'Assessment result must be a PDF, DOC, DOCX, JPG, PNG, or WEBP file.',
      sizeError: 'Assessment result must not be larger than 5 GB.',
   },
};

const EstimatorBagIcon = ({ className }: { className?: string }) => (
   <svg viewBox="0 0 128 96" className={className} aria-hidden="true">
      <path d="M47 26V16c0-5 4-8 8-8h18c4 0 8 3 8 8v10" fill="none" stroke="#04315A" strokeWidth="8" strokeLinecap="round" />
      <rect x="4" y="24" width="120" height="68" rx="14" fill="#04315A" />
      <path d="M18 40c16 0 22 10 46 10s30-10 46-10" fill="none" stroke="#F4F7F8" strokeWidth="8" strokeLinecap="round" />
      <rect x="58" y="42" width="12" height="16" rx="2" fill="#F6C20C" />
   </svg>
);

const facebookLink = (links: { host: string; profile_link: string }[] | null | undefined) => {
   if (!Array.isArray(links)) {
      return '';
   }

   return links.find((link) => link.host === 'facebook')?.profile_link ?? '';
};

const fileExtension = (name: string) => {
   const parts = name.split('.');

   return parts.length > 1 ? (parts.pop()?.toLowerCase() ?? '') : '';
};

const csrfHeaders = (): Record<string, string> => {
   const token = document.cookie
      .split('; ')
      .find((row) => row.startsWith('XSRF-TOKEN='))
      ?.split('=')
      .slice(1)
      .join('=');

   if (token) {
      return { 'X-XSRF-TOKEN': decodeURIComponent(token) };
   }

   const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

   return metaToken ? { 'X-CSRF-TOKEN': metaToken } : {};
};

const uploadErrorMessage = (error: unknown, field: string, fallback: string) => {
   if (!axios.isAxiosError(error)) {
      return fallback;
   }

   const fieldError = error.response?.data?.errors?.[field];
   if (Array.isArray(fieldError) && typeof fieldError[0] === 'string') {
      return fieldError[0];
   }

   const message = error.response?.data?.message;

   return typeof message === 'string' && message !== '' ? message : fallback;
};

const documentsFromProps = (profileDocuments: StudentDashboardProps['profileDocuments']) => ({
   cv: profileDocuments?.cv ?? null,
   id: profileDocuments?.id ?? null,
   assessment: profileDocuments?.assessment ?? null,
});

const UploadProgressBar = ({ upload, centered = false }: { upload: UploadProgress; centered?: boolean }) => (
   <div className={cn('w-full max-w-md space-y-1', centered && 'mx-auto text-center')}>
      <div className={cn('flex items-center justify-between gap-3 text-xs', centered && 'text-left')}>
         <span className="truncate">{upload.name}</span>
         <span className="shrink-0">{upload.progress >= 100 ? 'Saving…' : `Uploading ${upload.progress}%`}</span>
      </div>
      <div
         className="bg-muted h-2 overflow-hidden rounded-full"
         role="progressbar"
         aria-valuemin={0}
         aria-valuemax={100}
         aria-valuenow={upload.progress}
         aria-label={upload.progress >= 100 ? 'Saving file' : `Uploading ${upload.progress}%`}
      >
         <div className="h-full bg-[#012447]" style={{ width: `${upload.progress}%` }} />
      </div>
   </div>
);

const SavedFileLinks = ({
   document,
   centered = false,
   busy,
   onRemove,
}: {
   document: SavedDocument | null;
   centered?: boolean;
   busy: boolean;
   onRemove: () => void;
}) => {
   if (!document) {
      return null;
   }

   return (
      <p className={cn('flex flex-wrap items-center gap-x-4 gap-y-1 text-sm', centered && 'justify-center')}>
         <span>Current file: {document.file_name}</span>
         <a href={document.view_url} target="_blank" rel="noreferrer" className="font-medium underline">
            View
         </a>
         <button type="button" className="font-medium underline disabled:opacity-50" disabled={busy} onClick={onRemove}>
            Remove
         </button>
      </p>
   );
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
   const [documents, setDocuments] = useState(documentsFromProps(profileDocuments));
   const documentsRef = useRef(documents);
   const skipPropsSync = useRef(false);
   const [uploads, setUploads] = useState<Record<DocumentKey, UploadProgress | null>>({
      cv: null,
      id: null,
      assessment: null,
   });
   const [removing, setRemoving] = useState<DocumentKey | null>(null);
   const [documentErrors, setDocumentErrors] = useState<Record<DocumentKey, string | null>>({
      cv: null,
      id: null,
      assessment: null,
   });
   const [pending, setPending] = useState<PendingConfirm | null>(null);
   const [hasUsEstimatingExperience, setHasUsEstimatingExperience] = useState(user.has_us_estimating_experience === true);
   const [pendingExperience, setPendingExperience] = useState<boolean | null>(null);
   const [savingExperience, setSavingExperience] = useState(false);

   documentsRef.current = documents;

   const { data, post, setData, processing } = useForm({
      given_name: user.given_name ?? user.name.split(' ')[0] ?? '',
      last_name: user.given_name != null || user.last_name != null ? (user.last_name ?? '') : user.name.split(' ').slice(1).join(' '),
      photo: null as File | null,
      facebook: facebookLink(user.social_links),
   });

   useEffect(() => {
      if (window.location.hash === '#documents') {
         document.getElementById('documents')?.scrollIntoView();
      }
   }, []);

   useEffect(() => {
      const next = documentsFromProps(profileDocuments);

      if (skipPropsSync.current) {
         const same = (['cv', 'id', 'assessment'] as const).every(
            (key) => (next[key]?.file_name ?? null) === (documentsRef.current[key]?.file_name ?? null),
         );

         if (same) {
            skipPropsSync.current = false;
         }

         return;
      }

      setDocuments(next);
   }, [profileDocuments]);

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
         transform: (form) => ({
            given_name: form.given_name,
            last_name: form.last_name,
            facebook: form.facebook.trim(),
            photo: removePhoto ? null : photo,
            remove_photo: removePhoto ? 1 : 0,
         }),
         onSuccess: (page) => {
            photoFileRef.current = null;
            setRemovePhoto(false);
            setData('photo', null);
            setUserPhoto((page.props as SharedData).auth?.user?.photo ?? null);
            toast.success('Changes saved');
         },
         onError: (formErrors) => {
            const message = formErrors.photo || formErrors.given_name || formErrors.last_name || formErrors.facebook || 'Failed to save changes';
            toast.error(message);
         },
      });
   };

   const beginUpload = useCallback(async (key: DocumentKey, file: File) => {
      const spec = DOCUMENT_SPECS[key];
      const body = new FormData();
      body.append(spec.field, file);
      setDocumentErrors((current) => ({ ...current, [key]: null }));
      setUploads((current) => ({ ...current, [key]: { name: file.name, progress: 0 } }));

      try {
         const response = await axios.post<{ document: SavedDocument }>(route('student.profile.document.store', { type: key }), body, {
            headers: { Accept: 'application/json', ...csrfHeaders() },
            withCredentials: true,
            onUploadProgress: (event) => {
               const total = event.total && event.total > 0 ? event.total : file.size;
               const progress = total > 0 ? Math.min(100, Math.round((event.loaded / total) * 100)) : 0;
               setUploads((current) => ({ ...current, [key]: { name: file.name, progress } }));
            },
         });

         skipPropsSync.current = true;
         setDocuments((current) => ({ ...current, [key]: response.data.document }));
         setUploads((current) => ({ ...current, [key]: null }));
         toast.success(`${spec.label} saved`);
      } catch (error) {
         const message = uploadErrorMessage(error, spec.field, 'Upload failed');
         setUploads((current) => ({ ...current, [key]: null }));
         setDocumentErrors((current) => ({ ...current, [key]: message }));
         toast.error(message);
      }
   }, []);

   const removeDocument = useCallback(async (key: DocumentKey) => {
      const spec = DOCUMENT_SPECS[key];
      setRemoving(key);

      try {
         await axios.delete(route('student.profile.document.destroy', { type: key }), {
            headers: { Accept: 'application/json', ...csrfHeaders() },
            withCredentials: true,
         });
         skipPropsSync.current = true;
         setDocuments((current) => ({ ...current, [key]: null }));
         setDocumentErrors((current) => ({ ...current, [key]: null }));
         toast.success(`${spec.label} removed`);
      } catch (error) {
         toast.error(uploadErrorMessage(error, spec.field, 'Could not remove the file'));
      } finally {
         setRemoving(null);
      }
   }, []);

   const chooseFile = (key: DocumentKey, file: File | null) => {
      if (!file || uploads[key] || removing === key) {
         return;
      }

      const spec = DOCUMENT_SPECS[key];
      const extension = fileExtension(file.name);

      if (!spec.extensions.includes(extension)) {
         setDocumentErrors((current) => ({ ...current, [key]: spec.typeError }));
         toast.error(spec.typeError);
         return;
      }

      if (file.size > DOCUMENT_MAX_BYTES) {
         setDocumentErrors((current) => ({ ...current, [key]: spec.sizeError }));
         toast.error(spec.sizeError);
         return;
      }

      setDocumentErrors((current) => ({ ...current, [key]: null }));

      if (documents[key]) {
         setPending({ action: 'replace', key, file });
         return;
      }

      void beginUpload(key, file);
   };

   const requestExperienceChange = (next: boolean) => {
      if (savingExperience || next === hasUsEstimatingExperience) {
         return;
      }

      setPendingExperience(next);
   };

   const confirmExperience = async () => {
      if (pendingExperience === null || savingExperience) {
         return;
      }

      const next = pendingExperience;
      setSavingExperience(true);

      try {
         await axios.post(
            route('student.profile.experience'),
            { has_us_estimating_experience: next ? 1 : 0 },
            {
               headers: { Accept: 'application/json', ...csrfHeaders() },
               withCredentials: true,
            },
         );
         setHasUsEstimatingExperience(next);
         setPendingExperience(null);
         toast.success('Answer saved');
      } catch (error) {
         toast.error(uploadErrorMessage(error, 'has_us_estimating_experience', 'Could not save this answer'));
      } finally {
         setSavingExperience(false);
      }
   };

   const confirmPending = () => {
      if (!pending) {
         return;
      }

      const action = pending;
      setPending(null);

      if (action.action === 'replace') {
         void beginUpload(action.key, action.file);
         return;
      }

      void removeDocument(action.key);
   };

   const showRemove = Boolean(userPhoto || photoFileRef.current || user.photo);
   const pendingSpec = pending ? DOCUMENT_SPECS[pending.key] : null;
   const documentBusy = (key: DocumentKey) => Boolean(uploads[key]) || removing === key;

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

            <div className="flex items-center justify-center">
               <LoadingButton loading={processing}>Save Changes</LoadingButton>
            </div>

            <div id="documents" className="scroll-mt-24 space-y-4 border-t pt-12">
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
                     <SavedFileLinks document={documents.cv} busy={documentBusy('cv')} onRemove={() => setPending({ action: 'remove', key: 'cv' })} />
                     {uploads.cv ? <UploadProgressBar upload={uploads.cv} /> : null}
                     <div className="flex flex-wrap items-center gap-3">
                        <Button type="button" variant="outline" disabled={documentBusy('cv')} onClick={() => cvInputRef.current?.click()}>
                           Choose file
                        </Button>
                     </div>
                     <input
                        ref={cvInputRef}
                        id="cv_resume"
                        hidden
                        type="file"
                        accept=".pdf,.doc,.docx,application/pdf"
                        onChange={(event) => {
                           const file = event.target.files?.[0] ?? null;
                           event.target.value = '';
                           chooseFile('cv', file);
                        }}
                     />
                     <p className="text-muted-foreground text-xs">
                        PDF, DOC, or DOCX up to 5 GB. The file saves when the upload finishes. A new file replaces the saved one.
                     </p>
                     <InputError message={documentErrors.cv ?? undefined} />
                  </div>

                  <div className="grid gap-2">
                     <Label htmlFor="id_document">Government ID</Label>
                     <SavedFileLinks document={documents.id} busy={documentBusy('id')} onRemove={() => setPending({ action: 'remove', key: 'id' })} />
                     {uploads.id ? <UploadProgressBar upload={uploads.id} /> : null}
                     <div className="flex flex-wrap items-center gap-3">
                        <Button type="button" variant="outline" disabled={documentBusy('id')} onClick={() => idInputRef.current?.click()}>
                           Choose file
                        </Button>
                     </div>
                     <input
                        ref={idInputRef}
                        id="id_document"
                        hidden
                        type="file"
                        accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf"
                        onChange={(event) => {
                           const file = event.target.files?.[0] ?? null;
                           event.target.value = '';
                           chooseFile('id', file);
                        }}
                     />
                     <p className="text-muted-foreground text-xs">
                        JPG, PNG, WEBP, or PDF up to 5 GB. The file saves when the upload finishes. A new file replaces the saved one.
                     </p>
                     <InputError message={documentErrors.id ?? undefined} />
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
                  <SavedFileLinks
                     document={documents.assessment}
                     centered
                     busy={documentBusy('assessment')}
                     onRemove={() => setPending({ action: 'remove', key: 'assessment' })}
                  />
                  {uploads.assessment ? <UploadProgressBar upload={uploads.assessment} centered /> : null}
                  <div className="flex flex-wrap items-center justify-center gap-3">
                     <span>Upload your result here</span>
                     <Button
                        type="button"
                        variant="outline"
                        disabled={documentBusy('assessment')}
                        onClick={() => assessmentInputRef.current?.click()}
                     >
                        <Upload className="h-4 w-4" />
                        Upload File
                     </Button>
                  </div>
                  <input
                     ref={assessmentInputRef}
                     id="criteria_assessment"
                     hidden
                     type="file"
                     accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp"
                     onChange={(event) => {
                        const file = event.target.files?.[0] ?? null;
                        event.target.value = '';
                        chooseFile('assessment', file);
                     }}
                  />
                  <p className="text-muted-foreground text-center text-xs">
                     PDF, DOC, DOCX, JPG, PNG, or WEBP up to 5 GB. The file saves when the upload finishes. A new file replaces the saved one.
                  </p>
                  <InputError message={documentErrors.assessment ?? undefined} />
               </div>
            </div>

            <div className="flex items-center justify-center gap-3 text-center">
               <Checkbox
                  id="has_us_estimating_experience"
                  checked={hasUsEstimatingExperience}
                  disabled={savingExperience}
                  onCheckedChange={(value) => requestExperienceChange(value === true)}
               />
               <Label htmlFor="has_us_estimating_experience" className="text-base leading-6 font-semibold tracking-wide uppercase">
                  Do you have at least 1 year of U.S. estimating experience in roofing, siding, or drywall, or have you completed at least 50 U.S.
                  projects?
               </Label>
            </div>

            {hasUsEstimatingExperience ? (
               <div className="space-y-4">
                  <div className="flex items-center gap-4">
                     <span className="h-px flex-1 bg-[#f5b400]" />
                     <h2 className="text-center text-2xl leading-tight font-bold tracking-wide text-[#012447] uppercase md:text-4xl">
                        Ready for a <span className="text-[#f5b400]">direct opportunity</span>?
                     </h2>
                     <span className="h-px flex-1 bg-[#f5b400]" />
                  </div>
                  <p className="text-center text-xl leading-tight font-bold text-[#012447] md:text-3xl">
                     Submit your profile for consideration for U.S. construction opportunities.
                  </p>

                  <div className="mx-auto w-fit max-w-full rounded-2xl border-2 border-[#012447] bg-[#fff7e8] px-4 py-4 md:px-6">
                     <div className="flex flex-col items-center gap-3 md:flex-row md:justify-center">
                        <EstimatorBagIcon className="h-16 w-20 shrink-0" />
                        <div className="hidden h-16 w-px shrink-0 bg-[#012447]/25 md:block" />
                        <div className="space-y-1 text-center text-[#012447] md:text-left">
                           <div>
                              <h3 className="text-lg font-bold tracking-wide uppercase md:text-xl">Direct Estimator Path</h3>
                              <p className="text-xs font-semibold tracking-wide uppercase">For career-focused estimators</p>
                           </div>
                           <div>
                              <p className="text-sm font-semibold">What you'll gain:</p>
                              <ul className="mt-1 space-y-1 text-sm">
                                 <li className="flex items-center justify-center gap-2 md:justify-start">
                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#f5b400] text-[#012447]">
                                       <Check className="h-3 w-3" strokeWidth={3} />
                                    </span>
                                    Direct hiring consideration
                                 </li>
                                 <li className="flex items-center justify-center gap-2 md:justify-start">
                                    <span className="flex h-5 w-5 items-center justify-center rounded-full bg-[#f5b400] text-[#012447]">
                                       <Check className="h-3 w-3" strokeWidth={3} />
                                    </span>
                                    Access to relevant job openings
                                 </li>
                              </ul>
                           </div>
                        </div>
                     </div>
                     <div className="mt-3 flex justify-center">
                        <a
                           href={ESTIMATOR_APPLICATION_URL}
                           target="_blank"
                           rel="noreferrer"
                           className="inline-flex items-center justify-center rounded-full bg-[#f5b400] px-6 py-2 text-center text-sm font-bold tracking-wide text-[#012447] uppercase"
                        >
                           Apply as an estimator →
                        </a>
                     </div>
                  </div>
               </div>
            ) : null}
         </form>

         <AlertDialog
            open={pendingExperience !== null}
            onOpenChange={(open) => {
               if (!open && !savingExperience) {
                  setPendingExperience(null);
               }
            }}
         >
            <AlertDialogContent>
               <AlertDialogHeader>
                  <AlertDialogTitle>Save this answer?</AlertDialogTitle>
                  <AlertDialogDescription>
                     {pendingExperience
                        ? 'This saves that you have U.S. estimating experience and keeps Ready for a direct opportunity visible.'
                        : 'This saves that you do not have U.S. estimating experience and hides Ready for a direct opportunity.'}
                  </AlertDialogDescription>
               </AlertDialogHeader>
               <AlertDialogFooter>
                  <AlertDialogCancel type="button" disabled={savingExperience} onClick={() => setPendingExperience(null)}>
                     Cancel
                  </AlertDialogCancel>
                  <AlertDialogAction type="button" disabled={savingExperience} onClick={() => void confirmExperience()}>
                     {savingExperience ? 'Saving…' : 'Save'}
                  </AlertDialogAction>
               </AlertDialogFooter>
            </AlertDialogContent>
         </AlertDialog>

         <AlertDialog open={pending !== null} onOpenChange={(open) => !open && setPending(null)}>
            <AlertDialogContent>
               <AlertDialogHeader>
                  <AlertDialogTitle>{pending?.action === 'remove' ? 'Remove this file?' : 'Replace this file?'}</AlertDialogTitle>
                  <AlertDialogDescription>
                     {pending?.action === 'remove'
                        ? `This deletes only the saved ${pendingSpec?.label}.`
                        : `The saved ${pendingSpec?.label} will be replaced by ${pending?.action === 'replace' ? pending.file.name : 'the new file'}.`}
                  </AlertDialogDescription>
               </AlertDialogHeader>
               <AlertDialogFooter>
                  <AlertDialogCancel type="button" onClick={() => setPending(null)}>
                     Cancel
                  </AlertDialogCancel>
                  <AlertDialogAction type="button" onClick={confirmPending}>
                     {pending?.action === 'remove' ? 'Remove' : 'Replace'}
                  </AlertDialogAction>
               </AlertDialogFooter>
            </AlertDialogContent>
         </AlertDialog>

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
