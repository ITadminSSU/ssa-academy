import CurriculumSectionList from '@/components/curriculum-section-list';
import { getCompletedContents } from '@/lib/utils';
import Lesson from '@/pages/course-player/partials/lesson';
import Quiz from '@/pages/course-player/partials/quiz';
import { StudentCourseProps } from '@/types/page';
import { usePage } from '@inertiajs/react';

const CourseModules = () => {
   const { props } = usePage<StudentCourseProps>();
   const { modules } = props;
   const completed = getCompletedContents(props.watchHistory);

   return (
      <CurriculumSectionList
         sections={modules}
         renderLesson={(lesson, index) => (
            <Lesson key={lesson.id} lesson={lesson} completed={completed} variant="simple" index={index} />
         )}
         renderQuiz={(quiz, index) => <Quiz key={quiz.id} quiz={quiz} completed={completed} variant="simple" index={index} />}
      />
   );
};

export default CourseModules;
