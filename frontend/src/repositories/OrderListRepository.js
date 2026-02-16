import axios from "axios"
import { config } from "@/config"

export default {
  getList(page=1, filters={}){
    const token = localStorage.getItem("token")
    return axios.get(`${config.mpmBackendUrl}/api/orders`,{
      params:{
        page:page,
        search: filters.search,
        // order_id: filters.order_id,
        // first_name: filters.first_name,
        // last_name: filters.last_name,
        // email: filters.email,
        // phone_number: filters.phone_number,
        // status: filters.status,
        // notes: filters.notes,
        // type: filters.type,
        // amount: filters.amount,
        // power_code: filters.power_code,
        // token: filters.token
      },
      headers:{
        Authorization:`Bearer ${token}`
      }
    })
  }

}
