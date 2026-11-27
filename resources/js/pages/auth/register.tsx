import AppLogo from '@/components/app-logo';
import InputError from '@/components/input-error';
import LegalAgreementFields, {
   ACCOUNT_LEGAL_ACKNOWLEDGEMENT_KEYS,
   LegalAcknowledgementKey,
   LegalDocumentPayload,
   allLegalAcknowledgementsAccepted,
   emptyLegalAcknowledgements,
} from '@/components/legal-agreement-fields';
import LoadingButton from '@/components/loading-button';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import Main from '@/layouts/main';
import { SharedData } from '@/types/global';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef } from 'react';
import ReCAPTCHA from 'react-google-recaptcha';

interface RegisterProps {
   googleLogIn: boolean;
   recaptcha: {
      status: boolean;
      siteKey: string;
      secretKey: string;
   };
   legalDocument: LegalDocumentPayload;
   sourceCourse?: { id: number; title: string } | null;
   registrationSource?: string | null;
}

const Register = ({ googleLogIn, recaptcha, legalDocument, sourceCourse = null, registrationSource = null }: RegisterProps) => {
   const { props } = usePage<SharedData>();
   const { branding } = props;
   const { auth: authCopy, input: inputCopy, button: buttonCopy } = props.translate;
   const recaptchaRef = useRef<ReCAPTCHA | null>(null);

   useEffect(() => {
      document.documentElement.classList.add('ssu-public-root');
      return () => document.documentElement.classList.remove('ssu-public-root');
   }, []);

   const sourceCourseId = sourceCourse?.id ?? null;
   const sourceValue = sourceCourse ? '' : (registrationSource ?? '');

   const { data, setData, post, processing, errors, reset } = useForm({
      given_name: '',
      last_name: '',
      email: '',
      password: '',
      password_confirmation: '',
      recaptcha: '',
      recaptcha_status: recaptcha.status,
      source_course_id: sourceCourseId,
      source: sourceValue,
      referred_by: '',
      referrer_is_employee: null as boolean | null,
      ...emptyLegalAcknowledgements(),
   });

   useEffect(() => {
      setData((current) => ({
         ...current,
         source_course_id: sourceCourseId,
         source: sourceValue,
      }));
   }, [sourceCourseId, sourceValue]);

   const legalValues = {
      accept_terms: data.accept_terms,
      accept_legal_age: data.accept_legal_age,
      accept_single_account: data.accept_single_account,
      accept_student_integrity: data.accept_student_integrity,
   };

   const setLegalAcknowledgement = (key: LegalAcknowledgementKey, checked: boolean) => {
      setData(key, checked);
   };

   const isFormComplete =
      Boolean(data.given_name.trim()) &&
      Boolean(data.email.trim()) &&
      Boolean(data.password) &&
      Boolean(data.password_confirmation) &&
      allLegalAcknowledgementsAccepted(legalValues, ACCOUNT_LEGAL_ACKNOWLEDGEMENT_KEYS) &&
      (!recaptcha.status || Boolean(data.recaptcha)) &&
      (data.referred_by.trim() === '' || data.referrer_is_employee !== null);

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      const query = typeof window === 'undefined' ? '' : window.location.search;

      post(`${route('register')}${query}`, {
         onSuccess: () => reset('password', 'password_confirmation'),
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

         <div className="ssu-public ssu-page-shell min-h-svh">
            <header className="bg-[color:var(--ssu-navy)] px-4 py-8 text-white sm:px-6 sm:py-10">
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

            <div className="mx-auto w-full max-w-3xl px-4 py-8 sm:px-6 sm:py-10 lg:py-12">
               <div className="mb-8 space-y-2">
                  <p className="ssu-kicker">Account</p>
                  <h1 className="font-display text-2xl font-semibold tracking-tight sm:text-3xl">{authCopy.register_title}</h1>
                  <p className="text-muted-foreground text-sm sm:text-base">{authCopy.register_description}</p>
               </div>

               <form className="space-y-8" onSubmit={submit}>
                  <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                     <div className="grid gap-2">
                        <Label htmlFor="given_name">Given name</Label>
                        <Input
                           id="given_name"
                           type="text"
                           required
                           autoFocus
                           autoComplete="given-name"
                           value={data.given_name}
                           onChange={(e) => setData('given_name', e.target.value)}
                           disabled={processing}
                           placeholder="Given name"
                        />
                        <InputError message={errors.given_name} />
                     </div>

                     <div className="grid gap-2">
                        <Label htmlFor="last_name">Last name</Label>
                        <Input
                           id="last_name"
                           type="text"
                           autoComplete="family-name"
                           value={data.last_name}
                           onChange={(e) => setData('last_name', e.target.value)}
                           disabled={processing}
                           placeholder="Last name"
                        />
                        <InputError message={errors.last_name} />
                     </div>

                     <div className="grid gap-2 sm:col-span-2">
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

                     <div className="grid gap-2">
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

                     <div className="grid gap-2">
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
                  </div>

                  <div className="grid gap-3">
                     <Label htmlFor="referred_by">Referred by</Label>
                     <Input
                        id="referred_by"
                        type="text"
                        value={data.referred_by}
                        onChange={(e) => {
                           const value = e.target.value;
                           setData((form) => ({
                              ...form,
                              referred_by: value,
                              referrer_is_employee: value.trim() === '' ? null : form.referrer_is_employee,
                           }));
                        }}
                        disabled={processing}
                        placeholder="Optional"
                        maxLength={255}
                     />
                     <p className="text-muted-foreground text-xs">Leave this blank if nobody referred you.</p>
                     <InputError message={errors.referred_by} />
                     {data.referred_by.trim() !== '' && (
                        <fieldset className="grid gap-2">
                           <legend className="text-sm font-medium">Is this person a Smart Sourcing employee?</legend>
                           <div className="flex flex-wrap gap-4">
                              <label className="flex items-center gap-2 text-sm">
                                 <input
                                    type="radio"
                                    name="referrer_is_employee"
                                    checked={data.referrer_is_employee === true}
                                    onChange={() => setData('referrer_is_employee', true)}
                                    disabled={processing}
                                 />
                                 Yes
                              </label>
                              <label className="flex items-center gap-2 text-sm">
                                 <input
                                    type="radio"
                                    name="referrer_is_employee"
                                    checked={data.referrer_is_employee === false}
                                    onChange={() => setData('referrer_is_employee', false)}
                                    disabled={processing}
                                 />
                                 No
                              </label>
                           </div>
                           <InputError message={errors.referrer_is_employee} />
                        </fieldset>
                     )}
                  </div>

                  <div className="border-border/70 space-y-6 border-t pt-8">
                     <LegalAgreementFields
                        document={legalDocument}
                        values={legalValues}
                        onChange={setLegalAcknowledgement}
                        disabled={processing}
                        accountLayout
                        includedKeys={ACCOUNT_LEGAL_ACKNOWLEDGEMENT_KEYS}
                        errors={{
                           accept_terms: errors.accept_terms,
                           accept_legal_age: errors.accept_legal_age,
                        }}
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
};

export default Register;
