import { ErrorHandler } from "@/Helpers/ErrorHandler"

import MiniGridImportRepository from "@/repositories/MiniGridImportRepository"

export class MiniGridImportService {
  constructor() {
    this.repository = MiniGridImportRepository
  }

  async importMiniGrid(file) {
    try {
      const { data, status, error } = await this.repository.import(file)

      if (status !== 200 && status !== 201) {
        return new ErrorHandler(error, "http", status)
      }

      return data
    } catch (e) {
      const errorMessage = e.response?.data?.message || "Import failed"
      return new ErrorHandler(errorMessage, "http")
    }
  }
}
