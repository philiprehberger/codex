<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\Scopes\RedactedScope;
use App\Services\AssetSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves redacted/private project assets via HMAC-signed URLs.
 *
 * The 14-day 410-Gone window for visibility flips applies to the OLD
 * public `/storage/projects/<slug>/*` URL pattern, NOT this signed-URL
 * route — Phase 5 wires that as a separate handler so cached public
 * URLs get the proper cache-evict signal. This route's job is narrow:
 * a valid signature → file or 404; a bad signature → 403.
 *
 * Behaviour matrix:
 *  - missing/invalid sig or expired exp → 403
 *  - asset not found OR file not on disk → 404
 *  - asset's project visibility=public → 302 to public /storage URL
 *  - asset's project visibility=redacted/private → stream the file with
 *    Cache-Control: private, max-age=3600
 */
class SignedAssetController extends Controller
{
    public function __invoke(Request $request, AssetSigner $signer, string $ulid): Response
    {
        $sig = (string) $request->query('sig', '');
        $exp = (int) $request->query('exp', 0);

        if ($sig === '' || $exp <= 0 || ! $signer->verify($ulid, $exp, $sig)) {
            abort(403, 'Invalid or expired signature.');
        }

        $asset = ProjectAsset::find($ulid);
        if (! $asset) {
            abort(404);
        }

        $project = Project::withoutGlobalScope(RedactedScope::class)
            ->find($asset->project_id);
        if (! $project) {
            abort(404);
        }

        if ($project->visibility === 'public') {
            return redirect()->away(
                rtrim((string) config('app.url'), '/').'/storage/'.$asset->path,
                302,
            );
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($asset->path)) {
            abort(404);
        }

        return new StreamedResponse(
            fn () => fpassthru(fopen($disk->path($asset->path), 'rb')),
            200,
            [
                'Content-Type' => $this->mimeFor($asset->path),
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'avif' => 'image/avif',
            default => 'application/octet-stream',
        };
    }
}
