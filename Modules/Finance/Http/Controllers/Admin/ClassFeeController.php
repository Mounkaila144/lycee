<?php

namespace Modules\Finance\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Finance\Entities\ClassFee;
use Modules\StructureAcademique\Entities\Classe;

/**
 * Manage the mandatory fee schedule per class (tuition + registration).
 * The sum of a class's lines is what every enrolled student must pay; an
 * invoice is generated automatically on enrollment from these lines.
 */
class ClassFeeController extends Controller
{
    /**
     * Fee lines configured for a class (+ total).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
        ]);

        $lines = ClassFee::on('tenant')
            ->with('feeType:id,name,code')
            ->where('class_id', $validated['class_id'])
            ->orderBy('id')
            ->get()
            ->map(fn (ClassFee $f) => [
                'id' => $f->id,
                'fee_type_id' => $f->fee_type_id,
                'label' => $f->label ?: $f->feeType?->name,
                'amount' => (float) $f->amount,
            ]);

        return response()->json([
            'data' => [
                'class_id' => (int) $validated['class_id'],
                'lines' => $lines,
                'total' => round($lines->sum('amount'), 2),
            ],
        ]);
    }

    /**
     * Replace the whole fee schedule of a class in one call.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
            'lines' => 'present|array',
            'lines.*.fee_type_id' => 'nullable|integer|exists:tenant.fee_types,id',
            'lines.*.label' => 'nullable|string|max:255',
            'lines.*.amount' => 'required|numeric|min:0',
        ]);

        $classId = (int) $validated['class_id'];

        DB::connection('tenant')->transaction(function () use ($classId, $validated) {
            ClassFee::on('tenant')->where('class_id', $classId)->delete();

            foreach ($validated['lines'] as $line) {
                ClassFee::on('tenant')->create([
                    'class_id' => $classId,
                    'fee_type_id' => $line['fee_type_id'] ?? null,
                    'label' => $line['label'] ?? null,
                    'amount' => $line['amount'],
                ]);
            }
        });

        return $this->index($request);
    }

    /**
     * Copy a class's fee schedule to every other class of the same level
     * (handy: define once for "6ème A" and apply to all 6ème).
     */
    public function applyToLevel(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'class_id' => 'required|integer|exists:tenant.classes,id',
        ]);

        $source = Classe::on('tenant')->findOrFail($validated['class_id']);
        $sourceLines = ClassFee::on('tenant')->where('class_id', $source->id)->get();

        $targets = Classe::on('tenant')
            ->where('level_id', $source->level_id)
            ->where('academic_year_id', $source->academic_year_id)
            ->where('id', '!=', $source->id)
            ->pluck('id');

        DB::connection('tenant')->transaction(function () use ($targets, $sourceLines) {
            foreach ($targets as $targetId) {
                ClassFee::on('tenant')->where('class_id', $targetId)->delete();
                foreach ($sourceLines as $line) {
                    ClassFee::on('tenant')->create([
                        'class_id' => $targetId,
                        'fee_type_id' => $line->fee_type_id,
                        'label' => $line->label,
                        'amount' => $line->amount,
                    ]);
                }
            }
        });

        return response()->json(['message' => "Barème appliqué à {$targets->count()} classe(s) du même niveau."]);
    }
}
