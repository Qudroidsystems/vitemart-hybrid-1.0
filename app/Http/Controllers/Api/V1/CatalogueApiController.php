<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Catalogue\CatalogueRequests;
use Illuminate\Http\Request;

/** Head office side of the branch catalogue requests (signed branch-server calls). */
class CatalogueApiController extends Controller
{
    public function __construct(protected CatalogueRequests $service)
    {
    }

    public function priceRequests(Request $request)
    {
        $data = $request->validate([
            'requests' => 'required|array|max:500',
            'requests.*.uid' => 'required|string|size:26',
            'requests.*.product_uid' => 'required|string|size:26',
            'requests.*.variation_uid' => 'nullable|string|size:26',
            'requests.*.pack_uid' => 'nullable|string|size:26',
            'requests.*.field' => 'required|in:price,wholesale_price,promo_price',
            'requests.*.requested_value' => 'required|numeric|min:0',
            'requests.*.current_value' => 'nullable|numeric',
            'requests.*.item_label' => 'nullable|string|max:255',
            'requests.*.reason' => 'nullable|string|max:255',
            'requests.*.requested_by_name' => 'nullable|string|max:120',
            'requests.*.promo_starts_at' => 'nullable|date',
            'requests.*.promo_ends_at' => 'nullable|date',
        ]);
        $n = $this->service->receivePriceRequests($request->attributes->get('branch'), $data['requests']);
        return response()->json(['received' => $n]);
    }

    public function submissions(Request $request)
    {
        $data = $request->validate([
            'submissions' => 'required|array|max:100',
            'submissions.*.uid' => 'required|string|size:26',
            'submissions.*.product_uid' => 'required|string|size:26',
            'submissions.*.title' => 'required|string|max:255',
            'submissions.*.barcode' => 'nullable|string|max:64',
            'submissions.*.payload' => 'required|array',
            'submissions.*.payload.uid' => 'required|string|size:26',
            'submissions.*.payload.title' => 'required|string|max:255',
            'submissions.*.payload.price' => 'required|numeric|min:0',
            'submissions.*.submitted_by_name' => 'nullable|string|max:120',
            'submissions.*.note' => 'nullable|string|max:255',
        ]);
        $n = $this->service->receiveSubmissions($request->attributes->get('branch'), $data['submissions']);
        return response()->json(['received' => $n]);
    }

    public function decisions(Request $request)
    {
        $since = $request->query('since');
        return response()->json($this->service->decisionsFor($request->attributes->get('branch'), is_string($since) ? $since : null));
    }
}
