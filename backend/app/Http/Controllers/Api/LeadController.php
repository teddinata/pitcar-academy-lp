<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Resources\LeadResource;
use App\Services\LeadIntake;
use App\Services\WhatsAppLinkBuilder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class LeadController extends Controller
{
    public function store(
        StoreLeadRequest $request,
        LeadIntake $intake,
        WhatsAppLinkBuilder $links,
    ): JsonResponse {
        ['lead' => $lead, 'created' => $created] = $intake->handle($request->validated());

        $resource = new LeadResource(
            $lead,
            $links->build($lead),
            $created ? 'Lead berhasil dibuat' : 'Lead sudah tersimpan sebelumnya',
        );

        // 201 for a new lead, 200 for an idempotent replay of the same
        // submission_id. Both carry the same lead code.
        return $resource
            ->response($request)
            ->setStatusCode($created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
