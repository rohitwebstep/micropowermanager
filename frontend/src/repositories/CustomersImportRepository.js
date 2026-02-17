import axios from "axios"
import { config } from "@/config"

export default {
  import(file) {
    console.log("REPOSITORY HIT")

    const formData = new FormData()
    formData.append("file", file)
    formData.append("cluster_id", 19)
    formData.append("mini_grid_id", 1)

    const token = localStorage.getItem("token")

    return axios.post(
      config.mpmBackendUrl + "/api/people/import/csv", // 👈 FINAL API
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
