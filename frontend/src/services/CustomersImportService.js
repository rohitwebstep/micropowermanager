import { ErrorHandler } from "@/Helpers/ErrorHandler"
import CustomersImportRepository from "@/repositories/CustomersImportRepository"

export class CustomersImportService {
  constructor() {
    this.repository = CustomersImportRepository
  }

  async importCustomers(file) {
    console.log("SERVICE HIT")

    try {
      const response = await this.repository.import(file)

      console.log("API RESPONSE", response)

      if (response.status !== 200 && response.status !== 201) {
        throw new ErrorHandler("Import failed", "http", response.status)
      }

      return response.data
    } catch (e) {
      console.log("SERVICE ERROR", e)
      const errorMessage = e.response?.data?.message || "Customer import failed"
      throw new ErrorHandler(errorMessage, "http")
    }
  }
}
