import { ErrorHandler } from "@/Helpers/ErrorHandler"
import MiniGridImportListRepository from "@/repositories/MiniGridImportListRepository"

export class MiniGridImportListService {
  constructor() {
    this.repository = MiniGridImportListRepository
  }

  async fetchImportList(page = 1) {
    try {
      const { data, status } = await this.repository.getList(page)

      if (status !== 200) {
        return new ErrorHandler("Failed to load list", "http", status)
      }

      // return FULL response
      return data
    } catch (e) {
      const message = e.response?.data?.message || "Something went wrong"
      return new ErrorHandler(message, "http")
    }
  }
}
