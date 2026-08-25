import { IntroPageProps, PageSelectProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useSectionEditor } from '../../context';
import Categories from './categories';
import Courses from './courses';
import Instructors from './instructors';

interface Props {
   field: PropertyField;
   section_slug: string;
   onChange?: (value: any) => void;
}

const emptyPagination = <T,>(): Pagination<T> => ({
   data: [],
   current_page: 1,
   last_page: 1,
   first_page_url: '',
   last_page_url: '',
   next_page_url: null,
   prev_page_url: null,
   path: '',
   per_page: 10,
   total: 0,
   from: null,
   to: null,
   links: [],
});

const Contents = ({ field, section_slug, onChange }: Props) => {
   const { props } = usePage<IntroPageProps | PageSelectProps>();
   const courses = ('courses' in props ? props.courses : undefined) ?? emptyPagination<Course>();
   const categories = ('categories' in props ? props.categories : undefined) ?? emptyPagination<CourseCategory>();
   const instructors = ('instructors' in props ? props.instructors : undefined) ?? emptyPagination<Instructor>();
   const { section } = useSectionEditor();
   const [contentList, setContentList] = useState<number[]>(section.properties?.contents ? section.properties?.contents : []);

   useEffect(() => {
      if (field.type === 'contents' && Array.isArray(field.value)) {
         setContentList(field.value);
      }
   }, [field.value, field.type]);

   const onSelectChange = (id: number) => {
      let updatedContents: number[];

      // If ID already exists, remove it (deselect)
      if (contentList.includes(id)) {
         updatedContents = contentList.filter((item) => item !== id);
      } else {
         // If ID doesn't exist, add it (select)
         updatedContents = [...contentList, id];
      }

      // Update local state
      setContentList(updatedContents);

      // Update parent component via onChange - pass only the array value
      onChange?.(updatedContents);
   };

   const renderField = () => {
      switch (section_slug) {
         case 'hero':
         case 'top_course':
         case 'top_courses':
         case 'new_courses':
            return <Courses courses={courses as Pagination<Course>} selectedIds={contentList} onCourseSelect={onSelectChange} />;
         case 'top_categories':
         case 'category_courses':
            return <Categories categories={categories as Pagination<CourseCategory>} selectedIds={contentList} onCourseSelect={onSelectChange} />;

         case 'top_instructors':
            return <Instructors instructors={instructors as Pagination<Instructor>} selectedIds={contentList} onCourseSelect={onSelectChange} />;

         case 'blogs':
            return <h1>Blogs</h1>;

         default:
            return null;
      }
   };

   return <div className="rounded-md border">{renderField()}</div>;
};

export default Contents;
