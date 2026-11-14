<?php

namespace Modules\Settings\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Modules\Settings\Http\Requests\UpdateSchoolProfileRequest;
use Modules\Settings\Http\Requests\UploadSchoolImageRequest;
use Modules\Settings\Services\SchoolProfileService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Profil de l'établissement : lecture pour tout utilisateur authentifié du tenant,
 * écriture réservée à l'Administrator (cf. Routes/admin.php).
 */
class SchoolProfileController extends Controller
{
    public function __construct(private SchoolProfileService $schoolProfile) {}

    /**
     * GET /admin/settings/school-profile
     */
    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->schoolProfile->profile()]);
    }

    /**
     * PUT /admin/settings/school-profile
     */
    public function update(UpdateSchoolProfileRequest $request): JsonResponse
    {
        return response()->json([
            'message' => "Profil de l'établissement enregistré.",
            'data' => $this->schoolProfile->update($request->validated()),
        ]);
    }

    /**
     * GET /admin/settings/school-profile/{image} — logo ou cachet (PNG).
     */
    public function image(string $image): Response
    {
        $path = $this->schoolProfile->imagePath($image);

        if ($path === null) {
            abort(404, 'Aucune image enregistrée.');
        }

        return Storage::disk('local')->response($path, $image.'.png', [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * POST /admin/settings/school-profile/{image} (multipart « file »).
     */
    public function uploadImage(UploadSchoolImageRequest $request, string $image): JsonResponse
    {
        $this->schoolProfile->storeImage($image, $request->file('file'));

        return response()->json([
            'message' => $image === 'logo' ? 'Logo enregistré.' : 'Cachet / signature enregistré.',
            'data' => $this->schoolProfile->profile(),
        ]);
    }

    /**
     * DELETE /admin/settings/school-profile/{image}
     */
    public function deleteImage(string $image): JsonResponse
    {
        $this->schoolProfile->deleteImage($image);

        return response()->json([
            'message' => $image === 'logo' ? 'Logo supprimé.' : 'Cachet / signature supprimé.',
            'data' => $this->schoolProfile->profile(),
        ]);
    }
}
