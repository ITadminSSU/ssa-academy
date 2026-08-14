import ScrollToAcceptDocument from '@/components/scroll-to-accept-document';

export interface LegalDocumentPayload {
   version: string;
   terms: {
      title: string;
      html: string;
      url: string;
      version: string;
   };
}

interface Props {
   document: LegalDocumentPayload;
   acceptTerms: boolean;
   onAcceptTermsChange: (checked: boolean) => void;
   disabled?: boolean;
   termsError?: string;
}

const LegalAgreementFields = ({
   document,
   acceptTerms,
   onAcceptTermsChange,
   disabled,
   termsError,
}: Props) => {
   return (
      <div className="space-y-4">
         <p className="text-muted-foreground text-sm">
            Read the document in full and accept it before continuing. You must scroll to the bottom before the
            acceptance checkbox is enabled.
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
      </div>
   );
};

export default LegalAgreementFields;
