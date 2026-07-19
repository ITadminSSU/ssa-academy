import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import LegalAgreementFields, { LegalDocumentPayload } from '@/components/legal-agreement-fields';
import AuthLayout from '@/layouts/auth-layout';
import { SharedData } from '@/types/global';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';
import ReCAPTCHA from 'react-google-recaptcha';

interface ProfessionalType {
   id: number;
   name: string;
   is_active: boolean;
   sort_order: number;
}

interface RegisterProps {
   googleLogIn: boolean;
   recaptcha: {
      status: boolean;
      siteKey: string;
      secretKey: string;
   };
   professionalTypes: ProfessionalType[];
   legalDocument: LegalDocumentPayload;
}

export default function Register({ googleLogIn, recaptcha, professionalTypes, legalDocument }: RegisterProps) {
   const { props } = usePage<SharedData>();
   const { auth, input, button } = props.translate;
   const recaptchaRef = useRef<ReCAPTCHA | null>(null);

   const { data, setData, post, processing, errors, reset } = useForm({
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      recaptcha: '',
      recaptcha_status: recaptcha.status,
      professional_type_id: '',
      professional_type_other: '',
      cv_resume: null as File | null,
      accept_terms: false,
      accept_nda: false,
   });

   const selectedProfessionalType = professionalTypes.find((type) => type.id.toString() === data.professional_type_id);
   const isOtherSelected = selectedProfessionalType?.name === 'Other';

   const isFormComplete =
      Boolean(data.name.trim()) &&
      Boolean(data.email.trim()) &&
      Boolean(data.password) &&
      Boolean(data.password_confirmation) &&
      Boolean(data.professional_type_id) &&
      (!isOtherSelected || Boolean(data.professional_type_other.trim())) &&
      Boolean(data.cv_resume) &&
      data.accept_terms &&
      data.accept_nda &&
      (!recaptcha.status || Boolean(data.recaptcha));

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('register'), {
         forceFormData: true,
         onSuccess: () => reset('password', 'password_confirmation', 'cv_resume'),
         onError: () => {
            if (recaptchaRef.current) {
               recaptchaRef.current.reset();
            }
            setData('recaptcha', '');
         },
      });
   };

   return (
      <AuthLayout title={auth.register_title} description={auth.register_description}>
         <Head title={auth.register_title} />
         <form className="flex flex-col gap-6" onSubmit={submit}>
            <div className="grid gap-6">
               <div className="grid gap-2">
                  <Label htmlFor="name">{input.name}</Label>
                  <Input
                     id="name"
                     type="text"
                     required
                     autoFocus
                     tabIndex={1}
                     autoComplete="name"
                     value={data.name}
                     onChange={(e) => setData('name', e.target.value)}
                     disabled={processing}
                     placeholder={input.full_name_placeholder}
                  />
                  <InputError message={errors.name} className="mt-2" />
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="email">{input.email}</Label>
                  <Input
                     id="email"
                     type="email"
                     required
                     tabIndex={2}
                     autoComplete="email"
                     value={data.email}
                     onChange={(e) => setData('email', e.target.value)}
                     disabled={processing}
                     placeholder={input.email_placeholder}
                  />
                  <InputError message={errors.email} />
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="password">{input.password}</Label>
                  <Input
                     id="password"
                     type="password"
                     required
                     tabIndex={3}
                     autoComplete="new-password"
                     value={data.password}
                     onChange={(e) => setData('password', e.target.value)}
                     disabled={processing}
                     placeholder={input.password_placeholder}
                  />
                  <InputError message={errors.password} />
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="password_confirmation">{input.confirm_password}</Label>
                  <Input
                     id="password_confirmation"
                     type="password"
                     required
                     tabIndex={4}
                     autoComplete="new-password"
                     value={data.password_confirmation}
                     onChange={(e) => setData('password_confirmation', e.target.value)}
                     disabled={processing}
                     placeholder={input.confirm_password}
                  />
                  <InputError message={errors.password_confirmation} />
               </div>

               <div className="grid gap-2">
                  <Label htmlFor="professional_type_id">
                     Professional Type <span className="text-destructive">*</span>
                  </Label>
                  <Select
                     value={data.professional_type_id}
                     required
                     onValueChange={(value) => {
                        setData('professional_type_id', value);
                        if (value && professionalTypes.find((t) => t.id.toString() === value)?.name !== 'Other') {
                           setData('professional_type_other', '');
                        }
                     }}
                     disabled={processing}
                  >
                     <SelectTrigger>
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
                        type="text"
                        required
                        tabIndex={5}
                        value={data.professional_type_other}
                        onChange={(e) => setData('professional_type_other', e.target.value)}
                        disabled={processing}
                        placeholder="Enter your professional type"
                     />
                     <InputError message={errors.professional_type_other} />
                  </div>
               )}

               <div className="grid gap-2">
                  <Label htmlFor="cv_resume">
                     CV / Resume <span className="text-destructive">*</span>
                  </Label>
                  <Input
                     id="cv_resume"
                     type="file"
                     accept=".pdf,.doc,.docx"
                     required
                     tabIndex={6}
                     onChange={(e) => {
                        const file = e.target.files?.[0] || null;
                        setData('cv_resume', file);
                     }}
                     disabled={processing}
                  />
                  <p className="text-muted-foreground text-xs">Accepted formats: PDF, DOC, DOCX (Max 10MB)</p>
                  {data.cv_resume && (
                     <p className="text-muted-foreground text-xs">
                        Selected file: <span className="text-foreground font-medium">{data.cv_resume.name}</span>
                     </p>
                  )}
                  <InputError message={errors.cv_resume} />
               </div>

               <LegalAgreementFields
                  document={legalDocument}
                  acceptTerms={data.accept_terms}
                  acceptNda={data.accept_nda}
                  onAcceptTermsChange={(value) => setData('accept_terms', value)}
                  onAcceptNdaChange={(value) => setData('accept_nda', value)}
                  disabled={processing}
                  termsError={errors.accept_terms}
                  ndaError={errors.accept_nda}
               />

               {recaptcha.status && (
                  <div>
                     <ReCAPTCHA ref={recaptchaRef} sitekey={recaptcha.siteKey} onChange={(token) => setData('recaptcha', token as string)} />
                     <InputError message={errors.recaptcha} />
                  </div>
               )}

               <p className="text-muted-foreground text-xs">{auth.register_required_fields_note}</p>

               <LoadingButton className="mt-2 w-full" tabIndex={7} loading={processing} disabled={!isFormComplete}>
                  {button.create}
               </LoadingButton>
            </div>

            {googleLogIn && (
               <>
                  <div className="after:border-border relative text-center text-sm after:absolute after:inset-0 after:top-1/2 after:z-0 after:flex after:items-center after:border-t">
                     <span className="bg-background text-muted-foreground relative z-10 px-2">{auth.continue_with}</span>
                  </div>

                  <a type="button" className="w-full" href="auth/google">
                     <Button type="button" variant="outline" className="w-full">
                        {button.continue_with_google}
                     </Button>
                  </a>
               </>
            )}

            <div className="text-muted-foreground text-center text-sm">
               {auth.have_account}{' '}
               <TextLink href={route('login')} tabIndex={8}>
                  {button.login}
               </TextLink>
            </div>
         </form>
      </AuthLayout>
   );
}
