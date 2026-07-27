export const SSU_STAT_TONES = [
   { bg: 'bg-primary/10', text: 'text-primary' },
   { bg: 'bg-accent/10', text: 'text-accent' },
   { bg: 'bg-muted', text: 'text-muted-foreground' },
] as const;

export function ssuStatTone(index: number) {
   return SSU_STAT_TONES[index % SSU_STAT_TONES.length];
}

export const SSU_BADGE_TONES = [
   'bg-primary/10 text-primary border-primary/20 hover:bg-primary/10',
   'bg-accent/10 text-accent border-accent/20 hover:bg-accent/10',
   'bg-muted text-muted-foreground border-border/60 hover:bg-muted',
] as const;

export function ssuBadgeTone(index: number) {
   return SSU_BADGE_TONES[index % SSU_BADGE_TONES.length];
}
