<?php

namespace Modules\Payroll\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\Payroll\Entities\PayrollPeriod;
use Modules\Payroll\Entities\SocialDeclaration;
use Modules\Payroll\Services\SocialDeclarationService;

/**
 * Epic 4: Déclarations Sociales
 * Handles Stories 14-17
 */
class SocialDeclarationController extends Controller
{
    public function __construct(
        private SocialDeclarationService $declarationService
    ) {}

    /**
     * List declarations
     */
    public function index(Request $request): JsonResponse
    {
        $query = SocialDeclaration::on('tenant')->with('payrollPeriod');

        if ($type = $request->input('type')) {
            $query->byType($type);
        }

        if ($year = $request->input('year')) {
            $query->forYear($year);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        return response()->json($query->orderBy('declaration_date', 'desc')->get());
    }

    /**
     * Show declaration
     */
    public function show(int $id): JsonResponse
    {
        $declaration = SocialDeclaration::on('tenant')
            ->with('payrollPeriod')
            ->findOrFail($id);

        return response()->json(['data' => $declaration]);
    }

    /**
     * Story 14: Déclaration des cotisations sociales (agrégées par code de cotisation configuré)
     */
    public function generateSocialContributions(int $periodId): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($periodId);

        $declaration = $this->declarationService->generateSocialContributionsDeclaration($period);

        return response()->json([
            'message' => 'Déclaration des cotisations sociales générée avec succès',
            'data' => $declaration,
        ], 201);
    }

    /**
     * Story 15: Déclaration de l'impôt sur salaires retenu à la source
     */
    public function generateIncomeTax(int $periodId): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($periodId);

        $declaration = $this->declarationService->generateIncomeTaxDeclaration($period);

        return response()->json([
            'message' => "Déclaration de l'impôt sur salaires générée avec succès",
            'data' => $declaration,
        ], 201);
    }

    /**
     * Déclaration des charges patronales additionnelles (agrégées par code configuré)
     */
    public function generateEmployerCharges(int $periodId): JsonResponse
    {
        $period = PayrollPeriod::on('tenant')->findOrFail($periodId);

        $declaration = $this->declarationService->generateEmployerChargesDeclaration($period);

        return response()->json([
            'message' => 'Déclaration des charges patronales générée avec succès',
            'data' => $declaration,
        ], 201);
    }

    /**
     * Story 17: Generate annual tax summary
     */
    public function generateAnnualSummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $declaration = $this->declarationService->generateAnnualTaxSummary($request->year);

        return response()->json([
            'message' => 'Récapitulatif annuel généré avec succès',
            'data' => $declaration,
        ], 201);
    }

    /**
     * Validate declaration
     */
    public function validateDeclaration(Request $request, int $id): JsonResponse
    {
        $declaration = SocialDeclaration::on('tenant')->findOrFail($id);

        $declaration = $this->declarationService->validateDeclaration(
            $declaration,
            auth('tenant')->id(),
            $request->input('notes')
        );

        return response()->json([
            'message' => 'Déclaration validée avec succès',
            'data' => $declaration,
        ]);
    }

    /**
     * Submit declaration
     */
    public function submit(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'response' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $declaration = SocialDeclaration::on('tenant')->findOrFail($id);

        $declaration = $this->declarationService->submitDeclaration(
            $declaration,
            $request->reference,
            $request->response
        );

        return response()->json([
            'message' => 'Déclaration soumise avec succès',
            'data' => $declaration,
        ]);
    }

    /**
     * Record payment
     */
    public function recordPayment(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:bank_transfer,check,online,other',
            'reference' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $declaration = SocialDeclaration::on('tenant')->findOrFail($id);

        $declaration = $this->declarationService->recordPayment(
            $declaration,
            $request->amount,
            $request->method,
            $request->reference
        );

        return response()->json([
            'message' => 'Paiement enregistré avec succès',
            'data' => $declaration,
        ]);
    }
}
