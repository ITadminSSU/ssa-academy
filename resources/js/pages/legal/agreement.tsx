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
   });

   const canSubmit = data.accept_terms;

   const submit: FormEventHandler = (e) => {
      e.preventDefault();
      post(route('legal.agreement.store'));
   };

   return (
      <AuthLayout title="Legal Agreement Required" description="Accept the Terms & Conditions to access the academy.">
         <Head title="Legal Agreement" />

         <form onSubmit={submit} className="space-y-6">
            <LegalAgreementFields
               document={document}
               acceptTerms={data.accept_terms}
               onAcceptTermsChange={(value) => setData('accept_terms', value)}
               disabled={processing}
               termsError={errors.accept_terms}
            />

            <InputError message={errors.accept_terms} />

            <LoadingButton className="w-full" loading={processing} disabled={!canSubmit}>
               Accept and Continue
            </LoadingButton>
         </form>
      </AuthLayout>
   );
};

export default LegalAgreement;
