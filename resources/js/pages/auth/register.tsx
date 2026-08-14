import AppLogo from '@/components/app-logo';
import InputError from '@/components/input-error';
import LegalAgreementFields, { LegalDocumentPayload } from '@/components/legal-agreement-fields';
import LoadingButton from '@/components/loading-button';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import Main from '@/layouts/main';
import { SharedData } from '@/types/global';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
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
   estimatingSoftwareOptions: string[];
   constructionExperienceOptions: string[];
}

export default function Register({
   googleLogIn,
   recaptcha,
   professionalTypes,
   legalDocument,
   estimatingSoftwareOptions,
   constructionExperienceOptions,
}: RegisterProps) {
   const { props } = usePage<SharedData>();
   const { branding } = props;
   const { auth: authCopy, input: inputCopy, button: buttonCopy } = props.translate;
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
      estimating_software: [] as string[],
      estimating_software_other: '',
      construction_experience: '',
      worked_as_construction_va: '' as '' | '1' | '0',
      cv_resume: null as File | null,
      referred_by: '',
      accept_terms: false,
   });

   const selectedProfessionalType = professionalTypes.find((type) => type.id.toString() === data.professional_type_id);
   const isOtherSelected = selectedProfessionalType?.name === 'Other';
   const hasOthersSoftware = data.estimating_software.includes('Others');
   const hasNoneSoftware = data.estimating_software.includes('None');

   const toggleSoftware = (option: string, checked: boolean) => {
      if (option === 'None' && checked) {
         setData('estimating_software', ['None']);
         setData('estimating_software_other', '');
         return;
      }

      let next = data.estimating_software.filter((item) => item !== 'None');

      if (checked) {
         next = [...next, option];
      } else {
         next = next.filter((item) => item !== option);
         if (option === 'Others') {
            setData('estimating_software_other', '');
         }
      }

      setData('estimating_software', next);
   };

   const isFormComplete =
      Boolean(data.name.trim()) &&
      Boolean(data.email.trim()) &&
      Boolean(data.password) &&
      Boolean(data.password_confirmation) &&
      Boolean(data.professional_type_id) &&
      (!isOtherSelected || Boolean(data.professional_type_other.trim())) &&
      data.estimating_software.length > 0 &&
      (!hasOthersSoftware || Boolean(data.estimating_software_other.trim())) &&
      Boolean(data.construction_experience) &&
      data.worked_as_construction_va !== '' &&
      Boolean(data.cv_resume) &&
      data.accept_terms &&
      (!recaptcha.status || Boolean(data.recaptcha));

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      post(route('register'), {
         forceFormData: true,
         transform: (form) => ({
            ...form,
            worked_as_construction_va: form.worked_as_construction_va === '1',
         }),
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
      <Main>
         <Head title={authCopy.register_title} />

         <div className="ssu-page-shell min-h-svh">
            <header className="bg-primary px-4 py-8 text-white sm:px-6 sm:py-10">
               <div className="mx-auto flex max-w-5xl flex-col items-center text-center">
                  <p className="font-display mb-3 text-sm font-semibold tracking-[0.22em] uppercase">Welcome to</p>
                  <Link href={route('home')} className="block">
                     <AppLogo
                        theme="dark"
                        className="h-[96px] w-auto max-w-[260px] object-contain sm:h-[120px] sm:max-w-[320px]"
                     />
                  </Link>
               </div>
            </header>

            <div className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:py-12">
               <div className="mb-8 space-y-2">
                  <p className="ssu-kicker">Account</p>
                  <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">{authCopy.register_title}</h1>
                  <p className="text-muted-foreground text-sm sm:text-base">{authCopy.register_description}</p>
               </div>

               <form className="space-y-8" onSubmit={submit}>
                  {/*
                    Mobile/small: Password → Confirm password → Estimating software (then remaining fields).
                    Desktop (lg+): keep the original 3-column layout via explicit grid placement.
                  */}
                  <div className="grid grid-cols-1 gap-5 lg:grid-cols-3 lg:items-start lg:gap-x-8 lg:gap-y-5">
                     <div className="order-1 grid gap-2 lg:col-start-1 lg:row-start-1">
                        <Label htmlFor="name">{inputCopy.name}</Label>
                        <Input
                           id="name"
                           type="text"
                           required
                           autoFocus
                           autoComplete="name"
                           value={data.name}
                           onChange={(e) => setData('name', e.target.value)}
                           disabled={processing}
                           placeholder={inputCopy.full_name_placeholder}
                        />
                        <InputError message={errors.name} />
                     </div>

                     <div className="order-2 grid gap-2 lg:col-start-1 lg:row-start-2">
                        <Label htmlFor="email">{inputCopy.email}</Label>
                        <Input
                           id="email"
                           type="email"
                           required
                           autoComplete="email"
                           value={data.email}
                           onChange={(e) => setData('email', e.target.value)}
                           disabled={processing}
                           placeholder={inputCopy.email_placeholder}
                        />
                        <InputError message={errors.email} />
                     </div>

                     <div className="order-3 grid gap-2 lg:col-start-2 lg:row-start-1">
                        <Label htmlFor="password">{inputCopy.password}</Label>
                        <Input
                           id="password"
                           type="password"
                           required
                           autoComplete="new-password"
                           value={data.password}
                           onChange={(e) => setData('password', e.target.value)}
                           disabled={processing}
                           placeholder={inputCopy.password_placeholder}
                        />
                        <InputError message={errors.password} />
                     </div>

                     <div className="order-4 grid gap-2 lg:col-start-3 lg:row-start-1">
                        <Label htmlFor="password_confirmation">{inputCopy.confirm_password}</Label>
                        <Input
                           id="password_confirmation"
                           type="password"
                           required
                           autoComplete="new-password"
                           value={data.password_confirmation}
                           onChange={(e) => setData('password_confirmation', e.target.value)}
                           disabled={processing}
                           placeholder={inputCopy.confirm_password}
                        />
                        <InputError message={errors.password_confirmation} />
                     </div>

                     <fieldset className="order-5 space-y-3 lg:col-start-1 lg:row-start-3 lg:row-span-3">
                        <legend className="text-sm font-medium">
                           Which estimating software have you used? <span className="text-destructive">*</span>
                        </legend>
                        <p className="text-muted-foreground text-xs">Check all that apply</p>
                        <div className="space-y-2.5">
                           {estimatingSoftwareOptions.map((option) => {
                              const id = `software-${option}`;
                              const checked = data.estimating_software.includes(option);
                              const disabled = processing || (hasNoneSoftware && option !== 'None');

                              return (
                                 <div key={option} className="flex items-start gap-2">
                                    <Checkbox
                                       id={id}
                                       checked={checked}
                                       disabled={disabled}
                                       onCheckedChange={(value) => toggleSoftware(option, value === true)}
                                    />
                                    <Label htmlFor={id} className="font-normal">
                                       {option}
                                       {option === 'Others' ? ':' : ''}
                                    </Label>
                                 </div>
                              );
                           })}
                        </div>
                        {hasOthersSoftware && (
                           <Input
                              id="estimating_software_other"
                              type="text"
                              value={data.estimating_software_other}
                              onChange={(e) => setData('estimating_software_other', e.target.value)}
                              disabled={processing}
                              placeholder="Please specify"
                           />
                        )}
                        <InputError message={errors.estimating_software || errors['estimating_software.0']} />
                        <InputError message={errors.estimating_software_other} />
                     </fieldset>

                     <div className="order-6 space-y-5 lg:col-start-2 lg:row-start-2">
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
                                 value={data.professional_type_other}
                                 onChange={(e) => setData('professional_type_other', e.target.value)}
                                 disabled={processing}
                                 placeholder="Enter your professional type"
                              />
                              <InputError message={errors.professional_type_other} />
                           </div>
                        )}
                     </div>

                     <fieldset className="order-7 space-y-3 lg:col-start-2 lg:row-start-3">
                        <legend className="text-sm font-medium">
                           Years of Construction Experience <span className="text-destructive">*</span>
                        </legend>
                        <RadioGroup
                           value={data.construction_experience}
                           onValueChange={(value) => setData('construction_experience', value)}
                           disabled={processing}
                           className="space-y-2.5"
                        >
                           {constructionExperienceOptions.map((option) => {
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

                     <fieldset className="order-8 space-y-3 lg:col-start-2 lg:row-start-4">
                        <legend className="text-sm font-medium">
                           Have you worked as a Construction Virtual Assistant? <span className="text-destructive">*</span>
                        </legend>
                        <RadioGroup
                           value={data.worked_as_construction_va}
                           onValueChange={(value) => setData('worked_as_construction_va', value as '1' | '0')}
                           disabled={processing}
                           className="space-y-2.5"
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

                     <div className="order-9 grid gap-2 lg:col-start-3 lg:row-start-2">
                        <Label htmlFor="cv_resume">
                           CV / Resume <span className="text-destructive">*</span>
                        </Label>
                        <Input
                           id="cv_resume"
                           type="file"
                           accept=".pdf,.doc,.docx"
                           required
                           onChange={(e) => setData('cv_resume', e.target.files?.[0] || null)}
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

                     <div className="order-10 grid gap-2 lg:col-start-3 lg:row-start-3">
                        <Label htmlFor="referred_by">Who referred you?</Label>
                        <Input
                           id="referred_by"
                           type="text"
                           autoComplete="off"
                           value={data.referred_by}
                           onChange={(e) => setData('referred_by', e.target.value)}
                           disabled={processing}
                           placeholder="e.g. Juan Dela Cruz"
                        />
                        <p className="text-muted-foreground text-xs">Optional. Enter their full name.</p>
                        <InputError message={errors.referred_by} />
                     </div>
                  </div>

                  <div className="border-border/70 space-y-6 border-t pt-8">
                     <LegalAgreementFields
                        document={legalDocument}
                        acceptTerms={data.accept_terms}
                        onAcceptTermsChange={(value) => setData('accept_terms', value)}
                        disabled={processing}
                        termsError={errors.accept_terms}
                     />

                     {recaptcha.status && (
                        <div>
                           <ReCAPTCHA
                              ref={recaptchaRef}
                              sitekey={recaptcha.siteKey}
                              onChange={(token) => setData('recaptcha', token as string)}
                           />
                           <InputError message={errors.recaptcha} />
                        </div>
                     )}

                     <p className="text-muted-foreground text-xs">{authCopy.register_required_fields_note}</p>

                     <div className="flex flex-col items-center gap-4">
                        <LoadingButton
                           className="ssu-checkout-button h-11 min-w-[220px] px-10"
                           loading={processing}
                           disabled={!isFormComplete}
                        >
                           {buttonCopy.create}
                        </LoadingButton>

                        {googleLogIn && (
                           <a className="w-full max-w-sm" href="auth/google">
                              <Button type="button" variant="outline" className="w-full">
                                 {buttonCopy.continue_with_google}
                              </Button>
                           </a>
                        )}

                        <p className="text-muted-foreground text-center text-sm">
                           {authCopy.have_account}{' '}
                           <TextLink href={route('login')}>{buttonCopy.login}</TextLink>
                        </p>

                        <p className="text-muted-foreground text-center text-xs">
                           © {new Date().getFullYear()} {branding?.author || 'Smart Sourcing USA'}
                        </p>
                     </div>
                  </div>
               </form>
            </div>
         </div>
      </Main>
   );
}
