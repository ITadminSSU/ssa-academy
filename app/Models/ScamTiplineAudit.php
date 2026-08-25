<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScamTiplineAudit extends Model
{
    protected $table = 'scam_tipline_audits';

    protected $fillable = [
        'scam_tipline_report_id',
        'user_id',
        'action',
        'from_status',
        'to_status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ScamTiplineReport::class, 'scam_tipline_report_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }
}
