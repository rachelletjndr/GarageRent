<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\GarageFactoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Controller providing a read-only catalog of garages and official tariffs for Tenants (BR-03).
 */
class GarageCatalogController extends Controller
{
    public function __construct(private GarageFactoryService $garageService) {}

    /**
     * Display the read-only catalog of garage units and tariffs.
     */
    public function index(Request $request): View
    {
        $tipeFilter = $request->query('tipe');
        $statusFilter = $request->query('status');
        $search = $request->query('search');

        $garages = $this->garageService->getGarages(
            is_string($tipeFilter) ? $tipeFilter : null,
            is_string($statusFilter) ? $statusFilter : null,
            is_string($search) ? $search : null,
        );

        $statistics = $this->garageService->getGarageStatistics();

        return view('tenant.garages.index', [
            'garages' => $garages,
            'statistics' => $statistics,
            'tipeFilter' => is_string($tipeFilter) ? $tipeFilter : 'all',
            'statusFilter' => is_string($statusFilter) ? $statusFilter : 'all',
            'search' => is_string($search) ? $search : '',
        ]);
    }
}
