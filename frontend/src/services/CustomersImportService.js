import { ErrorHandler } from "@/Helpers/ErrorHandler"
import CustomersImportRepository from "@/repositories/CustomersImportRepository"

export class CustomersImportService {
  constructor() {
    this.repository = CustomersImportRepository
  }

  async importCustomers(file, clusterId, miniGridId) {
    try {
      const response = await this.repository.import(
        file,
        clusterId,
        miniGridId
      )

      if (response.status !== 200 && response.status !== 201) {
        throw new ErrorHandler("Import failed", "http", response.status)
      }

      return response.data
    } catch (e) {
      const errorMessage =
        e.response?.data?.message || "Customer import failed"
      throw new ErrorHandler(errorMessage, "http")
    }
  }
}