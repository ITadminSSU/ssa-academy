import DashboardLayout from '@/layouts/dashboard/layout';
import { isEmployeeLearner } from '@/lib/dashboard';
import { SharedData } from '@/types/global';
import { usePage } from '@inertiajs/react';
import { ReactNode } from 'react';

const Layout = ({ children }: { children: ReactNode }) => {
   const { auth, translate } = usePage<SharedData>().props;
   const user = auth.user;
   const variant = user && (user.role === 'student' || isEmployeeLearner(user)) ? 'learner' : 'admin';

   return (
      <DashboardLayout variant={variant} headTitle={translate.dashboard.notifications}>
         {children}
      </DashboardLayout>
   );
};

export default Layout;
