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

export const ACCOUNT_LEGAL_ACKNOWLEDGEMENT_KEYS: LegalAcknowledgementKey[] = [
   'accept_terms',
   'accept_legal_age',
   'accept_single_account',
];

export const emptyLegalAcknowledgements = (): LegalAcknowledgements => ({
   accept_terms: false,
   accept_legal_age: false,
   accept_single_account: false,
   accept_student_integrity: false,
});

export const allLegalAcknowledgementsAccepted = (
   values: LegalAcknowledgements,
   keys: LegalAcknowledgementKey[] = LEGAL_ACKNOWLEDGEMENT_KEYS,
): boolean => keys.every((key) => values[key]);

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
   includedKeys?: LegalAcknowledgementKey[];
}

const LegalAgreementFields = ({
   document,
   values,
   onChange,
   disabled,
   errors,
   signwellEnabled = false,
   includedKeys = LEGAL_ACKNOWLEDGEMENT_KEYS,
}: Props) => {
   const [canAccept, setCanAccept] = useState(false);
   const checkboxesDisabled = disabled || !canAccept;
   const accountOnly = !includedKeys.includes('accept_student_integrity');

   return (
      <div className="space-y-4">
         <div className="text-muted-foreground space-y-3 text-sm">
            {accountOnly ? (
               <p>
                  Read the Terms & Conditions, then confirm each statement. Scroll to the bottom before the checkboxes
                  are enabled. After you create your account, you will verify your email and open your dashboard. The
                  student agreement is signed the first time you start a course. Use your official, real legal name.
               </p>
            ) : signwellEnabled ? (
               <p>
                  Sign the student agreement before you start this course. After you finish signing, you will return to
                  the course you were starting.
               </p>
            ) : (
               <p>
                  Read the document in full and confirm each statement below before continuing. You must scroll to the
                  bottom before the checkboxes are enabled.
               </p>
            )}
         </div>

         <ScrollToAcceptDocument title={document.terms.title} html={document.terms.html} onCanAcceptChange={setCanAccept}>
            <div className="space-y-3">
               {includedKeys.map((key) => (
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
