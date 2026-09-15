import { Renderer } from 'richtor';
import 'richtor/styles';

const Overview = ({ course }: { course: Course }) => {
   return (
      <div>
         <Renderer value={course.description as string} />
      </div>
   );
};

export default Overview;
