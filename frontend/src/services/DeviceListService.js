// micropowermanager-main\frontend\src\services\DeviceListService.js
import DeviceListRepository from "@/repositories/DeviceListRepository"

export class DeviceListService {
  constructor() {
    this.repository = DeviceListRepository
  }

  async fetchDeviceList(page = 1, filters = {}) {
    try {
      const { data } = await this.repository.getList(page, filters)
      return data?.data ?? data  
    } catch (e) {
      console.log(e)
      return null
    }
  }

  async deleteDevice(id) {
    try {
      await this.repository.delete(id)
      return true  
    } catch (e) {
      console.log(e)
      return null
    }
  }
}