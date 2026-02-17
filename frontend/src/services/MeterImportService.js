import { ErrorHandler } from "@/Helpers/ErrorHandler"
import MeterImportRepository from "@/repositories/MeterImportRepository"

export class MeterImportService {
  constructor() {
    this.repository = MeterImportRepository
  }

  async importMeters(file) {
    try {
      const { data, status, error } = await this.repository.import(file)

      if (status !== 200 && status !== 201) {
        return new ErrorHandler(error, "http", status)
      }

      return data
    } catch (e) {
      const errorMessage = e.response?.data?.message || "Meter import failed"
      return new ErrorHandler(errorMessage, "http")
    }
  }
}
