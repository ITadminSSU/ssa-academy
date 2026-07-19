import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { StudentDashboardProps } from '@/types/page';
import { usePage } from '@inertiajs/react';
import { ChevronDown, Download, HelpCircle, Search, Video } from 'lucide-react';
import { useMemo, useState } from 'react';

const CATEGORY_LABELS: Record<string, string> = {
   getting_started: 'Getting Started',
   courses: 'Courses',
   exams: 'Exams',
   certificates: 'Certificates',
   account: 'Account & Settings',
   general: 'General',
};

interface HelpArticle {
   id: number;
   category: string;
   title: string;
   body: string | null;
   video_url: string | null;
   video: string | null;
   video_name: string | null;
   file: string | null;
   file_name: string | null;
}

const ArticleItem = ({ article }: { article: HelpArticle }) => {
   const [open, setOpen] = useState(false);

   return (
      <Card className="border">
         <button
            type="button"
            onClick={() => setOpen((v) => !v)}
            className="flex w-full items-center justify-between gap-3 p-4 text-left"
         >
            <span className="font-semibold">{article.title}</span>
            <ChevronDown className={`h-5 w-5 shrink-0 text-muted-foreground transition-transform ${open ? 'rotate-180' : ''}`} />
         </button>

         {open && (
            <CardContent className="space-y-4 border-t pt-4">
               {article.body && (
                  <p className="text-muted-foreground whitespace-pre-line text-sm leading-relaxed">{article.body}</p>
               )}

               {article.video && (
                  <video
                     src={article.video}
                     controls
                     className="w-full rounded-lg border"
                     preload="metadata"
                  >
                     Your browser does not support the video tag.
                  </video>
               )}

               <div className="flex flex-wrap gap-2">
                  {article.video_url && (
                     <Button asChild size="sm" variant="outline">
                        <a href={article.video_url} target="_blank" rel="noopener noreferrer">
                           <Video className="h-4 w-4" />
                           Watch on external link
                        </a>
                     </Button>
                  )}
                  {article.file && (
                     <Button asChild size="sm" variant="outline">
                        <a href={article.file} target="_blank" rel="noopener noreferrer" download={article.file_name ?? true}>
                           <Download className="h-4 w-4" />
                           {article.file_name ?? 'Download guide'}
                        </a>
                     </Button>
                  )}
               </div>

               {!article.body && !article.video && !article.video_url && !article.file && (
                  <p className="text-muted-foreground text-sm">No content available for this article.</p>
               )}
            </CardContent>
         )}
      </Card>
   );
};

const HelpCenter = () => {
   const { helpArticles = [] } = usePage<StudentDashboardProps>().props;
   const [query, setQuery] = useState('');
   const [activeCategory, setActiveCategory] = useState<string>('all');

   const categories = useMemo(() => {
      const present = new Set(helpArticles.map((a) => a.category));
      return ['all', ...Array.from(present)];
   }, [helpArticles]);

   const filtered = useMemo(() => {
      const q = query.trim().toLowerCase();
      return helpArticles.filter((article) => {
         const matchesCategory = activeCategory === 'all' || article.category === activeCategory;
         const matchesQuery =
            q === '' ||
            article.title.toLowerCase().includes(q) ||
            (article.body ?? '').toLowerCase().includes(q);
         return matchesCategory && matchesQuery;
      });
   }, [helpArticles, query, activeCategory]);

   const grouped = useMemo(() => {
      const map = new Map<string, HelpArticle[]>();
      filtered.forEach((article) => {
         const list = map.get(article.category) ?? [];
         list.push(article);
         map.set(article.category, list);
      });
      return Array.from(map.entries());
   }, [filtered]);

   return (
      <div className="space-y-6">
         <div>
            <h1 className="text-2xl font-bold tracking-tight">Help Center</h1>
            <p className="text-muted-foreground mt-1 text-sm">
               Tutorials and guides to help you get the most out of the platform.
            </p>
         </div>

         <div className="relative max-w-md">
            <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
            <Input
               value={query}
               onChange={(e) => setQuery(e.target.value)}
               placeholder="Search tutorials..."
               className="pl-9"
            />
         </div>

         <div className="flex flex-wrap gap-2">
            {categories.map((cat) => (
               <Button
                  key={cat}
                  size="sm"
                  variant={activeCategory === cat ? 'default' : 'outline'}
                  onClick={() => setActiveCategory(cat)}
               >
                  {cat === 'all' ? 'All' : CATEGORY_LABELS[cat] ?? cat}
               </Button>
            ))}
         </div>

         {grouped.length > 0 ? (
            <div className="space-y-6">
               {grouped.map(([category, items]) => (
                  <div key={category} className="space-y-3">
                     <h2 className="text-lg font-semibold">{CATEGORY_LABELS[category] ?? category}</h2>
                     <div className="space-y-3">
                        {items.map((article) => (
                           <ArticleItem key={article.id} article={article} />
                        ))}
                     </div>
                  </div>
               ))}
            </div>
         ) : (
            <Card>
               <CardContent className="flex flex-col items-center justify-center gap-3 p-10 text-center">
                  <HelpCircle className="text-muted-foreground h-10 w-10" />
                  <p className="text-muted-foreground text-sm">
                     {helpArticles.length === 0
                        ? 'No tutorials available yet. Check back soon.'
                        : 'No articles match your search.'}
                  </p>
               </CardContent>
            </Card>
         )}
      </div>
   );
};

export default HelpCenter;
