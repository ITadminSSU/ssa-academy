import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Check, Compass } from 'lucide-react';
import { useState } from 'react';

export interface LearningPathLink {
   label: string;
   url?: string;
   note?: string;
   clickable?: boolean;
}

export interface LearningPathPayload {
   fundamentals: LearningPathLink;
   advanced: LearningPathLink;
   estimating: LearningPathLink;
   us_experience: LearningPathLink;
   resume?: LearningPathLink;
}

type ExperienceAnswer = 'yes' | 'no' | null;
type YearsAnswer = 'under_1' | '1_to_3' | '3_plus' | null;

interface PathStep {
   connector?: 'and_or';
   items?: LearningPathLink[];
   left?: LearningPathLink[];
   right?: LearningPathLink[];
}

interface Props {
   learningPath: LearningPathPayload;
}

const choiceClass = (selected: boolean) =>
   cn(
      'min-h-11 rounded-md px-4 text-sm font-bold tracking-wide uppercase transition',
      selected
         ? 'bg-[color:var(--brand-red)] text-white'
         : 'border-2 border-[color:var(--brand-red)] bg-background text-primary hover:bg-[color:var(--brand-red)]/10',
   );

const pathItemClass =
   'bg-primary text-primary-foreground flex h-full min-h-[6.5rem] items-center gap-3 rounded-lg px-4 py-3 text-sm font-semibold tracking-wide uppercase shadow-sm';

const buildPath = (experience: ExperienceAnswer, years: YearsAnswer, links: LearningPathPayload): PathStep[] => {
   const resume = links.resume ?? { label: 'Building A Winning Resume', url: '', clickable: false };

   if (experience === 'no') {
      return [
         { items: [links.fundamentals] },
         { items: [links.advanced] },
         { items: [links.estimating] },
         { items: [links.us_experience] },
         { items: [resume] },
      ];
   }

   if (experience === 'yes' && years === 'under_1') {
      return [
         { items: [links.fundamentals] },
         { items: [links.advanced] },
         { items: [links.estimating] },
         { items: [links.us_experience] },
         { items: [resume] },
      ];
   }

   if (experience === 'yes' && years === '1_to_3') {
      return [
         {
            connector: 'and_or',
            left: [links.fundamentals, links.estimating, links.us_experience],
            right: [links.advanced, resume],
         },
      ];
   }

   if (experience === 'yes' && years === '3_plus') {
      return [
         {
            connector: 'and_or',
            left: [links.advanced, links.estimating, links.us_experience],
            right: [links.estimating, resume],
         },
      ];
   }

   return [];
};

const PathItem = ({ item }: { item: LearningPathLink }) => {
   const clickable = item.clickable !== false && Boolean(item.url);
   const className = cn(pathItemClass, clickable ? 'transition hover:bg-primary-dark' : 'cursor-default');
   const inner = (
      <>
         <span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-500 text-white">
            <Check className="h-3.5 w-3.5" />
         </span>
         <span className="min-w-0">
            <span className="block leading-snug">{item.label}</span>
            {item.note ? (
               <span className="mt-1 block text-[11px] font-normal normal-case tracking-normal text-primary-foreground/80">
                  {item.note}
               </span>
            ) : null}
         </span>
      </>
   );

   if (!clickable) {
      return <div className={className}>{inner}</div>;
   }

   return (
      <Link href={item.url as string} className={className}>
         {inner}
      </Link>
   );
};

const LearningPathGuide = ({ learningPath }: Props) => {
   const [open, setOpen] = useState(false);
   const [experience, setExperience] = useState<ExperienceAnswer>(null);
   const [years, setYears] = useState<YearsAnswer>(null);

   const path = buildPath(experience, years, learningPath);
   const showYears = experience === 'yes';
   const showPath = path.length > 0;

   const reset = () => {
      setExperience(null);
      setYears(null);
   };

   return (
      <>
         <div className="flex justify-center">
            <Button
               type="button"
               variant="brand"
               className="h-12 min-w-[260px] px-8 text-base font-semibold shadow-md sm:h-14 sm:min-w-[320px] sm:px-10 sm:text-lg"
               onClick={() => setOpen(true)}
            >
               <Compass className="h-5 w-5" />
               Don't know where to start?
            </Button>
         </div>

         <Dialog
            open={open}
            onOpenChange={(next) => {
               setOpen(next);
               if (!next) {
                  reset();
               }
            }}
         >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
               <DialogHeader>
                  <DialogTitle>Find your starting path</DialogTitle>
                  <DialogDescription>
                     Answer a couple of questions. We'll point you to the right course. Nothing is enrolled until you choose a course.
                  </DialogDescription>
               </DialogHeader>

               <div className="space-y-5">
                  <div className="space-y-3">
                     <p className="bg-primary text-primary-foreground rounded-lg px-4 py-3 text-center text-sm font-semibold tracking-wide uppercase">
                        Do you have experience using a takeoff software?
                     </p>
                     <div className="flex flex-wrap justify-center gap-3">
                        <button type="button" className={choiceClass(experience === 'no')} onClick={() => { setExperience('no'); setYears(null); }}>
                           No
                        </button>
                        <button type="button" className={choiceClass(experience === 'yes')} onClick={() => { setExperience('yes'); setYears(null); }}>
                           Yes
                        </button>
                     </div>
                  </div>

                  {showYears ? (
                     <div className="space-y-3">
                        <p className="bg-primary text-primary-foreground rounded-lg px-4 py-3 text-center text-sm font-semibold tracking-wide uppercase">
                           Years of experience
                        </p>
                        <div className="flex flex-wrap justify-center gap-3">
                           <button type="button" className={choiceClass(years === 'under_1')} onClick={() => setYears('under_1')}>
                              Less than 1 year
                           </button>
                           <button type="button" className={choiceClass(years === '1_to_3')} onClick={() => setYears('1_to_3')}>
                              1 year to 3 years
                           </button>
                           <button type="button" className={choiceClass(years === '3_plus')} onClick={() => setYears('3_plus')}>
                              3 years and above
                           </button>
                        </div>
                     </div>
                  ) : null}

                  {showPath ? (
                     <div className="space-y-3">
                        <p className="text-muted-foreground text-center text-sm">Your recommended path. Click a course to open it.</p>
                        {path.map((step, index) => {
                           const left = step.left ?? (step.items?.length ? [step.items[0]] : []);
                           const right = step.right ?? step.items?.slice(1) ?? [];
                           const stacked = step.items ?? [];

                           if (step.connector === 'and_or' && (left.length > 0 || right.length > 0)) {
                              const rowCount = Math.max(left.length, right.length);

                              return (
                                 <div
                                    key={index}
                                    className="grid items-stretch gap-3 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]"
                                 >
                                    <div className="flex flex-col gap-2">
                                       {Array.from({ length: rowCount }, (_, row) =>
                                          left[row] ? (
                                             <PathItem key={left[row].label} item={left[row]} />
                                          ) : (
                                             <div key={`left-spacer-${row}`} className="min-h-[6.5rem]" />
                                          ),
                                       )}
                                    </div>
                                    <span className="bg-[color:var(--brand-red)] shrink-0 self-center justify-self-center rounded-md px-3 py-1 text-xs font-bold tracking-wide text-white uppercase">
                                       and / or
                                    </span>
                                    <div className="flex flex-col gap-2">
                                       {Array.from({ length: rowCount }, (_, row) =>
                                          right[row] ? (
                                             <PathItem key={right[row].label} item={right[row]} />
                                          ) : (
                                             <div key={`right-spacer-${row}`} className="min-h-[6.5rem]" />
                                          ),
                                       )}
                                    </div>
                                 </div>
                              );
                           }

                           return (
                              <div key={index} className="space-y-2">
                                 {stacked.map((item) => (
                                    <PathItem key={item.label} item={item} />
                                 ))}
                              </div>
                           );
                        })}
                     </div>
                  ) : null}
               </div>
            </DialogContent>
         </Dialog>
      </>
   );
};

export default LearningPathGuide;
