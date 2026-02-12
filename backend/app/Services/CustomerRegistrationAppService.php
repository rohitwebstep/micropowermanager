<?php

namespace App\Services;

use App\Events\AccessRatePaymentInitialize;
use App\Http\Requests\AndroidAppRequest;
use App\Models\Meter\Meter;
use App\Models\Order\Order;
use App\Models\Person\Person;

class CustomerRegistrationAppService
{
    public function __construct(
        private PersonService $personService,
        private MeterService $meterService,
        private DeviceService $deviceService,
        private MeterDeviceService $meterDeviceService,
        private AddressesService $addressService,
        private DeviceAddressService $deviceAddressService,
        private GeographicalInformationService $geographicalInformationService,
        private AddressGeographicalInformationService $addressGeographicalInformationService,
    ) {}

    public function createCustomer(AndroidAppRequest $request): Person
    {
        $serialNumber = $request->input('serial_number');
        $phone = $request->input('phone');

        $person = $this->personService->getByPhoneNumber($phone);
        if (!$person instanceof Person) {
            $request->attributes->add(['is_customer' => 1]);
            $person = $this->personService->createFromRequest($request);
        }

        if (!empty($serialNumber)) {
            $meter = $this->meterService->getBySerialNumber($serialNumber);
            if ($meter instanceof Meter) {
                throw new \Exception('Meter already exists');
            }

            $manufacturerId = $request->input('manufacturer');
            $meterTypeId = $request->input('meter_type');
            $connectionTypeId = $request->input('connection_type_id');
            $connectionGroupId = $request->input('connection_group_id');
            $tariffId = $request->input('tariff_id');

            $meterData = [
                'serial_number' => $serialNumber,
                'connection_group_id' => $connectionGroupId,
                'manufacturer_id' => $manufacturerId,
                'meter_type_id' => $meterTypeId,
                'connection_type_id' => $connectionTypeId,
                'tariff_id' => $tariffId,
                'in_use' => 1,
            ];

            $meter = $this->meterService->create($meterData);
            $device = $this->deviceService->make([
                'person_id' => $person->id,
                'device_serial' => $meter->serial_number,
            ]);

            $this->meterDeviceService->setAssigned($device);
            $this->meterDeviceService->setAssignee($meter);
            $this->meterDeviceService->assign();
            $this->deviceService->save($device);

            $customerPendingFirstOrder = Order::where('customer_id', $person->id)
                ->where('type', 'meter_order')
                ->whereNull('meter_id')
                ->orderBy('created_at')
                ->first();

            if ($customerPendingFirstOrder) {
                $customerPendingFirstOrder->meter_id = $meter->id;
                $customerPendingFirstOrder->save();
            }

            // initializes a new Access Rate Payment for the next Period
            event(new AccessRatePaymentInitialize($meter));
        }


        $cityId = $request->input('city_id');
        $geoPoints = $request->input('geo_points');

        $addressData = [
            'city_id' => $cityId ?? 1,
        ];
        $address = $this->addressService->make($addressData);

        if (!empty($device ?? null)) {
            $this->deviceAddressService->setAssigned($address);
            $this->deviceAddressService->setAssignee($device);
            $this->deviceAddressService->assign();
            $this->addressService->save($address);
            $geographicalInformation = $this->geographicalInformationService->make([
                'points' => $geoPoints,
            ]);
            $this->addressGeographicalInformationService->setAssigned($geographicalInformation);
            $this->addressGeographicalInformationService->setAssignee($address);
            $this->addressGeographicalInformationService->assign();
            $this->geographicalInformationService->save($geographicalInformation);
        }

        return $person;
    }
}
