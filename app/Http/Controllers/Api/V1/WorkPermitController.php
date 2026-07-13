<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Maintenance\Services\WorkPermitService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WorkPermitResource;
use App\Models\WorkOrder;
use App\Models\WorkPermit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Los permisos de alto riesgo, para el técnico que está frente a la máquina.
 *
 * La firma del ejecutante ocurre en el sitio, con guantes y bajo el sol: por eso
 * vive en la PWA. Lo que **no** hace es encolarse sin conexión — a diferencia de
 * un comentario o una lectura de horómetro, aceptar un permiso es la autorización
 * para entrar a un espacio confinado. Una firma guardada en el teléfono no protege
 * a nadie: el servidor tiene que saber que ese hombre entró, y tiene que saberlo
 * antes de que entre.
 */
class WorkPermitController extends Controller
{
    public function __construct(private readonly WorkPermitService $service) {}

    public function index(Request $request, string $workOrder): AnonymousResourceCollection
    {
        abort_if(! $request->user()->tokenCan('permits.read') && ! $request->user()->tokenCan('*'), 403);

        $workOrder = WorkOrder::findOrFail($workOrder);

        return WorkPermitResource::collection(
            $workOrder->permits()->with(['issuedBy', 'acceptedBy'])->latest('issued_at')->get()
        );
    }

    /** La firma del ejecutante: le explicaron los riesgos y los acepta. */
    public function accept(Request $request, string $permit): WorkPermitResource
    {
        abort_if(! $request->user()->tokenCan('permits.write') && ! $request->user()->tokenCan('*'), 403);

        $permit = WorkPermit::findOrFail($permit);

        $permit = $this->service->accept($permit, $request->user());

        return new WorkPermitResource($permit->load(['issuedBy', 'acceptedBy']));
    }
}
