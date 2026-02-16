import axios from "axios"
import { config } from "@/config"

export default {
  getList(page = 1) {
    const token = localStorage.getItem("token")

    return axios.get(
      `${config.mpmBackendUrl}/api/people`,
      {
        params: {
          per_page: 15,
          page,
        },
        headers: {
          Authorization: `Bearer ${token}`,
        },
      }
    )
  },
}
