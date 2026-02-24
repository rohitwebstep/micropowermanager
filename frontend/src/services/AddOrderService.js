import AddOrderRepository from "@/repositories/AddOrderRepository"

export class AddOrderService {

  async createOrder(payload) {
    try {
      const response = await AddOrderRepository.create(payload)
      return response.data

    } catch (e) {

      // 🔥 RETURN FULL VALIDATION RESPONSE
      if (e.response && e.response.status === 422) {
        return e.response.data
      }

      // 🔥 RETURN NORMAL ERROR RESPONSE
      if (e.response && e.response.data) {
        return e.response.data
      }

      // 🔥 NETWORK / UNKNOWN
      return {
        message: "Server error"
      }
    }
  }
}