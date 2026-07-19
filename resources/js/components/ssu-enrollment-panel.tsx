import EnrollOrPlayerButton from '@/pages/courses/partials/course-player-button';
import { ReactNode } from 'react';

interface Props {
   children?: ReactNode;
}

/**
 * SSU-approved enrollment CTA shell for course detail sidebar.
 * Wraps the existing enrollment/play logic with design-system surfaces.
 */
const SsuEnrollmentPanel = ({ children }: Props) => {
   return (
      <div className="ssu-enrollment-panel space-y-4">
         <div>
            <p className="ssu-kicker mb-1">Enrollment</p>
            <p className="text-muted-foreground text-sm">Start learning or continue your assigned path.</p>
         </div>

         <div className="flex flex-col gap-2.5">{children ?? <EnrollOrPlayerButton />}</div>
      </div>
   );
};

export default SsuEnrollmentPanel;
