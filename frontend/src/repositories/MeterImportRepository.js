import axios from "axios"
import { config } from "@/config"

export default {
  import(file) {
    const formData = new FormData()
    formData.append("file", file)

    const token = localStorage.getItem("token")

    return axios.post(
      config.mpmBackendUrl + "/api/meters/import", // confirm endpoint
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
