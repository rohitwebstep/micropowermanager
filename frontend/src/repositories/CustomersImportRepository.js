import axios from "axios"
import { config } from "@/config"

export default {
  import(file, clusterId, miniGridId) {
  const formData = new FormData()
  formData.append("file", file)
  formData.append("cluster_id", clusterId)
  formData.append("mini_grid_id", miniGridId)

  const token = localStorage.getItem("token")

  return axios.post(
    config.mpmBackendUrl + "/api/people/import/csv",
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