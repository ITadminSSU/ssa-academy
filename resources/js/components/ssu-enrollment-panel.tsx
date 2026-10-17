import EnrollOrPlayerButton from '@/pages/courses/partials/course-player-button';
import { ReactNode } from 'react';

interface Props {
   children?: ReactNode;
}

/**
 * Button row for the course purchase card.
 * The pay, wishlist, and play actions stay in EnrollOrPlayerButton.
 */
const SsuEnrollmentPanel = ({ children }: Props) => {
   return <div className="flex flex-col gap-2.5">{children ?? <EnrollOrPlayerButton />}</div>;
};

export default SsuEnrollmentPanel;
