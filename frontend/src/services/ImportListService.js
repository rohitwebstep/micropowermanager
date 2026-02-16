import { ErrorHandler } from "@/helpers/ErrorHandler"
import ImportListRepository from "@/repositories/ImportListRepository"

export class ImportListService {
  constructor() {
    this.repository = ImportListRepository
  }

  async getImportList(params = {}) {
    try {
      const { data, status, error } = await this.repository.list(params)

      if (status !== 200) {
        return new ErrorHandler(error, "http", status)
      }

      return data
    } catch (e) {
      const errorMessage =
        e.response?.data?.message || "Failed to fetch import list"

      return new ErrorHandler(errorMessage, "http")
    }
  }

  async deleteImport(id) {
    try {
      const { data, status, error } = await this.repository.delete(id)

      if (status !== 200 && status !== 204) {
        return new ErrorHandler(error, "http", status)
      }

      return data
    } catch (e) {
      const errorMessage =
        e.response?.data?.message || "Failed to delete import"

      return new ErrorHandler(errorMessage, "http")
    }
  }
}
