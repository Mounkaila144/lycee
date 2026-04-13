<?php

namespace Modules\NotesEvaluations\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GradeStatisticsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'evaluation_id' => $this->resource['evaluation_id'] ?? null,
            'evaluation_name' => $this->resource['evaluation_name'] ?? null,
            'count' => $this->resource['count'] ?? 0,
            'average' => $this->resource['average'] ?? 0,
            'min' => $this->resource['min'] ?? null,
            'max' => $this->resource['max'] ?? null,
            'median' => $this->resource['median'] ?? 0,
            'std_dev' => $this->resource['std_dev'] ?? 0,
            'pass_rate' => $this->resource['pass_rate'] ?? 0,
            'fail_rate' => $this->resource['fail_rate'] ?? 0,
            'absent_count' => $this->resource['absent_count'] ?? 0,
            'distribution' => $this->resource['distribution'] ?? [],
            'anomalies' => $this->resource['anomalies'] ?? [],
        ];
    }
}
