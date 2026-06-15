import axios from "axios"
import { config } from "@/config"

export default {
  getList(page = 1, filters = {}) {
    const token = localStorage.getItem("token")
    return axios.get(`${config.mpmBackendUrl}/api/bluetti-devices`, {
      params: {
        page: page,
        search: filters.search,
      },
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  },

  delete(id) {
    const token = localStorage.getItem("token")
    return axios.delete(`${config.mpmBackendUrl}/api/bluetti-devices/${id}`, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })
  },
}
