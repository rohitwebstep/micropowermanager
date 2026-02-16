import OrderListRepository from "@/repositories/OrderListRepository"

export class OrderListService {
  constructor() {
    this.repository = OrderListRepository
  }

  async fetchOrderList(page = 1, filters = {}) {
    try {
      const { data } = await this.repository.getList(page, filters)
      return data
    } catch (e) {
      console.log(e)
      return null
    }
  }
}
