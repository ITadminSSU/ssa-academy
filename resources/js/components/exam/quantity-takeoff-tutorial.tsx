import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { PlayCircle } from 'lucide-react';

interface Props {
   video: {
      url: string;
      name: string;
   };
}

const QuantityTakeoffTutorial = ({ video }: Props) => {
   return (
      <Card className="border-primary/20">
         <CardHeader>
            <CardTitle className="flex items-center gap-2">
               <PlayCircle className="h-5 w-5 text-primary" />
               Walkthrough tutorial
            </CardTitle>
            <CardDescription>
               Review the trainer walkthrough for this project now that your attempt has been submitted.
            </CardDescription>
         </CardHeader>
         <CardContent className="space-y-3">
            <p className="text-sm font-medium">{video.name}</p>
            <video src={video.url} controls playsInline preload="metadata" className="w-full rounded-lg border bg-black">
               Your browser does not support the video tag.
            </video>
         </CardContent>
      </Card>
   );
};

export default QuantityTakeoffTutorial;
