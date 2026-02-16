import axios from "axios"
import { config } from "@/config"

export default {
  create(payload) {
    const token = localStorage.getItem("token")

    return axios.post(
      `${config.mpmBackendUrl}/api/orders`,
      payload,
      {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      }
    )
  },
}
