import axios from "axios"
import { config } from "@/config"

const API_URL = `${config.mpmBackendUrl}/api/orders/analytics`

export default {

  fetchAnalytics(filters = {}) {
    const token = localStorage.getItem("token")

    return axios.get(API_URL, {
      params: {
        from: filters.from,
        to: filters.to
      },
      headers: {
        Authorization: `Bearer ${token}`
      }
    })
  }

}
