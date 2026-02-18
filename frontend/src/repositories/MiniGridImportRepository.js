import axios from "axios"
import { config } from "@/config"

export default {
  import(file) {
    const formData = new FormData()
    formData.append("file", file)

    const token = localStorage.getItem("token") // or wherever you store it

    return axios.post(
      config.mpmBackendUrl + "/api/orders/import/csv",
      formData,
      {
        headers: {
          "Content-Type": "multipart/form-data",
          Authorization: `Bearer ${token}`,
        },
      }
    )
  },
}
