import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';

const Footer = () => {
   const { branding } = usePage<SharedData>().props;

   return (
      <footer className="ssu-player-footer">
         <div className="text-muted-foreground flex flex-wrap items-center justify-between gap-2 text-xs">
            <span>
               © {new Date().getFullYear()} {branding.author}
            </span>
            <div className="flex items-center gap-3">
               <Link href={route('category.courses', { category: 'all' })} className="hover:text-primary transition-colors">
                  All courses
               </Link>
            </div>
         </div>
      </footer>
   );
};

export default Footer;
