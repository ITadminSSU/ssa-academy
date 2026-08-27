import RatingStars from '@/components/rating-stars';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { ExamPreviewProps } from '@/types/page';
import { Link, usePage } from '@inertiajs/react';
import { Book, Star, Users } from 'lucide-react';

const Instructor = () => {
   const { instructor, translate } = usePage<ExamPreviewProps>().props;
   const { frontend } = translate;
   const {
      user,
      courses_count,
      total_reviews_count,
      total_average_rating,
      total_enrollments_count,
      exams_count,
      total_exam_reviews_count,
      total_exam_average_rating,
      total_exam_instructors_count,
   } = instructor;
   const biography = instructor.biography?.trim() || '';

   return (
      <div>
         <div className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-4">
               <Link href={route('instructors.show', instructor.id)}>
                  <Avatar className="h-12 w-12">
                     <AvatarImage src={user.photo || ''} alt={user.name} className="object-cover" />
                     <AvatarFallback>{user.name.charAt(0)}</AvatarFallback>
                  </Avatar>
               </Link>

               <Link href={route('instructors.show', instructor.id)}>
                  <div className="group">
                     <h3 className="text-xl font-semibold group-hover:underline">{user.name}</h3>
                     <p className="text-muted-foreground">{user.email}</p>
                  </div>
               </Link>
            </div>

            <Button asChild>
               <Link href={route('instructors.show', instructor.id)}>
                  {frontend.courses_by_instructor ?? 'Courses By Instructor'}
               </Link>
            </Button>
         </div>

         <div className="mt-6 flex gap-8">
            <div className="flex items-center gap-2">
               <Book className="h-5 w-5 text-muted-foreground" />
               <span>{courses_count} Courses</span>
            </div>

            <div className="flex items-center gap-2">
               <Users className="h-5 w-5 text-muted-foreground" />
               <span>
                  {total_enrollments_count} {frontend.students}
               </span>
            </div>

            <div className="flex items-center gap-2">
               <Star className="h-5 w-5 text-muted-foreground" />
               <span>{total_reviews_count} Reviews</span>
            </div>

            <div className="flex items-center gap-2">
               <span>{total_average_rating ? Number(total_average_rating).toFixed(1) : 0}</span>
               <RatingStars rating={total_average_rating || 0} starClass="h-4 w-4" />
            </div>
         </div>

         <div className="mt-6 flex gap-8">
            <div className="flex items-center gap-2">
               <Book className="h-5 w-5 text-muted-foreground" />
               <span>{exams_count} Exams</span>
            </div>

            <div className="flex items-center gap-2">
               <Users className="h-5 w-5 text-muted-foreground" />
               <span>
                  {total_exam_instructors_count} {frontend.students}
               </span>
            </div>

            <div className="flex items-center gap-2">
               <Star className="h-5 w-5 text-muted-foreground" />
               <span>{total_exam_reviews_count} Reviews</span>
            </div>

            <div className="flex items-center gap-2">
               <span>{total_exam_average_rating ? Number(total_exam_average_rating).toFixed(1) : 0}</span>
               <RatingStars rating={total_exam_average_rating || 0} starClass="h-4 w-4" />
            </div>
         </div>

         {biography ? (
            <div className="mt-6 space-y-2">
               <h4 className="text-sm font-semibold">{frontend.instructor_biography ?? 'Instructor Biography'}</h4>
               <p className="text-muted-foreground max-w-prose whitespace-pre-wrap text-sm leading-relaxed">{biography}</p>
            </div>
         ) : null}
      </div>
   );
};

export default Instructor;
