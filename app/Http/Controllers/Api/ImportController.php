<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Jobs\ProcessImportJob;
use App\Models\Import;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ImportController extends Controller
{
    public function store(StoreImportRequest $request): JsonResponse
    {
        $supplier = Supplier::where('name', $request->validated('supplier'))->firstOrFail();

        $import = Import::firstOrCreate(
            [
                'supplier_id' => $supplier->id,
                'external_import_id' => $request->validated('external_import_id'),
            ],
            [
                'sent_at' => $request->validated('sent_at'),
                'status' => Import::STATUS_PENDING,
                'total_offers' => count($request->validated('offers')),
            ],
        );

        // Idempotency: a repeated import must not create duplicates
        // nor trigger processing again.
        if ($import->wasRecentlyCreated) {
            ProcessImportJob::dispatch($import, $request->validated('offers'));
        }

        return ImportResource::make($import)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
