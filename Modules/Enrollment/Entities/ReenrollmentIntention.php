<?php

namespace Modules\Enrollment\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intention de réinscription d'un élève (oui / non) pour une campagne ouverte.
 */
class ReenrollmentIntention extends Model
{
    protected $connection = 'tenant';

    protected $table = 'reenrollment_intentions';

    protected $fillable = [
        'campaign_id',
        'student_id',
        'wants_to_reenroll',
        'comment',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'wants_to_reenroll' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReenrollmentCampaign::class, 'campaign_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
