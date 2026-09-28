import { Target } from 'lucide-react';

const orbitNodes = [
   { index: '01', label: 'Construction plans', slot: 'tl' },
   { index: '02', label: 'PDF plan sets', slot: 'tr' },
   { index: '03', label: 'Estimating', slot: 'ml' },
   { index: '04', label: 'Takeoffs', slot: 'mr' },
   { index: '05', label: 'Terminology', slot: 'bl' },
   { index: '07', label: 'Software', slot: 'bc' },
   { index: '06', label: 'Project documentation', slot: 'br' },
] as const;

const WorkflowRadar = () => (
   <div
      className="ssu-workflow-plot"
      role="img"
      aria-label="Remote collaboration workflow at the center of construction plans, PDF plan sets, estimating, takeoffs, terminology, software, and project documentation"
   >
      <div className="ssu-workflow-plot__field">
         <div className="ssu-workflow-plot__grid" aria-hidden />
         <svg className="ssu-workflow-plot__rings" viewBox="0 0 100 100" aria-hidden>
            <circle cx="50" cy="50" r="38" fill="none" stroke="currentColor" strokeWidth="0.45" />
            <circle cx="50" cy="50" r="26" fill="none" stroke="currentColor" strokeWidth="0.45" />
         </svg>
         <div className="ssu-workflow-plot__hub">
            <Target className="ssu-workflow-plot__target" strokeWidth={1.4} aria-hidden />
            <p className="ssu-workflow-plot__hub-copy">
               Remote
               <br />
               collaboration
               <br />
               workflow
            </p>
         </div>
      </div>
      <div className="ssu-workflow-plot__nodes">
         {orbitNodes.map((node) => (
            <p key={node.index} className={`ssu-workflow-plot__node ssu-workflow-plot__node--${node.slot}`}>
               <span className="ssu-workflow-plot__tick" aria-hidden />
               <span className="ssu-workflow-plot__index">{node.index}</span>
               <span className="ssu-workflow-plot__name">{node.label}</span>
            </p>
         ))}
      </div>
   </div>
);

export default WorkflowRadar;
