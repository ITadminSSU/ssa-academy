import { Head } from '@inertiajs/react';
import { ReactNode, useEffect } from 'react';
import '../../../css/evt-landing.css';

const Icon = ({ name }: { name: string }) => {
   const paths: Record<string, ReactNode> = {
      laptop: (
         <>
            <rect x="3" y="5" width="18" height="12" rx="2" />
            <path d="M2 19h20" />
         </>
      ),
      chart: (
         <>
            <path d="M4 19V9" />
            <path d="M10 19V5" />
            <path d="M16 19v-7" />
            <path d="M22 19H2" />
         </>
      ),
      clipboard: (
         <>
            <rect x="6" y="4" width="12" height="16" rx="2" />
            <path d="M9 4.5h6v2H9z" />
            <path d="M9 11h6M9 15h4" />
         </>
      ),
      users: (
         <>
            <circle cx="9" cy="9" r="3" />
            <circle cx="16" cy="10" r="2.2" />
            <path d="M3.5 18c.6-2.4 2.6-3.5 5.5-3.5s4.9 1.1 5.5 3.5" />
            <path d="M14 14.6c1.6.2 2.9.9 3.5 2.4" />
         </>
      ),
      calendar: (
         <>
            <rect x="4" y="5" width="16" height="15" rx="2" />
            <path d="M8 3v4M16 3v4M4 10h16" />
         </>
      ),
      clock: (
         <>
            <circle cx="12" cy="12" r="8" />
            <path d="M12 8v5l3 2" />
         </>
      ),
      shield: (
         <>
            <path d="M12 3 5 6v6c0 4 3 6.5 7 8 4-1.5 7-4 7-8V6z" />
            <path d="m9 12 2 2 4-4" />
         </>
      ),
      check: <path d="m5 12 4 4 10-8" />,
      gear: (
         <>
            <circle cx="12" cy="12" r="3" />
            <path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M18.4 5.6l-1.6 1.6M7.2 16.8l-1.6 1.6" />
         </>
      ),
      helmet: (
         <>
            <path d="M4 14a8 8 0 0 1 16 0" />
            <path d="M3 14h18" />
            <path d="M5 14v2.5A2.5 2.5 0 0 0 7.5 19h9a2.5 2.5 0 0 0 2.5-2.5V14" />
            <path d="M9 14v-1.5a3 3 0 0 1 6 0V14" />
         </>
      ),
      file: (
         <>
            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" />
            <path d="M14 3v6h6M8 13h8M8 17h5" />
         </>
      ),
      cap: (
         <>
            <path d="m2 10 10-5 10 5-10 5z" />
            <path d="M6 12v4c2 2 10 2 12 0v-4" />
            <path d="M22 10v5" stroke="#f5b400" />
            <circle cx="22" cy="16.5" r="1.35" fill="#f5b400" stroke="none" />
         </>
      ),
      case: (
         <>
            <rect x="3" y="7" width="18" height="13" rx="2" />
            <path d="M8 7V5h8v2M3 13h18" />
         </>
      ),
   };

   return (
      <svg
         viewBox="0 0 24 24"
         width="26"
         height="26"
         fill="none"
         stroke="currentColor"
         strokeWidth="1.8"
         strokeLinecap="round"
         strokeLinejoin="round"
         aria-hidden="true"
      >
         {paths[name]}
      </svg>
   );
};

const WebinarButton = ({ id }: { id?: string }) => (
   <a id={id} className="evt-btn evt-webinar-target" href="/register">
      <span>SIGN UP TO JOIN THE FREE WEBINAR</span>
      <span>ON NOVEMBER 21 →</span>
   </a>
);

export default function EvtLanding() {
   useEffect(() => {
      const id = 'evt-barlow';
      if (document.getElementById(id)) {
         return;
      }

      const link = document.createElement('link');
      link.id = id;
      link.rel = 'stylesheet';
      link.href = 'https://fonts.bunny.net/css?family=barlow:500,600,700,800,900|barlow-condensed:800,900';
      document.head.appendChild(link);
   }, []);

   return (
      <>
         <Head title="Become a Remote Construction Estimator" />
         <div className="evt-page">
            <div className="evt-topbar">
               Start your next career step with a <strong className="evt-red">FREE EVT</strong> <strong>webinar.</strong>
            </div>

            <header className="evt-header">
               <img src="/assets/evt/ssa-mark.png" alt="SmartSourcing USA Academy" />
               <span className="evt-header-rule" aria-hidden="true" />
               <p>EXCLUSIVE VIRTUAL TRAINING</p>
            </header>

            <section className="evt-hero-block">
               <p className="evt-kicker">SMARTSOURCING USA ACADEMY PRESENTS:</p>
               <div className="evt-hero">
                  <div className="evt-hero-copy">
                     <h1>
                        <span className="evt-h-line">
                           BECOME A <span className="evt-gold-word">REMOTE</span>
                        </span>
                        <span className="evt-h-line evt-gold-word">CONSTRUCTION ESTIMATOR</span>
                     </h1>
                     <p className="evt-intro">
                        Introducing the <span className="evt-red">Exclusive Virtual Training (EVT).</span>
                     </p>
                     <p className="evt-lead">
                        Specialized, practical training in <strong>Roofing, Siding &amp; Drywall</strong> with a structured pathway from{' '}
                        <strong>Dec 1, 2026 – Apr 1, 2027</strong>.
                     </p>
                     <ul className="evt-features">
                        <li>
                           <span className="evt-icon-bubble">
                              <Icon name="laptop" />
                           </span>
                           <span>
                              Specialized
                              <br />
                              Training
                           </span>
                        </li>
                        <li>
                           <span className="evt-icon-bubble">
                              <Icon name="chart" />
                           </span>
                           <span>
                              U.S. Estimating
                              <br />
                              Workflows
                           </span>
                        </li>
                        <li>
                           <span className="evt-icon-bubble">
                              <Icon name="clipboard" />
                           </span>
                           <span>
                              Practical
                              <br />
                              Exercises
                           </span>
                        </li>
                        <li>
                           <span className="evt-icon-bubble">
                              <Icon name="users" />
                           </span>
                           <span>
                              Structured
                              <br />
                              Pathway
                           </span>
                        </li>
                     </ul>
                     <WebinarButton id="evt-webinar" />
                     <p className="evt-experience">
                        <i aria-hidden="true" />
                        <span>
                           Have 1 year U.S. experience? <a href="#evt-estimator">Apply as an Estimator</a> or{' '}
                           <a href="#evt-scholarship">Check for Scholarship.</a>
                        </span>
                        <i aria-hidden="true" />
                     </p>
                  </div>
                  <img className="evt-mascot" src="/assets/evt/mascot.png" alt="EVT mascot pointing toward the training" />
               </div>
            </section>

            <div className="evt-lower">
               <div className="evt-strip">
                  <span>
                     <Icon name="users" />
                     FOR ASPIRING CONSTRUCTION ESTIMATORS
                  </span>
                  <span>Roofing | Siding | Drywall</span>
                  <span className="evt-strip-rule" aria-hidden="true" />
                  <span>
                     <Icon name="laptop" />
                     ENTRY VIA <span className="evt-gold">FREE WEBINAR</span>
                  </span>
               </div>

               <section className="evt-steps">
                  <div className="evt-wrap">
                     <div className="evt-section-head">
                        <span className="evt-section-mark" aria-hidden="true" />
                        <div>
                           <p className="evt-section-kicker">YOUR FIRST STEP STARTS HERE</p>
                           <h2>
                              BEFORE YOU COMMIT, <span>SEE WHAT&apos;S POSSIBLE.</span>
                           </h2>
                        </div>
                     </div>
                     <div className="evt-step-grid">
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">01</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="chart" />
                              </span>
                           </div>
                           <h3>THE OPPORTUNITY</h3>
                           <p>Discover U.S. construction opportunities and in-demand skills.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">02</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="laptop" />
                              </span>
                           </div>
                           <h3>THE TRAINING</h3>
                           <p>Get inside the EVT program—training, instructor support, and hands-on estimating.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">03</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="users" />
                              </span>
                           </div>
                           <h3>YOUR NEXT STEP</h3>
                           <p>Learn about application pathways, placement considerations, and career preparation.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">04</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="shield" />
                              </span>
                           </div>
                           <h3>MONEYBACK GUARANTEE</h3>
                           <ul className="evt-checks">
                              <li>
                                 <Icon name="check" />
                                 <span>No placement by Aug 31, 2027</span>
                              </li>
                              <li>
                                 <Icon name="check" />
                                 <span>At least 50+ projects accomplished by Mar 31, 2027</span>
                              </li>
                              <li>
                                 <Icon name="check" />
                                 <span>Attended all additional trainings</span>
                              </li>
                           </ul>
                        </article>
                     </div>
                     <div className="evt-session">
                        <div className="evt-session-items">
                           <article>
                              <Icon name="calendar" />
                              <span>
                                 Saturday,
                                 <br />
                                 Nov 21, 2026
                              </span>
                           </article>
                           <span className="evt-session-rule" aria-hidden="true" />
                           <article>
                              <Icon name="clock" />
                              <span>
                                 8:00 PM
                                 <br />
                                 PHT
                              </span>
                           </article>
                           <span className="evt-session-rule" aria-hidden="true" />
                           <article>
                              <Icon name="laptop" />
                              <span className="evt-reg">
                                 <span className="evt-red evt-free">FREE</span>
                                 Registration
                              </span>
                           </article>
                        </div>
                        <a className="evt-btn evt-session-btn" href="/register">
                           <span>SIGN UP TO JOIN THE FREE</span>
                           <span>WEBINAR ON NOVEMBER 21 →</span>
                        </a>
                     </div>
                  </div>
               </section>

               <section className="evt-why">
                  <div className="evt-why-banner">
                     <div className="evt-wrap">
                        <p className="evt-why-kicker">WHY CHOOSE EVT?</p>
                        <h2>
                           NOT JUST LESSONS. <span>SKILLS YOU CAN PUT TO WORK.</span>
                        </h2>
                        <p>
                           EVT goes beyond online learning with U.S.-focused estimating training, industry-experienced instructors, and practical
                           exercises to build career-ready skills.
                        </p>
                     </div>
                     <img className="evt-why-photo" src="/assets/evt/hard-hat.png" width={628} height={224} alt="" />
                  </div>
                  <div className="evt-wrap evt-why-grid">
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="gear" />
                        </span>
                        <div>
                           <p>01. SPECIALIZED TRAINING</p>
                           <h3>
                              U.S. PROJECT
                              <br />
                              ESTIMATING
                           </h3>
                           <small>Roofing | Siding | Drywall</small>
                        </div>
                     </article>
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="helmet" />
                        </span>
                        <div>
                           <p>02. INDUSTRY INSTRUCTORS</p>
                           <h3>
                              LEARN FROM
                              <br />
                              WORKING ESTIMATORS
                           </h3>
                           <small>Real U.S. project experience</small>
                        </div>
                     </article>
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="file" />
                        </span>
                        <div>
                           <p>03. PRACTICAL EXPERIENCE</p>
                           <h3>
                              51 PROJECTS.
                              <br />
                              REAL PRACTICE.
                           </h3>
                           <small>Hands-on estimating exercises</small>
                        </div>
                     </article>
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="chart" />
                        </span>
                        <div>
                           <p>04. CAREER PREPARATION</p>
                           <h3>
                              PREPARE FOR
                              <br />
                              WHAT COMES NEXT.
                           </h3>
                           <small>Skills, support &amp; U.S. opportunities</small>
                        </div>
                     </article>
                  </div>
               </section>

               <section className="evt-paths">
                  <div className="evt-wrap">
                     <h2>
                        YOUR EXPERIENCE. <span>YOUR NEXT STEP.</span>
                     </h2>
                     <p className="evt-paths-lead">Choose the pathway that matches your current experience and career goal.</p>
                     <div className="evt-path-grid">
                        <article className="evt-path">
                           <span className="evt-path-badge">PATH 01</span>
                           <div className="evt-path-title">
                              <Icon name="cap" />
                              <h3>
                                 NEW TO U.S. ESTIMATING?
                                 <span>START HERE.</span>
                              </h3>
                           </div>
                           <p className="evt-path-copy">Get introduced to U.S. estimating, the EVT program, and how to get started.</p>
                           <a className="evt-btn" href="/register">
                              JOIN THE FREE WEBINAR →
                           </a>
                           <div className="evt-qualify" />
                        </article>
                        <article className="evt-path evt-path-featured" id="evt-scholarship">
                           <span className="evt-path-badge">PATH 02</span>
                           <div className="evt-path-title">
                              <Icon name="users" />
                              <h3>
                                 WITH U.S. EXPERIENCE?
                                 <span>APPLY FOR A SCHOLARSHIP.</span>
                              </h3>
                           </div>
                           <p className="evt-path-copy">
                              Qualified participants may receive a scholarship, advanced learning, and a chance to help mentor other EVT trainees.
                           </p>
                           <a className="evt-btn" href="/register">
                              APPLY FOR SCHOLARSHIP →
                           </a>
                           <div className="evt-qualify">
                              <strong>Who qualifies:</strong>
                              <p>
                                 <Icon name="check" />
                                 <span>At least 1 year of U.S. estimating experience in Roofing, Siding, or Drywall or 51+ U.S. projects.</span>
                              </p>
                           </div>
                        </article>
                        <article className="evt-path" id="evt-estimator">
                           <span className="evt-path-badge">PATH 03</span>
                           <div className="evt-path-title">
                              <Icon name="case" />
                              <h3>READY FOR A DIRECT OPPORTUNITY?</h3>
                           </div>
                           <p className="evt-path-copy">
                              Submit your profile for consideration for direct employment opportunities with our U.S. construction clients.
                           </p>
                           <a className="evt-btn" href="/register">
                              APPLY AS AN ESTIMATOR →
                           </a>
                           <div className="evt-qualify">
                              <strong>Who qualifies:</strong>
                              <p>
                                 <Icon name="check" />
                                 <span>At least 1 year of U.S. estimating experience in Roofing, Siding, or Drywall or 51+ U.S. projects.</span>
                              </p>
                           </div>
                        </article>
                     </div>
                  </div>
               </section>

               <footer className="evt-footer">
                  <h2>
                     READY TO TAKE <span>THE NEXT STEP?</span>
                  </h2>
                  <p>Join the FREE webinar and discover how EVT can help you build practical skills, real experience, and future opportunities.</p>
               </footer>
            </div>
         </div>
      </>
   );
}
