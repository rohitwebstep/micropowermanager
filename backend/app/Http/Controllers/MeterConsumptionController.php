<?php

namespace App\Http\Controllers;

use App\Http\Resources\ApiResource;
use App\Services\MeterConsumptionService;
use App\Services\MeterService;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class MeterConsumptionController extends Controller
{
    public function __construct(
        private MeterService $meterService,
        private MeterConsumptionService $meterConsumptionService,
    ) {}

    /**
     * Consumption List
     * If the meter has the ability to send data to your server. That is the endpoint where you get the
     * meter readings ( used energy, credit on meter etc.).
     *
     * @urlParam     serialNumber
     * @urlParam     start YYYY-mm-dd format
     * @urlParam     end YYYY-mm-dd format
     *
     * @responseFile responses/meters/meter.consumption.list.json
     */
    /*
    public function show(string $serialNumber, string $start, string $end): ApiResource {
        $meter = $this->meterService->getBySerialNumber($serialNumber);

        return ApiResource::make($this->meterConsumptionService->getByMeter($meter, $start, $end));
    }
    */

    public function show(string $serialNumber, string $start, string $end): ApiResource
    {
        // Get meter
        $meter = $this->meterService->getBySerialNumber($serialNumber);

        if (!$meter) {
            return ApiResource::make([
                'success' => false,
                'message' => 'Meter not found.'
            ])->additional(['status' => 404]);
        }

        // Parse dates safely
        try {
            $startDate = Carbon::parse($start);
            $endDate   = Carbon::parse($end);
        } catch (\Exception $e) {
            return ApiResource::make([
                'success' => false,
                'message' => 'Invalid date format.'
            ])->additional(['status' => 400]);
        }

        if ($startDate->gt($endDate)) {
            return ApiResource::make([
                'success' => false,
                'message' => 'Start date must be before end date.'
            ])->additional(['status' => 400]);
        }

        // Determine type
        $endpoint = '/COMM_MonthlyData';
        $period = CarbonPeriod::create(
            $startDate->copy()->startOfMonth(),
            '1 month',
            $endDate->copy()->startOfMonth()
        );

        $queryList = collect($period)->map(
            fn(Carbon $date) => [
                "MeterNo" => $serialNumber,
                "Year"    => $date->year,
                "Month"   => $date->month,
            ]
        )->values()->toArray();

        $baseUrl = config('services.meter.base_url', 'http://47.90.189.157:6001/api');

        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($baseUrl . $endpoint, [
                    "CompanyName" => config('services.meter.company', 'Sandstream'),
                    "UserName"    => config('services.meter.username', 'pos1'),
                    "Password"    => config('services.meter.password', 'Sandstream@@25'),
                    "QueryList"   => $queryList
                ]);

            if (!$response->successful()) {
                return ApiResource::make([
                    'data' => []
                ])->additional(['status' => $response->status()]);
            }

            $result = $response->json('Result') ?? [];

            $previousUnits = null;

            $data = collect($result)->map(function ($item, $index) use ($meter, &$previousUnits) {

                $totalUnits = (float) ($item['TotalUnitsCounter'] ?? 0);

                $consumption = $previousUnits !== null
                    ? $totalUnits - $previousUnits
                    : $totalUnits;

                $previousUnits = $totalUnits;

                return [
                    'id'                => $index + 1,
                    'meter_id'          => $meter->id,
                    'total_consumption' => round($totalUnits, 2),
                    'consumption'       => round($consumption, 2),
                    'credit_on_meter'   => round((float) ($item['CurrentCreditRegister'] ?? 0), 2),
                    'reading_date'      => Carbon::create(
                        $item['Year'],
                        $item['Month'],
                        1
                    )->endOfMonth()->toDateTimeString(),
                    'created_at'        => null,
                    'updated_at'        => null,
                ];
            })->values()->toArray();

            return ApiResource::make([
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return ApiResource::make([
                'data' => []
            ])->additional(['status' => 500]);
        }
    }
}
