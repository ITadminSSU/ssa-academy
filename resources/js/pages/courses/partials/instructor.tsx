const Instructor = ({ course, tone = 'dark', showBiography = false }: { course: Course; tone?: 'light' | 'dark'; showBiography?: boolean }) => {
   const instructor = course.instructor;
   const user = instructor?.user;
   if (!instructor || !user?.name) {
      return null;
   }

   const photo = course.instructor_card_photo?.trim() || user.photo?.trim() || '';
   const biography = course.instructor_card_biography?.trim() || instructor.biography?.trim() || '';
   const paragraphs = biography
      .split(/\n+/)
      .map((paragraph) => paragraph.trim())
      .filter(Boolean);
   const text = tone === 'light' ? 'text-white/90' : 'text-[#1a344f]';

   return (
      <div>
         <p className={`flex items-center gap-2 text-sm font-medium ${text}`}>
            {photo ? <img src={photo} alt="" className="h-8 w-8 shrink-0 rounded-full object-cover" /> : null}
            <span>{user.name}</span>
         </p>
         {showBiography && paragraphs.length > 0 ? (
            <div className="mt-4">
               {paragraphs.map((paragraph, index) => (
                  <p key={index} className="mb-3 text-[13px] leading-relaxed text-[#4a5966] last:mb-0">
                     {paragraph}
                  </p>
               ))}
            </div>
         ) : null}
      </div>
   );
};

export default Instructor;
