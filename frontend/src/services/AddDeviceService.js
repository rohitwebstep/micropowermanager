// micropowermanager-main\frontend\src\services\AddDeviceService.js
import AddDeviceRepository from "@/repositories/AddDeviceRepository"

export class AddDeviceService {
  async createDevice(payload) {
    try {
      const response = await AddDeviceRepository.create(payload)
      // response.data = { data: { id: 1, device_name: ... } }
      return response.data?.data ?? response.data  // ✅ inner data nikalo
    } catch (e) {
      if (e.response && e.response.status === 422) {
        return e.response.data
      }
      if (e.response && e.response.data) {
        return e.response.data
      }
      return { message: "Server error" }
    }
  }
}