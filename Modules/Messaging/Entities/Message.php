<?php

namespace Modules\Messaging\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Enrollment\Entities\Student;
use Modules\UsersGuard\Entities\TenantUser;

class Message extends Model
{
    use SoftDeletes;

    protected $connection = 'tenant';

    protected $table = 'messages';

    protected $fillable = [
        'sender_id', 'recipient_id', 'thread_id',
        'student_context_id', 'subject', 'body', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'sender_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'recipient_id');
    }

    public function studentContext(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_context_id');
    }
}
