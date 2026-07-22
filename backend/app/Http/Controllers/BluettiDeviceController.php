<?php
/*
    micropowermanager-main\backend\app\Http\Controllers\BluettiDeviceController.php
*/
namespace App\Http\Controllers;

use App\Http\Requests\BluettiDeviceCreateRequest;
use App\Http\Requests\BluettiDeviceUpdateRequest;
use App\Http\Resources\ApiResource;
use App\Services\BluettiDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BluettiDeviceController extends Controller
{
    public function __construct(
        private BluettiDeviceService $bluettiDeviceService,
    ) {}

    public function index(Request $request): ApiResource
    {
        $limit  = $request->input('limit', config('settings.paginate', 15));
        $search = $request->input('search');

        return ApiResource::make(
            $this->bluettiDeviceService->getAll($limit, $search)
        );
    }

    public function show(int $id): ApiResource
    {
        return ApiResource::make(
            $this->bluettiDeviceService->getById($id)
        );
    }

    public function store(BluettiDeviceCreateRequest $request): JsonResponse
    {
        $device = $this->bluettiDeviceService->create($request->validated());
        return response()->json(['data' => $device], 201);
    }

    public function update(BluettiDeviceUpdateRequest $request, int $id): ApiResource
    {
        $device  = $this->bluettiDeviceService->getById($id);
        $updated = $this->bluettiDeviceService->update($device, $request->validated());
        return ApiResource::make($updated);
    }

    public function destroy(int $id): JsonResponse
    {
        $device = $this->bluettiDeviceService->getById($id);
        $this->bluettiDeviceService->delete($device);
        return response()->json(null, 204);
    }

    public function assignCustomer(Request $request, int $id): ApiResource|JsonResponse
    {
        $request->validate([
            'customer_id' => ['required', 'integer'],
            'emi_months'  => ['required', 'integer', 'in:12,18'],
        ]);

        try {
            $device = $this->bluettiDeviceService->assignCustomer(
                $id,
                $request->integer('customer_id'),
                $request->integer('emi_months'),
            );
            return ApiResource::make($device);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function unassignCustomer(int $id): ApiResource
    {
        $device = $this->bluettiDeviceService->unassignCustomer($id);
        return ApiResource::make($device);
    }

    public function devicesByCustomer(int $customerId): JsonResponse
    {
        $devices = $this->bluettiDeviceService->getByCustomer($customerId);
        return response()->json(['data' => $devices]);
    }

    // ─── Monthly Transactions ─────────────────────────────────────────────────

    /**
     * GET /bluetti-devices/{id}/transactions
     * Device ki saari monthly transactions
     */
    public function listTransactions(int $id): JsonResponse
    {
        $transactions = $this->bluettiDeviceService->getTransactions($id);
        return response()->json(['data' => $transactions]);
    }

    /**
     * POST /bluetti-devices/{id}/transactions
     * Transaction save ya update (same month+year pe overwrite)
     */
    public function upsertTransaction(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
            'month'          => ['required', 'integer', 'min:1', 'max:12'],
            'year'           => ['required', 'integer', 'min:2020', 'max:2100'],
        ]);

        $txn = $this->bluettiDeviceService->upsertTransaction(
            $id,
            $request->input('transaction_id'),
            $request->integer('month'),
            $request->integer('year'),
        );

        return response()->json(['data' => $txn], 201);
    }

    // ─── Legacy single-field endpoints (backward compat) ─────────────────────

    public function assignTransaction(Request $request, int $id): ApiResource
    {
        $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
        ]);
        $device = $this->bluettiDeviceService->assignTransaction(
            $id,
            $request->input('transaction_id')
        );
        return ApiResource::make($device);
    }

    public function assignCustomerNo(Request $request, int $id): ApiResource
    {
        $request->validate([
            'customer_no' => ['required', 'string', 'max:255'],
        ]);
        $device = $this->bluettiDeviceService->assignCustomerNo(
            $id,
            $request->input('customer_no')
        );
        return ApiResource::make($device);
    }

    /**
     * POST /bluetti-devices/{id}/transactions/{txnId}/activate
     */
    public function activateTransaction(Request $request, int $id, int $txnId): JsonResponse
    {
        try {
            $txn = $this->bluettiDeviceService->activateTransaction($txnId);
            return response()->json(['data' => $txn]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * POST /bluetti-devices/{id}/transactions/{txnId}/deactivate
     */
    public function deactivateTransaction(int $id, int $txnId): JsonResponse
    {
        try {
            $txn = $this->bluettiDeviceService->deactivateTransaction($txnId);
            return response()->json(['data' => $txn]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
