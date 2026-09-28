import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useAuth } from '@/hooks/use-auth';
import { homeSectionHref, PUBLIC_NAV } from '@/lib/ssu-public';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import Actions from './partials/actions';

interface NavbarProps {
   language?: boolean;
   heightCover?: boolean;
   customizable?: boolean;
}

const Navbar = ({ heightCover = true }: NavbarProps) => {
   const { url } = usePage<SharedData>();
   const { isLoggedIn } = useAuth();
   const pathname = url.split('#')[0].split('?')[0] || '/';
   const onHome = pathname === '/';
   const [isSticky, setIsSticky] = useState(false);
   const [isMenuOpen, setIsMenuOpen] = useState(false);
   const [activeHash, setActiveHash] = useState('academy');

   useEffect(() => {
      const handleScroll = () => setIsSticky(window.scrollY > 40);
      handleScroll();
      window.addEventListener('scroll', handleScroll, { passive: true });
      return () => window.removeEventListener('scroll', handleScroll);
   }, []);

   useEffect(() => {
      if (!onHome) {
         return;
      }

      const ids = PUBLIC_NAV.map((item) => item.hash);
      const observer = new IntersectionObserver(
         (entries) => {
            const visible = entries
               .filter((entry) => entry.isIntersecting)
               .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
            if (visible?.target.id) {
               setActiveHash(visible.target.id);
            }
         },
         { rootMargin: '-20% 0px -65% 0px', threshold: [0.1, 0.25, 0.5] },
      );

      ids.forEach((id) => {
         const node = document.getElementById(id);
         if (node) {
            observer.observe(node);
         }
      });

      return () => observer.disconnect();
   }, [onHome]);

   return (
      <>
         <div className={cn('ssu-nav-shell fixed top-[var(--site-alert-offset,0px)] z-30 w-full', isSticky && 'ssu-nav-shell--sticky')}>
            <div className="container mt-0 flex min-h-14 items-center justify-between gap-1 !px-4 py-1.5 md:min-h-16 md:gap-6">
               <div className="flex items-center gap-2">
                  <Button size="icon" variant="secondary" className="bg-transparent lg:hidden" onClick={() => setIsMenuOpen(!isMenuOpen)}>
                     {isMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                  </Button>

                  <Link href={route('home')} className="ssu-logo-frame ssu-logo-frame--nav">
                     <AppLogo className="ssu-nav-logo" theme="light" />
                  </Link>
               </div>

               <nav className="hidden items-center gap-5 lg:flex" aria-label="Primary navigation">
                  {PUBLIC_NAV.map((item) => (
                     <a
                        key={item.hash}
                        href={homeSectionHref(item.hash, pathname)}
                        className={cn('ssu-pub-nav-link', onHome && activeHash === item.hash && 'is-active')}
                     >
                        {item.label}
                     </a>
                  ))}
               </nav>

               <div className="flex items-center gap-3">
                  <Actions language={false} variant="public" />
               </div>
            </div>

            {isMenuOpen && (
               <ScrollArea className="bg-[color:var(--ssu-cream-nav)] h-[calc(100vh-64px)] border-t border-[color:var(--ssu-line)] lg:hidden">
                  <div className="flex flex-col space-y-4 px-6 py-4">
                     {PUBLIC_NAV.map((item) => (
                        <a
                           key={item.hash}
                           href={homeSectionHref(item.hash, pathname)}
                           className="ssu-pub-nav-link py-1"
                           onClick={() => setIsMenuOpen(false)}
                        >
                           {item.label}
                        </a>
                     ))}
                     <Link href={route('about')} className="ssu-pub-nav-link py-1" onClick={() => setIsMenuOpen(false)}>
                        About
                     </Link>
                     <Link href={route('connect')} className="ssu-pub-nav-link py-1" onClick={() => setIsMenuOpen(false)}>
                        Connect with us
                     </Link>
                     {!isLoggedIn && (
                        <>
                           <Link href={route('login')} className="ssu-pub-nav-link py-1" onClick={() => setIsMenuOpen(false)}>
                              Log in
                           </Link>
                           <Link href={route('register')} className="ssu-pub-cta w-fit" onClick={() => setIsMenuOpen(false)}>
                              Get started
                           </Link>
                        </>
                     )}
                  </div>
               </ScrollArea>
            )}
         </div>

         {heightCover && <div className="relative z-20 h-16 bg-transparent" />}
      </>
   );
};

export default Navbar;
