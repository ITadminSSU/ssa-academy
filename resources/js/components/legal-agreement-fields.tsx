import ScrollToAcceptDocument from '@/components/scroll-to-accept-document';

export interface LegalDocumentPayload {
   version: string;
   terms: {
      title: string;
      html: string;
      url: string;
      version: string;
   };
   nda: {
      title: string;
      html: string;
      url: string;
      version: string;
   };
}

interface Props {
   document: LegalDocumentPayload;
   acceptTerms: boolean;
   acceptNda: boolean;
   onAcceptTermsChange: (checked: boolean) => void;
   onAcceptNdaChange: (checked: boolean) => void;
   disabled?: boolean;
   termsError?: string;
   ndaError?: string;
}

const LegalAgreementFields = ({
   document,
   acceptTerms,
   acceptNda,
   onAcceptTermsChange,
   onAcceptNdaChange,
   disabled,
   termsError,
   ndaError,
}: Props) => {
   return (
      <div className="space-y-4">
         <p className="text-muted-foreground text-sm">
            Read each document in full and accept them separately before creating your account. You must scroll to the bottom of
            each document before the acceptance checkbox is enabled.
         </p>

         <ScrollToAcceptDocument
            title={document.terms.title}
            html={document.terms.html}
            checkboxId="accept_terms"
            checkboxLabel="I have read and agree to the Terms & Conditions."
            checked={acceptTerms}
            onCheckedChange={onAcceptTermsChange}
            disabled={disabled}
            error={termsError}
         />

         <ScrollToAcceptDocument
            title={document.nda.title}
            html={document.nda.html}
            checkboxId="accept_nda"
            checkboxLabel="I have read and agree to the legally binding Non-Disclosure Agreement (NDA). I understand violations may result in penalties, fines, and permanent academy bans."
            checked={acceptNda}
            onCheckedChange={onAcceptNdaChange}
            disabled={disabled}
            error={ndaError}
         />
      </div>
   );
};

export default LegalAgreementFields;
