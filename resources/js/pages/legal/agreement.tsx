import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import LegalAgreementFields, { LegalDocumentPayload } from '@/components/legal-agreement-fields';
import AuthLayout from '@/layouts/auth-layout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';
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

   const { data, setData, post, processing, errors } = useForm({
      accept_terms: false,
   });

   const canSubmit = data.accept_terms;

   const submit: FormEventHandler = (e) => {
      e.preventDefault();

      if (useSignWell) {
         router.get(route('signwell.start'));
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
                     After registration you must sign the Student Agreement in SignWell before you can open the student
                     dashboard or enroll in courses.
                  </p>
                  {signwellStatus === 'pending' ? (
                     <p className="text-muted-foreground text-xs">You have a signing session in progress. Continue below.</p>
                  ) : null}
               </div>
            ) : (
               <LegalAgreementFields
                  document={document}
                  acceptTerms={data.accept_terms}
                  onAcceptTermsChange={(value) => setData('accept_terms', value)}
                  disabled={processing}
                  termsError={errors.accept_terms}
               />
            )}

            {!useSignWell ? <InputError message={errors.accept_terms} /> : null}

            <LoadingButton className="w-full" loading={processing} disabled={useSignWell ? false : !canSubmit}>
               {useSignWell ? 'Sign Student Agreement' : 'Accept and Continue'}
            </LoadingButton>
         </form>
      </AuthLayout>
   );
};

export default LegalAgreement;
