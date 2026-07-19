import { StudentDashboardProps } from '@/types/page';
import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';
import Layout from './partials/layout';
import Announcements from './tabs-content/announcements';
import BecomeInstructor from './tabs-content/become-instructor';
import Certificates from './tabs-content/certificates';
import Community from './tabs-content/community';
import HelpCenter from './tabs-content/help-center';
import Home from './tabs-content/home';
import MyCourses from './tabs-content/my-courses';
import ProfessionalDevelopment from './tabs-content/professional-development';
import ProjectLibrary from './tabs-content/project-library';
import Resources from './tabs-content/resources';
import MyExams from './tabs-content/my-exams';
import MyProfile from './tabs-content/my-profile';
import MySubscriptions from './tabs-content/my-subscriptions';
import Settings from './tabs-content/settings';
import Wishlist from './tabs-content/wishlist';

const Index = (props: StudentDashboardProps) => {
   const { translate } = props;
   const { frontend } = translate;

   const renderContent = () => {
      switch (props.tab) {
         case 'home':
            return <Home />;
         case 'courses':
            return <MyCourses />;
         case 'exams':
            return <MyExams />;
         case 'professional-development':
            return <ProfessionalDevelopment />;
         case 'certificates':
            return <Certificates />;
         case 'project-library':
            return <ProjectLibrary />;
         case 'announcements':
            return <Announcements />;
         case 'community':
            return <Community />;
         case 'resources':
            return <Resources />;
         case 'help-center':
            return <HelpCenter />;
         case 'wishlist':
            return <Wishlist />;
         case 'profile':
            return <MyProfile />;
         case 'settings':
            return <Settings />;
         case 'subscriptions':
            return <MySubscriptions />;
         case 'instructor':
            return <BecomeInstructor />;
         default:
            return <></>;
      }
   };

   return (
      <>
         <Head title={frontend.student_dashboard} />

         {renderContent()}
      </>
   );
};

Index.layout = (page: ReactNode & StudentDashboardProps) => <Layout children={page} tab={page.tab} />;

export default Index;
