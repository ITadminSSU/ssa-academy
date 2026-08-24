import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import LegalAgreementFields, {
   LegalAcknowledgementKey,
   LegalDocumentPayload,
   allLegalAcknowledgementsAccepted,
   emptyLegalAcknowledgements,
} from '@/components/legal-agreement-fields';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';
import { SharedData } from '@/types/global';

interface Props {
   document: LegalDocumentPayload & {
      signwell?: {
         enabled?: boolean;
         status?: string | null;
         has_signing_url?: boolean;
      };
   };
   signwellEnabled?: boolean;
   signwellStatus?: string | null;
}

const LegalAgreement = ({ document, signwellEnabled = false, signwellStatus = null }: Props) => {
   const { flash } = usePage<SharedData>().props;
   const useSignWell = Boolean(signwellEnabled || document.signwell?.enabled);
   const [startingSignWell, setStartingSignWell] = useState(false);

   const { data, setData, post, processing, errors } = useForm({
      ...emptyLegalAcknowledgements(),
   });

   const legalValues = {
      accept_terms: data.accept_terms,
      accept_legal_age: data.accept_legal_age,
      accept_single_account: data.accept_single_account,
      accept_student_integrity: data.accept_student_integrity,
   };

   const setLegalAcknowledgement = (key: LegalAcknowledgementKey, checked: boolean) => {
      setData(key, checked);
   };

   const canSubmit = allLegalAcknowledgementsAccepted(legalValues);
   const isBusy = processing || startingSignWell;

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      if (useSignWell) {
         setStartingSignWell(true);
         window.location.assign(route('signwell.start'));
         return;
      }

      post(route('legal.agreement.store'));
   };

   return (
      <AuthLayout
         title={useSignWell ? 'Student Agreement Required' : 'Legal Agreement Required'}
         description={
            useSignWell
               ? 'Sign the SMARTSOURCING USA Academy Student Agreement to access your dashboard.'
               : 'Accept the Terms & Conditions to access the academy.'
         }
      >
         <Head title="Legal Agreement" />

         {(flash?.error || flash?.info || flash?.success) && (
            <div className="mb-4 space-y-2 text-sm">
               {flash?.error ? <p className="text-destructive">{flash.error}</p> : null}
               {flash?.info ? <p className="text-muted-foreground">{flash.info}</p> : null}
               {flash?.success ? <p className="text-emerald-600">{flash.success}</p> : null}
            </div>
         )}

         <form onSubmit={submit} className="space-y-6">
            {useSignWell ? (
               <div className="bg-muted/40 space-y-3 rounded-lg border p-4 text-sm">
                  <p className="font-medium">Electronic signature required</p>
                  <p className="text-muted-foreground">
                     After registration you must sign the Student Agreement before you can open the student dashboard
                     or enroll in courses. You will sign on this site and then continue to your dashboard.
                  </p>
                  {signwellStatus === 'pending' ? (
                     <p className="text-muted-foreground text-xs">
                        If you already finished signing in SignWell, click the button below — we will unlock your account.
                     </p>
                  ) : null}
               </div>
            ) : (
               <LegalAgreementFields
                  document={document}
                  values={legalValues}
                  onChange={setLegalAcknowledgement}
                  disabled={isBusy}
                  errors={{
                     accept_terms: errors.accept_terms,
                     accept_legal_age: errors.accept_legal_age,
                     accept_single_account: errors.accept_single_account,
                     accept_student_integrity: errors.accept_student_integrity,
                  }}
               />
            )}

            {!useSignWell ? (
               <>
                  <InputError message={errors.accept_terms} />
                  <InputError message={errors.accept_legal_age} />
                  <InputError message={errors.accept_single_account} />
                  <InputError message={errors.accept_student_integrity} />
               </>
            ) : null}

            <LoadingButton className="w-full" loading={isBusy} disabled={useSignWell ? isBusy : !canSubmit || isBusy}>
               {useSignWell ? (startingSignWell ? 'Opening SignWell…' : 'Sign Student Agreement') : 'Accept and Continue'}
            </LoadingButton>
         </form>
      </AuthLayout>
   );
};

export default LegalAgreement;
