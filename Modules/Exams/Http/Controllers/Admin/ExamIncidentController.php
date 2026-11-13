<?php

namespace Modules\Exams\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Exams\Entities\ExamIncident;
use Modules\Exams\Entities\ExamSession;
use Modules\Exams\Services\ExamSupervisionService;

class ExamIncidentController extends Controller
{
    public function __construct(private ExamSupervisionService $supervisionService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_session_id' => 'required|exists:tenant.exam_sessions,id',
            'student_id' => 'nullable|exists:tenant.students,id',
            'type' => 'required|in:cheating,disturbance,technical,medical,other',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'severity' => 'required|in:low,medium,high,critical',
            'occurred_at_time' => 'required',
            'witnesses' => 'nullable|array',
        ]);

        $incident = $this->supervisionService->reportIncident($validated);

        return response()->json($incident, 201);
    }

    public function update(Request $request, int $incident): JsonResponse
    {
        $incident = ExamIncident::query()->findOrFail($incident);

        $validated = $request->validate([
            'description' => 'sometimes|string',
            'action_taken' => 'nullable|string',
            'resolution_notes' => 'nullable|string',
        ]);

        $incident->update($validated);

        return response()->json($incident->fresh());
    }

    public function updateStatus(Request $request, int $incident): JsonResponse
    {
        $incident = ExamIncident::query()->findOrFail($incident);

        $validated = $request->validate([
            'status' => 'required|in:reported,under_review,resolved,escalated',
            'resolution_notes' => 'nullable|string',
        ]);

        $incident = $this->supervisionService->updateIncidentStatus($incident, $validated['status'], $validated);

        return response()->json($incident);
    }

    public function addEvidence(Request $request, int $incident): JsonResponse
    {
        $incident = ExamIncident::query()->findOrFail($incident);

        $validated = $request->validate([
            'evidence_path' => 'required|string',
        ]);

        $incident = $this->supervisionService->addIncidentEvidence($incident, $validated['evidence_path']);

        return response()->json($incident);
    }

    public function escalate(Request $request, int $incident): JsonResponse
    {
        $incident = ExamIncident::query()->findOrFail($incident);

        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $incident = $this->supervisionService->escalateIncident($incident, $validated['reason']);

        return response()->json($incident);
    }

    public function sessionIncidents(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $incidents = $session->incidents()->with(['student', 'reporter', 'reviewer'])->get();

        return response()->json($incidents);
    }

    public function summary(int $session): JsonResponse
    {
        $session = ExamSession::query()->findOrFail($session);
        $summary = $this->supervisionService->getIncidentsSummary($session);

        return response()->json($summary);
    }
}
