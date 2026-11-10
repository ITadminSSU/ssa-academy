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
      stepChart: (
         <>
            <path d="M6 18V11" />
            <path d="M12 18V7" />
            <path d="M18 18V4" />
            <path d="M4 18h16" />
         </>
      ),
      stepLaptop: (
         <>
            <rect x="4.5" y="4" width="15" height="10" rx="1.4" />
            <path d="M2 18h20" />
            <path d="M8 14h8" />
         </>
      ),
      stepPeople: (
         <>
            <circle cx="9" cy="7.5" r="2.4" />
            <path d="M4.4 18c.5-2.8 2.3-4.3 4.6-4.3s4.1 1.5 4.6 4.3" />
            <circle cx="15.6" cy="8.6" r="1.9" />
            <path d="M13.2 18c.3-2 1.3-3.1 2.5-3.1 1.2 0 2.2 1.1 2.5 3.1" />
         </>
      ),
      stepShield: (
         <>
            <path d="M12 3 5 6.2v6c0 3.8 2.8 6.4 7 7.8 4.2-1.4 7-4 7-7.8v-6z" />
            <path d="m8.8 12.1 2.2 2.2 4.2-4.3" />
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
      whyGear: (
         <>
            <circle cx="12" cy="12" r="2.7" />
            <path d="M12 4.6a7.4 7.4 0 0 1 0 14.8 7.4 7.4 0 0 1 0-14.8z" />
            <path d="M12 2.2v2.6M12 19.2v2.6M2.2 12h2.6M19.2 12h2.6M4.8 4.8l1.9 1.9M17.3 17.3l1.9 1.9M19.2 4.8l-1.9 1.9M6.7 17.3l-1.9 1.9" />
         </>
      ),
      whyHelmet: (
         <>
            <path d="M4.5 13.2a7.5 7.5 0 0 1 15 0" />
            <path d="M3 13.2h18" />
            <path d="M6 13.2v2.2a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-2.2" />
            <path d="M12 5.4V8" />
         </>
      ),
      whyClipboard: (
         <>
            <rect x="6" y="3.5" width="12" height="17" rx="1.6" />
            <path d="M9 3.4h6v2.2H9z" />
            <path d="M9 10.5h6M9 14h4" />
         </>
      ),
      whyChart: (
         <>
            <path d="M6 17.5V11" />
            <path d="M12 17.5V7" />
            <path d="M18 17.5V4" />
            <path d="M4 17.5h16" />
         </>
      ),
      pathCap: (
         <>
            <path d="m3 10 9-5 9 5-9 4.6z" />
            <path d="M7.2 12.2v3.4c1.5 1.5 8.1 1.5 9.6 0v-3.4" />
            <path d="M20 10.4v5" stroke="#f5b400" />
            <circle cx="20" cy="16.2" r="1.25" fill="#f5b400" stroke="none" />
         </>
      ),
      pathPeople: (
         <>
            <circle cx="12" cy="7" r="2.15" />
            <circle cx="7.2" cy="8.5" r="1.65" />
            <circle cx="16.8" cy="8.5" r="1.65" />
            <path d="M8.3 18.2c.35-2.3 1.7-3.5 3.7-3.5s3.35 1.2 3.7 3.5" />
            <path d="M4 18.2c.25-1.7 1.1-2.6 2.4-2.6" />
            <path d="M20 18.2c-.25-1.7-1.1-2.6-2.4-2.6" />
         </>
      ),
      pathCase: (
         <>
            <rect x="3" y="8" width="18" height="11" rx="1.6" />
            <path d="M8 8V6.2A1.2 1.2 0 0 1 9.2 5h5.6A1.2 1.2 0 0 1 16 6.2V8" />
            <path d="M3 13h18" />
         </>
      ),
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
                     <p className="evt-intro">Introducing the Exclusive Virtual Training (EVT).</p>
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
                     <div className="evt-hero-cta">
                        <WebinarButton id="evt-webinar" />
                     </div>
                  </div>
                  <p className="evt-experience">
                     <i aria-hidden="true" />
                     <span>
                        Have 1 year U.S. experience? <a href="#evt-estimator">Apply as an Estimator</a> or{' '}
                        <a href="#evt-scholarship">Check for Scholarship.</a>
                     </span>
                     <i aria-hidden="true" />
                  </p>
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
                        <h2>
                           BEFORE YOU COMMIT, <span>SEE WHAT&apos;S POSSIBLE.</span>
                        </h2>
                     </div>
                     <div className="evt-step-grid">
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">01</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="stepChart" />
                              </span>
                           </div>
                           <h3>THE OPPORTUNITY</h3>
                           <p>Discover U.S. construction opportunities and in-demand skills.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">02</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="stepLaptop" />
                              </span>
                           </div>
                           <h3>THE TRAINING</h3>
                           <p>Get inside the EVT program—training, instructor support, and hands-on estimating.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">03</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="stepPeople" />
                              </span>
                           </div>
                           <h3>YOUR NEXT STEP</h3>
                           <p>Learn about application pathways, placement considerations, and career preparation.</p>
                        </article>
                        <article className="evt-step">
                           <div className="evt-step-top">
                              <span className="evt-num">04</span>
                              <span className="evt-icon-bubble">
                                 <Icon name="stepShield" />
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
                        <p className="evt-why-kicker">EVT PROGRAM</p>
                        <h2>
                           NOT JUST LESSONS, <span>SKILLS YOU CAN PUT TO WORK.</span>
                        </h2>
                        <p>
                           EVT is not just online learning — it&apos;s U.S.-focused estimating training, real project exposure and instruction, and a
                           clear pathway to build career-ready skills.
                        </p>
                     </div>
                     <img className="evt-why-photo" src="/assets/evt/hard-hat.jpg" width={1448} height={1086} alt="SSA hard hat on construction blueprints" />
                  </div>
                  <div className="evt-wrap evt-why-grid">
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="whyGear" />
                        </span>
                        <div>
                           <p>U.S. STANDARDS TRAINING</p>
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
                           <Icon name="whyHelmet" />
                        </span>
                        <div>
                           <p>REAL INDUSTRY INSTRUCTION</p>
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
                           <Icon name="whyClipboard" />
                        </span>
                        <div>
                           <p>PRACTICAL, HANDS-ON</p>
                           <h3>
                              50 PROJECTS,
                              <br />
                              REAL PRACTICE.
                           </h3>
                           <small>Hands-on estimating exercises</small>
                        </div>
                     </article>
                     <article className="evt-why-card">
                        <span className="evt-icon-bubble">
                           <Icon name="whyChart" />
                        </span>
                        <div>
                           <p>CAREER PREPARATION</p>
                           <h3>
                              PREPARE FOR
                              <br />
                              WHAT COMES NEXT.
                           </h3>
                           <small>Skills beyond U.S. estimating</small>
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
                              <Icon name="pathCap" />
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
                              <Icon name="pathPeople" />
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
                              <Icon name="pathCase" />
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
