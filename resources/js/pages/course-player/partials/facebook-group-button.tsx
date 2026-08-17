import { Button } from '@/components/ui/button';
import { Facebook } from 'lucide-react';

const FACEBOOK_GROUP_URL = 'https://www.facebook.com/share/g/14ttXqLttek/';

const FacebookGroupButton = () => (
   <Button asChild variant="outline">
      <a href={FACEBOOK_GROUP_URL} target="_blank" rel="noopener noreferrer">
         <Facebook className="h-4 w-4" />
         Click here to join our Facebook group
      </a>
   </Button>
);

export default FacebookGroupButton;
