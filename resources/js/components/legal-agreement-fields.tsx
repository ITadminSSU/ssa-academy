import ScrollToAcceptDocument from '@/components/scroll-to-accept-document';

export interface LegalDocumentPayload {
   version: string;
   terms: {
      title: string;
      html: string;
      url: string;
      version: string;
   };
   signwell?: {
      enabled?: boolean;
      status?: string | null;
      has_signing_url?: boolean;
   };
}

interface Props {
   document: LegalDocumentPayload;
   acceptTerms: boolean;
   onAcceptTermsChange: (checked: boolean) => void;
   disabled?: boolean;
   termsError?: string;
   signwellEnabled?: boolean;
}

const LegalAgreementFields = ({
   document,
   acceptTerms,
   onAcceptTermsChange,
   disabled,
   termsError,
   signwellEnabled = false,
}: Props) => {
   return (
      <div className="space-y-4">
         <p className="text-muted-foreground text-sm">
            {signwellEnabled
               ? 'Read the Terms & Conditions, then create your account. You will be redirected to SignWell to electronically sign the Student Agreement before accessing the dashboard.'
               : 'Read the document in full and accept it before continuing. You must scroll to the bottom before the acceptance checkbox is enabled.'}
         </p>

         <ScrollToAcceptDocument
            title={document.terms.title}
            html={document.terms.html}
            checkboxId="accept_terms"
            checkboxLabel={
               signwellEnabled
                  ? 'I have read the Terms & Conditions and agree to sign the Student Agreement next.'
                  : 'I have read and agree to the Terms & Conditions.'
            }
            checked={acceptTerms}
            onCheckedChange={onAcceptTermsChange}
            disabled={disabled}
            error={termsError}
         />
      </div>
   );
};

export default LegalAgreementFields;
