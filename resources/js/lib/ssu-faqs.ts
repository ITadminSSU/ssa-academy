export type PublicFaq = {
   question: string;
   paragraphs: string[];
   bullets?: string[];
   after?: string[];
};

export const publicFaqs: PublicFaq[] = [
   {
      question: 'What is SmartSourcing USA Academy?',
      paragraphs: [
         'SmartSourcing USA Academy is a construction-focused training platform designed to help aspiring and current construction VAs and construction professionals develop practical skills for remote construction work.',
         'The Academy focuses on U.S. construction workflows, industry tools, terminology, project documents, and practical training to help learners prepare for real-world remote construction opportunities.',
      ],
   },
   {
      question: 'Who can join?',
      paragraphs: [
         'SmartSourcing USA Academy is for engineers, architects, estimators, quantity surveyors, construction professionals, students, fresh graduates, aspiring construction VAs, and anyone interested in building practical skills for a remote construction career.',
         'You do not need to have previous U.S. construction experience to start learning.',
      ],
   },
   {
      question: 'Do I need construction experience?',
      paragraphs: [
         'No. Previous U.S. construction experience is not required.',
         'Some courses may be easier if you already have a construction background, but the Academy is built so students, career changers, and working professionals can start from where they are.',
      ],
   },
   {
      question: 'Do I need to be an engineer?',
      paragraphs: [
         'No. You do not need to be a licensed engineer to enroll.',
         'SmartSourcing USA Academy is open to anyone who wants to develop construction-related skills and learn more about working remotely in the construction industry.',
      ],
   },
   {
      question: 'What courses are available?',
      paragraphs: [
         'Training topics may include practical skills commonly used in remote construction work, such as:',
      ],
      bullets: [
         'Construction estimating',
         'Quantity takeoff',
         'Blueprint and plan reading',
         'U.S. construction terminology',
         'Project specifications and construction documents',
         'Estimating and takeoff software',
         'Remote communication and collaboration',
         'Trade-specific construction skills',
      ],
      after: ['Browse the live catalog for current programs. Available courses may vary as new programs are introduced.'],
   },
   {
      question: 'How does the training work?',
      paragraphs: [
         'Courses are pre-recorded but interactive, so you can study at your own pace while staying connected throughout the training.',
         'Depending on the course, learners may have access to:',
      ],
      bullets: [
         'Pre-recorded lessons',
         'Interactive quizzes and assessments',
         'Instructor communication through the platform',
         'An exclusive Facebook community for Academy learners',
         'Additional learning resources and activities',
      ],
   },
   {
      question: 'Are courses self-paced?',
      paragraphs: [
         'Yes. You can learn at your own pace, track your progress, and revisit lessons whenever you need to. Access does not expire after enrollment.',
      ],
   },
   {
      question: 'What software is covered?',
      paragraphs: [
         'Tool coverage varies by course. Training may include digital takeoff and project tools commonly used in U.S. construction workflows, such as PlanSwift, Bluebeam, On-Screen Takeoff, AutoCAD, Revit, Primavera, and Procore.',
         'Review each course page for its specific software focus.',
      ],
   },
   {
      question: 'Do I receive a certificate?',
      paragraphs: [
         'Complete your program and earn an SSU-verified credential with a unique reference number. Credential details can vary by course — review the course page for what is included.',
      ],
   },
   {
      question: 'Are credentials verified?',
      paragraphs: [
         'Yes. SSU-verified credentials include a unique reference number and a digital record of completion.',
      ],
   },
   {
      question: 'Does completing a course guarantee a job?',
      paragraphs: [
         'No. Academy enrollment does not guarantee employment, placement, clients, income, or employment with SmartSourcing USA.',
         'Any engagement opportunity still depends on qualifications, skills, experience, interview performance, client requirements, available positions, and the applicable hiring process.',
      ],
   },
   {
      question: 'How does the Academy connect to SmartSourcing USA?',
      paragraphs: [
         'SMARTSOURCING USA ACADEMY is the learning and development platform of SMARTSOURCING USA, built to help construction professionals strengthen their skills and prepare for opportunities in a global construction environment.',
         'The Academy and SmartSourcing USA are connected as an ecosystem, but completing a course does not place you onto a SmartSourcing USA team.',
      ],
   },
   {
      question: 'How much does it cost?',
      paragraphs: [
         'Pricing varies depending on the training course.',
         'Complete details, including the price, inclusions, and enrollment options, are listed on each course in the catalog.',
      ],
   },
   {
      question: 'When will SmartSourcing USA Academy launch?',
      paragraphs: [
         'The Academy is live. Browse the catalog for current courses, schedules, and enrollment options.',
      ],
   },
   {
      question: 'What makes SMARTSOURCING USA Academy unique?',
      paragraphs: [
         'Instead of focusing only on theory or software tutorials, the Academy helps learners understand how construction professionals work with plans, specifications, takeoffs, estimating processes, terminology, project documents, industry tools, and remote workflows.',
      ],
   },
   {
      question: 'After enrolling in a course, do I need to purchase each module/lesson separately?',
      paragraphs: [
         'No. Each training course has a one-time enrollment fee that gives you access to all modules and lessons included in your chosen course. Your access does not expire.',
      ],
   },
   {
      question: 'Can students join SmartSourcing USA Academy?',
      paragraphs: [
         'Yes. Students and fresh graduates who are interested in construction and remote career opportunities are welcome to join.',
      ],
   },
   {
      question: 'Can current construction professionals join SmartSourcing USA Academy?',
      paragraphs: [
         'Yes. The Academy is also designed for experienced construction professionals who want to expand their skills, learn U.S. construction workflows, become familiar with industry tools, or prepare to transition into remote construction work.',
      ],
   },
   {
      question: 'How do I join the Construction VA Academy Facebook community?',
      paragraphs: [
         'You may join the Construction VA Academy Facebook community through this link:',
         'https://www.facebook.com/groups/constructionvaacademy',
         'Please make sure to answer all membership questions, as completing them is required for membership approval.',
      ],
   },
   {
      question: 'How can I stay updated about SmartSourcing USA Academy?',
      paragraphs: ['Follow SmartSourcing USA and join the Construction VA Academy Facebook community to receive updates about:'],
      bullets: [
         'Enrollment',
         'New courses and training programs',
         'Academy announcements',
         'Construction learning resources',
      ],
   },
];

export const homeFaqQuestions = [
   'What is SmartSourcing USA Academy?',
   'Who can join?',
   'Do I need construction experience?',
   'Do I need to be an engineer?',
   'What courses are available?',
   'How does the training work?',
   'Are courses self-paced?',
   'What software is covered?',
   'Do I receive a certificate?',
   'Are credentials verified?',
   'Does completing a course guarantee a job?',
   'How does the Academy connect to SmartSourcing USA?',
   'How much does it cost?',
];

export const homeFaqs = homeFaqQuestions
   .map((question) => publicFaqs.find((faq) => faq.question === question))
   .filter((faq): faq is PublicFaq => Boolean(faq));
