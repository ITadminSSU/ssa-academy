import { GhostCta, GoldCta, SheetKicker } from '@/components/ssu-public/chrome';

const CallToAction = () => (
   <section className="bg-[color:var(--ssu-navy)] py-20 text-white">
      <div className="container px-4">
         <SheetKicker label="Next sheet" index="15" tone="gold" />
         <h2 className="ssu-pub-display mt-5 max-w-4xl text-[clamp(2.2rem,5vw,4.6rem)]">
            Your experience is the foundation.{' '}
            <span className="text-[color:var(--ssu-gold)]">Build what&apos;s next.</span>
         </h2>
         <p className="mt-6 max-w-2xl text-sm leading-relaxed text-white/75 md:text-base">
            Keep learning. Strengthen your skills. Become more familiar with U.S. construction workflows and prepare for new
            professional opportunities.
         </p>
         <div className="mt-8 flex flex-wrap gap-3">
            <GoldCta href={route('category.courses', { category: 'all' })}>Explore courses</GoldCta>
            <GhostCta href={route('register')}>Create your free account</GhostCta>
         </div>
      </div>
   </section>
);

export default CallToAction;
