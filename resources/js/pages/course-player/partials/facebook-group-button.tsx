import { Button } from '@/components/ui/button';
import { Facebook } from 'lucide-react';

const FACEBOOK_GROUP_URL = 'https://www.facebook.com/share/g/14ttXqLttek/';

const FacebookGroupButton = () => (
   <Button
      asChild
      className="border-0 bg-[#1877F2] text-white hover:bg-[#166FE5] hover:text-white"
   >
      <a href={FACEBOOK_GROUP_URL} target="_blank" rel="noopener noreferrer">
         <Facebook className="h-4 w-4" />
         Click here to join our Facebook group
      </a>
   </Button>
);

export default FacebookGroupButton;
