<?php

namespace App\Http\Controllers;

use App\Domain\MasterData\Import\ImportRun;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportRunController extends Controller
{
    public function errors(ImportRun $importRun): StreamedResponse
    {
        Gate::authorize('view', $importRun);
        abort_if($importRun->error_report_path === null, 404);

        return Storage::disk($importRun->disk)->download(
            $importRun->error_report_path,
            pathinfo($importRun->original_name, PATHINFO_FILENAME).'-errors.xlsx',
            ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
        );
    }
}
