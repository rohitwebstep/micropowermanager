<?php
/*
    micropowermanager-main\backend\app\Services\BluettiDeviceService.php
*/
namespace App\Services;

use App\Models\Bluetti\BluettiDevice;
use App\Models\Bluetti\BluettiDeviceTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BluettiDeviceService
{
    private string $appKey;
    private string $appSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->appKey    = config('bluetti.app_key');
        $this->appSecret = config('bluetti.app_secret');
        $this->baseUrl   = config('bluetti.base_url');
    }

    // ─── BLUETTI API Helpers ──────────────────────────────────────────────────

    private function buildHeaders(): array
    {
        $nonceStr  = bin2hex(random_bytes(16));
        $timeStamp = (string) time();
        $signStr   = "appKey={$this->appKey}&appSecret={$this->appSecret}&nonceStr={$nonceStr}&timeStamp={$timeStamp}";
        $signature = strtoupper(hash('sha256', $signStr));

        return [
            'Content-Type'  => 'application/json',
            'x-app-key'     => $this->appKey,
            'appKey'        => $this->appKey,
            'appSecret'     => $this->appSecret,
            'ETag'          => $nonceStr,
            'Date'          => $timeStamp,
            'nonceStr'      => $nonceStr,
            'timeStamp'     => $timeStamp,
            'Authorization' => $signature,
        ];
    }

    private function requestCode(string $customerNo, string $sn, int $daysToActivate = 1, int $tokenType = 1): array
    {
        $payload = [
            'customerNo'     => $customerNo,
            'sn'             => $sn,
            'daysToActivate' => $daysToActivate,
            'tokenType'      => $tokenType,
        ];

        $headers = $this->buildHeaders();

        $response = Http::withHeaders($headers)
            ->post("{$this->baseUrl}/open/blugohire/api/code/requestCode", $payload);

        if (!$response->successful()) {
            throw new \Exception('BLUETTI requestCode API failed: ' . $response->body());
        }

        $json = $response->json();

        if (($json['msgCode'] ?? -1) !== 0) {
            throw new \Exception($json['message'] ?? 'BLUETTI API error');
        }

        // ✅ Pura response return karo, sirf 'data' nahi
        return $json;
    }

    private function queryCodeHistory(string $codeSerialNumber): array
    {
        $response = Http::withHeaders($this->buildHeaders())
            ->post("{$this->baseUrl}/open/blugohire/api/code/queryCodeHistory", [
                'codeSerialNumber' => $codeSerialNumber,
            ]);

        if (!$response->successful()) {
            throw new \Exception('BLUETTI queryCodeHistory API failed: ' . $response->body());
        }

        $json = $response->json();

        if (($json['code'] ?? -1) !== 0) {
            throw new \Exception('BLUETTI queryCodeHistory error: ' . ($json['error'] ?? 'Unknown'));
        }

        // ✅ Pura response return karo
        return $json;
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────

    public function getAll(int $limit, ?string $search = null): LengthAwarePaginator
    {
        $query = BluettiDevice::on('mysql')
            ->with('customer')
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('device_name',     'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('client',        'like', "%{$search}%")
                  ->orWhere('style',         'like', "%{$search}%");
            });
        }

        return $query->paginate($limit);
    }

    public function getById(int $id): BluettiDevice
    {
        return BluettiDevice::on('mysql')->findOrFail($id);
    }

    public function create(array $data): BluettiDevice
    {
        return BluettiDevice::on('mysql')->create($data);
    }

    public function update(BluettiDevice $device, array $data): BluettiDevice
    {
        $device->update($data);
        return $device->fresh();
    }

    public function delete(BluettiDevice $device): void
    {
        $device->delete();
    }

    public function assignCustomer(int $deviceId, int $customerId): BluettiDevice
    {
        $device = $this->getById($deviceId);
        $device->update(['customer_id' => $customerId]);
        return $device->fresh();
    }

    public function unassignCustomer(int $deviceId): BluettiDevice
    {
        $device = $this->getById($deviceId);
        $device->update(['customer_id' => null]);
        return $device->fresh();
    }

    public function getByCustomer(int $customerId): \Illuminate\Database\Eloquent\Collection
    {
        return BluettiDevice::on('mysql')
            ->with(['transactions' => function ($q) {
                $q->orderByDesc('year')->orderByDesc('month');
            }])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();
    }

    // ─── Monthly Transactions ─────────────────────────────────────────────────

    public function getTransactions(int $deviceId): \Illuminate\Database\Eloquent\Collection
    {
        return BluettiDeviceTransaction::on('mysql')
            ->where('device_id', $deviceId)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get();
    }

    public function upsertTransaction(
        int    $deviceId,
        string $transactionId,
        int    $month,
        int    $year
    ): BluettiDeviceTransaction {
        return BluettiDeviceTransaction::on('mysql')->updateOrCreate(
            [
                'device_id' => $deviceId,
                'month'     => $month,
                'year'      => $year,
            ],
            [
                'transaction_id' => $transactionId,
            ]
        );
    }

    // ─── Activate Transaction — BLUETTI API call + DB save ───────────────────

    public function activateTransaction(int $transactionId): BluettiDeviceTransaction
    {
        $txn    = BluettiDeviceTransaction::on('mysql')->findOrFail($transactionId);
        $device = BluettiDevice::on('mysql')->findOrFail($txn->device_id);

        if (!$device->customer_no) {
            throw new \Exception('Customer No not assigned to this device. Please assign Customer No first.');
        }

        $customerNo = config('bluetti.customer_no');

        // STEP 1: requestCode — pura response
        $requestCodeResponse = $this->requestCode(
            customerNo:     $customerNo,
            sn:             $device->serial_number,
            daysToActivate: $txn->days_to_activate ?? 1,
            tokenType:      $txn->token_type       ?? 1,
        );

        $codeData         = $requestCodeResponse['data'];
        $codeSerialNumber = $codeData['codeSerialNumber'];
        $token            = $codeData['token'];

        // STEP 2: queryCodeHistory — pura response
        $queryCodeHistoryResponse = $this->queryCodeHistory($codeSerialNumber);
        $historyData = $queryCodeHistoryResponse['data'];

        // STEP 3: DB update
        $txn->update([
            'code_serial_number'           => $codeSerialNumber,
            'token'                        => $token,
            'days_to_activate'             => $historyData['daysToActivate'] ?? $txn->days_to_activate,
            'token_type'                   => $historyData['tokenType']      ?? $txn->token_type,
            'is_active'                    => true,
            'request_code_response'        => $requestCodeResponse,
            'query_code_history_response'  => $queryCodeHistoryResponse,
        ]);

        return $txn->fresh();
    }

    public function deactivateTransaction(int $transactionId): BluettiDeviceTransaction
    {
        $txn    = BluettiDeviceTransaction::on('mysql')->findOrFail($transactionId);
        $device = BluettiDevice::on('mysql')->findOrFail($txn->device_id);

        if (!$device->customer_no) {
            throw new \Exception('Customer No not assigned. Cannot deactivate.');
        }

        $customerNo = config('bluetti.customer_no');
        
        // BLUETTI API call — tokenType=2 (Set the days), daysToActivate=0 (lock)
        $requestCodeResponse = $this->requestCode(
            customerNo:     $customerNo,
            sn:             $device->serial_number,
            daysToActivate: 0,   // 0 = device lock/deactivate
            tokenType:      2,   // "Set the days" mode
        );

        $codeData         = $requestCodeResponse['data'];
        $codeSerialNumber = $codeData['codeSerialNumber'];
        $token            = $codeData['token'];

        // queryCodeHistory bhi call karo (activate jaisa hi pattern)
        $queryCodeHistoryResponse = $this->queryCodeHistory($codeSerialNumber);

        $txn->update([
            'is_active'                   => false,
            'code_serial_number'          => $codeSerialNumber,
            'token'                       => $token,
            'request_code_response'       => $requestCodeResponse,
            'query_code_history_response' => $queryCodeHistoryResponse,
        ]);

        return $txn->fresh();
    }

    // ─── Legacy ───────────────────────────────────────────────────────────────

    public function assignTransaction(int $deviceId, string $transactionId): BluettiDevice
    {
        $device = $this->getById($deviceId);
        $device->update(['transaction_id' => $transactionId]);
        return $device->fresh();
    }

    public function assignCustomerNo(int $deviceId, string $customerNo): BluettiDevice
    {
        $device = $this->getById($deviceId);
        $device->update(['customer_no' => $customerNo]);
        return $device->fresh();
    }
}