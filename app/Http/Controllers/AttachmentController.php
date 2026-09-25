<?php

namespace App\Http\Controllers;

use App\Domain\Documents\Models\Attachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to fetch a stored file. Route model binding goes through the
 * entity scope (another entity's attachment is a 404) and the policy checks
 * the parent record. Every download is audited.
 */
class AttachmentController extends Controller
{
    public function download(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('download', $attachment);

        activity('documents')
            ->performedOn($attachment)
            ->causedBy(auth()->user())
            ->event('attachment_downloaded')
            ->withProperties(['name' => $attachment->original_name, 'sha256' => $attachment->sha256])
            ->log('File downloaded');

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
