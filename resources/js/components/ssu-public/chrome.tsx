import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ArrowRight, ArrowUp } from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

export const SheetKicker = ({
   label,
   index,
   tone = 'muted',
}: {
   label: string;
   index: string;
   tone?: 'muted' | 'gold';
}) => (
   <p className={cn('ssu-pub-kicker', tone === 'gold' && 'ssu-pub-kicker--gold')}>
      <span className="ssu-pub-kicker-line" aria-hidden />
      {label} / {index}
   </p>
);

export const GoldCta = ({
   href,
   children,
   className,
   external = false,
}: {
   href: string;
   children: ReactNode;
   className?: string;
   external?: boolean;
}) => {
   const classes = cn('ssu-pub-cta', className);

   if (external || href.startsWith('#')) {
      return (
         <a href={href} className={classes} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>
            {children}
            <ArrowRight className="h-3.5 w-3.5" />
         </a>
      );
   }

   return (
      <Link href={href} className={classes}>
         {children}
         <ArrowRight className="h-3.5 w-3.5" />
      </Link>
   );
};

export const GhostCta = ({ href, children, className }: { href: string; children: ReactNode; className?: string }) => {
   const classes = cn(
      'ssu-pub-ghost inline-flex items-center gap-2 rounded-[2px] px-4 py-2.5 font-[family-name:var(--ssu-font-display)] text-[0.95rem] tracking-[0.08em] text-white uppercase transition',
      className,
   );

   if (href.startsWith('#')) {
      return (
         <a href={href} className={classes}>
            {children}
         </a>
      );
   }

   return (
      <Link href={href} className={classes}>
         {children}
      </Link>
   );
};

export const PublicPageHero = ({
   kicker,
   title,
   description,
}: {
   kicker: string;
   title: string;
   description: string;
}) => (
   <section className="relative overflow-hidden bg-[color:var(--ssu-navy)] text-[color:var(--ssu-hero-type)]">
      <div className="pointer-events-none absolute inset-0" aria-hidden>
         <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full border border-[color:var(--ssu-gold)]/25" />
         <div className="absolute -right-8 top-10 h-[28rem] w-[28rem] rounded-full border border-[color:var(--ssu-gold)]/15" />
      </div>
      <div className="relative container px-4 py-16 md:py-24">
         <SheetKicker label={kicker} index="SSU" tone="gold" />
         <h1 className="ssu-pub-display mt-5 max-w-4xl text-4xl text-white md:text-6xl">{title}</h1>
         <p className="mt-5 max-w-2xl text-sm leading-relaxed text-white/75 md:text-base">{description}</p>
      </div>
   </section>
);

export const BackToTop = () => {
   const [visible, setVisible] = useState(false);

   useEffect(() => {
      const onScroll = () => setVisible(window.scrollY > 480);
      onScroll();
      window.addEventListener('scroll', onScroll, { passive: true });
      return () => window.removeEventListener('scroll', onScroll);
   }, []);

   if (!visible) {
      return null;
   }

   return (
      <a href="#top" className="ssu-pub-backtop" aria-label="Back to top">
         <ArrowUp className="h-4 w-4" />
      </a>
   );
};
