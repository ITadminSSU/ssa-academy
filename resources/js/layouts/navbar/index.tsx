import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useAuth } from '@/hooks/use-auth';
import { resolveNavbarItemHref } from '@/lib/navbar';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types/global';
import { Link, usePage } from '@inertiajs/react';
import { ChevronDown, Menu, X } from 'lucide-react';
import { Fragment, useEffect, useState } from 'react';
import Actions from './partials/actions';

interface NavbarProps {
   language?: boolean;
   heightCover?: boolean;
   customizable?: boolean;
}

const Navbar = ({ language = false, heightCover = true }: NavbarProps) => {
   const { props } = usePage<SharedData>();
   const { navbar, translate } = props;
   const { isLoggedIn } = useAuth();
   const [isSticky, setIsSticky] = useState(false);
   const [isMenuOpen, setIsMenuOpen] = useState(false);

   useEffect(() => {
      const handleScroll = () => {
         const scrollPosition = window.scrollY;
         if (scrollPosition > 100) {
            setIsSticky(true);
         } else {
            setIsSticky(false);
         }
      };

      window.addEventListener('scroll', handleScroll);

      return () => {
         window.removeEventListener('scroll', handleScroll);
      };
   }, []);

   const renderNavItems = (item: NavbarItem) => {
      if (item.active) {
         switch (item.type) {
            case 'url':
               if (item.title === 'Contact Us' || item.title === 'Contact' || item.value?.includes('/contact')) {
                  return (
                     <a
                        key={item.id}
                        href="https://smartsourcingusa.com/contact"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-sm font-normal"
                     >
                        {item.title}
                     </a>
                  );
               }
               return (
                  <Link key={item.id} href={resolveNavbarItemHref(item)} className="text-sm font-normal">
                     {item.title}
                  </Link>
               );

            case 'dropdown':
               return (
                  <DropdownMenu key={item.id}>
                     <DropdownMenuTrigger className="flex cursor-pointer items-center gap-1 text-sm">
                        {item.title}
                        <ChevronDown className="ml-1 h-4 w-4" />
                     </DropdownMenuTrigger>
                     <DropdownMenuContent align="start" className="min-w-20">
                        {item.items &&
                           Array.isArray(item.items) &&
                           item.items.map((subItem: any, idx: number) => (
                              <DropdownMenuItem key={idx} asChild className="cursor-pointer px-5">
                                 <Link href={subItem.url || ''}>{subItem.title}</Link>
                              </DropdownMenuItem>
                           ))}
                     </DropdownMenuContent>
                  </DropdownMenu>
               );

            default:
               return null;
         }
      }
   };

   const sortedItems = navbar.navbar_items
      .filter((item) => item.active !== false)
      .filter((item, index, items) => {
         if (item.type !== 'url') {
            return true;
         }

         const normalizedTitle = item.title?.trim().toLowerCase();
         const normalizedValue = (item.value || '').trim().toLowerCase();

         return (
            items.findIndex((candidate) => {
               if (candidate.type !== 'url') {
                  return false;
               }

               const candidateTitle = candidate.title?.trim().toLowerCase();
               const candidateValue = (candidate.value || '').trim().toLowerCase();

               return candidateTitle === normalizedTitle || (normalizedValue !== '' && candidateValue === normalizedValue);
            }) === index
         );
      })
      .sort((a, b) => a.sort - b.sort);

   return (
      <>
         <div className={cn('ssu-nav-shell fixed top-0 z-30 w-full', isMenuOpen && 'bg-background', isSticky && 'ssu-nav-shell--sticky')}>
            <div
               className={cn(
                  'container mt-0 flex min-h-14 items-center justify-between gap-1 !px-4 py-1.5 transition-all duration-200 md:gap-6',
               )}
            >
               <div className="flex items-center gap-2">
                  {/* Mobile menu button */}
                  <Button size="icon" variant="secondary" className="md:hidden" onClick={() => setIsMenuOpen(!isMenuOpen)}>
                     {isMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                  </Button>

                  {/* Logo */}
                  <Link href={route('home')} className="ssu-logo-frame ssu-logo-frame--nav">
                     <AppLogo className="ssu-nav-logo" />
                  </Link>
               </div>

               {/* Desktop Navigation */}
               <div className="hidden gap-4 md:flex md:items-center">
                  <Link href={route('home')} className="hover:text-primary text-sm font-medium transition-colors">
                     Home
                  </Link>
                  {sortedItems.map((item) => (
                     <Fragment key={item.id}>{renderNavItems(item)}</Fragment>
                  ))}
               </div>

               <div className="flex items-center gap-2">
                  <Actions language={language} />
               </div>
            </div>

            {/* Mobile Menu */}
            {isMenuOpen && (
               <ScrollArea className="bg-background h-[calc(100vh-72px)] border-t md:hidden">
                  <div className="flex flex-col space-y-4 px-6 py-4">
                     <Link href={route('home')} className="text-sm font-normal">
                        Home
                     </Link>
                     {sortedItems.map((item) => (
                        <Fragment key={item.id}>{renderNavItems(item)}</Fragment>
                     ))}

                     {!isLoggedIn && (
                        <div className="block space-y-2 sm:hidden">
                           <Button asChild variant="outline" className="w-full rounded-sm shadow-none sm:px-5 md:h-10">
                              <Link href={route('register')}>{translate.button.sign_up}</Link>
                           </Button>
                           <Button asChild className="w-full rounded-sm shadow-none sm:px-5 md:h-10">
                              <Link href={route('login')}>{translate.button.log_in}</Link>
                           </Button>
                        </div>
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
