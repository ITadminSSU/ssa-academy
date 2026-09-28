import { BackToTop } from '@/components/ssu-public/chrome';
import React, { useEffect } from 'react';
import Footer from './footer';
import Main from './main';
import Navbar from './navbar';

interface LayoutProps {
   children: React.ReactNode;
   language?: boolean;
   navbarHeight?: boolean;
   customizable?: boolean;
}

const LandingLayout = ({ children, language = false, navbarHeight = true }: LayoutProps) => {
   useEffect(() => {
      document.documentElement.classList.add('ssu-public-root');

      const scrollToHash = () => {
         const hash = window.location.hash;
         if (hash.length < 2) {
            return;
         }

         requestAnimationFrame(() => {
            document.querySelector(hash)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
         });
      };

      const timer = window.setTimeout(scrollToHash, 80);
      window.addEventListener('hashchange', scrollToHash);

      return () => {
         window.clearTimeout(timer);
         window.removeEventListener('hashchange', scrollToHash);
         document.documentElement.classList.remove('ssu-public-root');
      };
   }, []);

   return (
      <Main>
         <div id="top" className="ssu-public flex min-h-screen max-w-[100vw] flex-col justify-between">
            <main className="min-w-0">
               <Navbar heightCover={navbarHeight} language={language} />
               {children}
            </main>
            <Footer />
            <BackToTop />
         </div>
      </Main>
   );
};

export default LandingLayout;
