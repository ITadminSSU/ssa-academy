import InputError from '@/components/input-error';
import LoadingButton from '@/components/loading-button';
import LegalAgreementFields, { LegalDocumentPayload } from '@/components/legal-agreement-fields';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
   document: LegalDocumentPayload;
}

const LegalAgreement = ({ document }: Props) => {
   const { data, setData, post, processing, errors } = useForm({
      accept_terms: false,
      accept_nda: false,
   });

   const canSubmit = data.accept_terms && data.accept_nda;

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('legal.agreement.store'));
   };

   return (
      <AuthLayout title="Legal Agreement Required" description="Accept the Terms & Conditions and NDA to access the academy.">
         <Head title="Legal Agreement" />

         <form onSubmit={submit} className="space-y-6">
            <LegalAgreementFields
               document={document}
               acceptTerms={data.accept_terms}
               acceptNda={data.accept_nda}
               onAcceptTermsChange={(value) => setData('accept_terms', value)}
               onAcceptNdaChange={(value) => setData('accept_nda', value)}
               disabled={processing}
               termsError={errors.accept_terms}
               ndaError={errors.accept_nda}
            />

            <InputError message={errors.accept_terms} />
            <InputError message={errors.accept_nda} />

            <LoadingButton className="w-full" loading={processing} disabled={!canSubmit}>
               Accept and Continue
            </LoadingButton>
         </form>
      </AuthLayout>
   );
};

export default LegalAgreement;
