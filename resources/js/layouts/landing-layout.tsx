import React from 'react';
import Footer from './footer';
import Main from './main';
import Navbar from './navbar';

interface LayoutProps {
   children: React.ReactNode;
   language?: boolean;
   navbarHeight?: boolean;
   customizable?: boolean;
}

const LandingLayout = ({ children, language = false, navbarHeight = true, customizable }: LayoutProps) => {
   return (
      <Main>
         {/*
           Do NOT use overflow-x-hidden here. On iPad/Safari it creates a scroll
           containment that incorrectly clips rounded / sticky children into
           triangular artifacts. Horizontal bleed is handled via overflow-x-clip
           on html/body in the design system.
         */}
         <div className="flex min-h-screen max-w-[100vw] flex-col justify-between">
            <main className="min-w-0">
               <Navbar heightCover={navbarHeight} customizable={customizable} language={language} />

               {children}
            </main>

            <Footer />
         </div>
      </Main>
   );
};

export default LandingLayout;
