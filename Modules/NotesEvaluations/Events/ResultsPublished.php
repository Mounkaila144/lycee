<?php

namespace Modules\NotesEvaluations\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\NotesEvaluations\Entities\PublicationRecord;

class ResultsPublished
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public PublicationRecord $publicationRecord
    ) {}

    /**
     * Get the publication record
     */
    public function getPublicationRecord(): PublicationRecord
    {
        return $this->publicationRecord;
    }

    /**
     * Get semester ID
     */
    public function getSemesterId(): int
    {
        return $this->publicationRecord->semester_id;
    }

    /**
     * Get student count
     */
    public function getStudentCount(): int
    {
        return $this->publicationRecord->students_count;
    }
}
