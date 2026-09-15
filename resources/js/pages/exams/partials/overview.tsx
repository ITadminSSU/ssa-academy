import { ExamPreviewProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { Renderer } from 'richtor';
import 'richtor/styles';

const Overview = () => {
   const { exam } = usePage<ExamPreviewProps>().props;

   return (
      <div>
         <Renderer value={exam.description as string} />
      </div>
   );
};

export default Overview;
