import InputError from '@/components/input-error';
import ScrollToAcceptDocument from '@/components/scroll-to-accept-document';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useState } from 'react';

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

export type LegalAcknowledgementKey =
   | 'accept_terms'
   | 'accept_legal_age'
   | 'accept_single_account'
   | 'accept_student_integrity';

export type LegalAcknowledgements = Record<LegalAcknowledgementKey, boolean>;

export const LEGAL_ACKNOWLEDGEMENT_KEYS: LegalAcknowledgementKey[] = [
   'accept_terms',
   'accept_legal_age',
   'accept_single_account',
   'accept_student_integrity',
];

export const emptyLegalAcknowledgements = (): LegalAcknowledgements => ({
   accept_terms: false,
   accept_legal_age: false,
   accept_single_account: false,
   accept_student_integrity: false,
});

export const allLegalAcknowledgementsAccepted = (values: LegalAcknowledgements): boolean =>
   LEGAL_ACKNOWLEDGEMENT_KEYS.every((key) => values[key]);

const ACKNOWLEDGEMENT_LABELS: Record<LegalAcknowledgementKey, string> = {
   accept_terms: 'I agree to these website Terms and Conditions.',
   accept_legal_age: 'I confirm that I am of legal age and legally capable of entering into these Terms.',
   accept_single_account:
      'I understand that I may have only one Academy registration and one Academy account, and that my account may not be shared with any other person.',
   accept_student_integrity:
      'I understand that I must also review and accept the SMARTSOURCING USA Academy Student Integrity, Confidentiality, and Participation Agreement before participating in the Academy.',
};

interface Props {
   document: LegalDocumentPayload;
   values: LegalAcknowledgements;
   onChange: (key: LegalAcknowledgementKey, checked: boolean) => void;
   disabled?: boolean;
   errors?: Partial<Record<LegalAcknowledgementKey, string>>;
   signwellEnabled?: boolean;
}

const LegalAgreementFields = ({
   document,
   values,
   onChange,
   disabled,
   errors,
   signwellEnabled = false,
}: Props) => {
   const [canAccept, setCanAccept] = useState(false);
   const checkboxesDisabled = disabled || !canAccept;

   return (
      <div className="space-y-4">
         <p className="text-muted-foreground text-sm">
            {signwellEnabled
               ? 'Read the Terms & Conditions in full, confirm each statement below, then create your account. You will be redirected to SignWell to electronically sign the Student Agreement before accessing the dashboard.'
               : 'Read the document in full and confirm each statement below before continuing. You must scroll to the bottom before the checkboxes are enabled.'}
         </p>

         <ScrollToAcceptDocument title={document.terms.title} html={document.terms.html} onCanAcceptChange={setCanAccept}>
            <div className="space-y-3">
               {LEGAL_ACKNOWLEDGEMENT_KEYS.map((key) => (
                  <div key={key} className="space-y-1">
                     <div className="flex items-start gap-3">
                        <Checkbox
                           id={key}
                           checked={values[key]}
                           onCheckedChange={(value) => {
                              if (!canAccept) {
                                 return;
                              }
                              onChange(key, Boolean(value));
                           }}
                           disabled={checkboxesDisabled}
                        />
                        <Label
                           htmlFor={key}
                           className={cn(
                              'text-sm font-medium leading-relaxed',
                              checkboxesDisabled ? 'text-muted-foreground cursor-not-allowed' : 'cursor-pointer',
                           )}
                        >
                           {ACKNOWLEDGEMENT_LABELS[key]}
                        </Label>
                     </div>
                     <InputError message={errors?.[key]} />
                  </div>
               ))}
            </div>
         </ScrollToAcceptDocument>
      </div>
   );
};

export default LegalAgreementFields;
