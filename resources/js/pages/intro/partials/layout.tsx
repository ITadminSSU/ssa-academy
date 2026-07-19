import LandingLayout from '@/layouts/landing-layout';
import { ReactNode } from 'react';

interface PageProps {
   navbarHeight?: boolean;
   children: ReactNode;
}

const Layout = ({ navbarHeight = true, children }: PageProps) => {
   return (
      <LandingLayout navbarHeight={navbarHeight} customizable={false}>
         {children}
      </LandingLayout>
   );
};

export default Layout;
