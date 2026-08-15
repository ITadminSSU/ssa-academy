import { cn } from '@/lib/utils';
import { ComponentPropsWithoutRef } from 'react';

/**
 * Full-page navigation for course-player lesson/quiz changes.
 * Inertia SPA visits reuse the page and leave Plyr's externally managed DOM
 * in place; swapping the video source then crashes React (white screen).
 * A normal document load tears the player down cleanly.
 */
const PlayerNavLink = ({ className, children, ...props }: ComponentPropsWithoutRef<'a'>) => (
   <a className={cn(className)} {...props}>
      {children}
   </a>
);

export default PlayerNavLink;
