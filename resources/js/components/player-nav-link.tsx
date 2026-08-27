import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ComponentProps, forwardRef } from 'react';

type PlayerNavLinkProps = ComponentProps<typeof Link>;

/**
 * Inertia navigation for course-player lesson/quiz changes.
 * The video player is isolated from React's DOM, so SPA visits are safe
 * and keep the player chrome on screen.
 */
const PlayerNavLink = forwardRef<HTMLAnchorElement, PlayerNavLinkProps>(({ className, children, ...props }, ref) => (
   <Link ref={ref} className={cn(className)} {...props} preserveScroll preserveState>
      {children}
   </Link>
));

PlayerNavLink.displayName = 'PlayerNavLink';

export default PlayerNavLink;
