import { ErrorHandler } from "@/Helpers/ErrorHandler"
import AddOrderRepository from "@/repositories/AddOrderRepository"

export class AddOrderService {

  async createOrder(payload) {
    try {
      const response = await AddOrderRepository.create(payload)

      const { status, data } = response

      if (status !== 200 && status !== 201) {
        return new ErrorHandler("Failed to create order", "http", status)
      }

      // 🔥 RETURN ACTUAL ORDER DATA
      return data

    } catch (e) {
      return new ErrorHandler(
        e.response?.data?.message || "Something went wrong",
        "http"
      )
    }
  }

}
