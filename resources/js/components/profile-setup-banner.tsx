import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

const missingDocumentList = (missing?: { needs_cv: boolean; needs_id: boolean } | null) => {
   const files = [missing?.needs_cv ? 'CV/resume' : null, missing?.needs_id ? 'government ID' : null].filter(Boolean);

   if (files.length === 2) {
      return `${files[0]} and ${files[1]}`;
   }

   return files[0] ?? '';
};

const ProfileSetupBanner = ({ onProfile = false }: { onProfile?: boolean }) => {
   const { auth } = usePage<SharedData>().props;
   const missing = auth.certificateDocuments;
   const missingLabel = missingDocumentList(missing);

   if (!missingLabel) {
      return null;
   }

   const href = onProfile ? '#documents' : auth.profileUrl ? `${auth.profileUrl}#documents` : null;

   if (!href) {
      return null;
   }

   return (
      <div className="border-border bg-muted/40 flex flex-col gap-3 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between">
         <p className="text-sm">Upload your {missingLabel} on your profile to release your certificate.</p>
         {onProfile ? (
            <a href={href} className="text-sm font-medium underline">
               Go to documents
            </a>
         ) : (
            <Link href={href} className="text-sm font-medium underline">
               Go to profile
            </Link>
         )}
      </div>
   );
};

export default ProfileSetupBanner;
