export const SSU_STAT_TONES = [
   { bg: 'bg-primary/10', text: 'text-primary' },
   { bg: 'bg-[color:var(--sidebar-accent)]/40', text: 'text-foreground' },
   { bg: 'bg-[color:var(--brand-red)]/10', text: 'text-[color:var(--brand-red)]' },
] as const;

export function ssuStatTone(index: number) {
   return SSU_STAT_TONES[index % SSU_STAT_TONES.length];
}

export const SSU_BADGE_TONES = [
   'bg-primary/10 text-primary border-primary/20 hover:bg-primary/10',
   'bg-muted text-foreground border-border/60 hover:bg-muted',
   'bg-[color:var(--brand-red)]/10 text-[color:var(--brand-red)] border-[color:var(--brand-red)]/20 hover:bg-[color:var(--brand-red)]/10',
] as const;

export function ssuBadgeTone(index: number) {
   return SSU_BADGE_TONES[index % SSU_BADGE_TONES.length];
}
