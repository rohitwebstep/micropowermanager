<template>
  <div class="order-page">

    <!-- HEADER -->
    <div class="top-bar">
      <h2>Orders Management</h2>
      <div class="buttonsec">
      <button class="export-btn" @click="exportCSVCustomer">Export Customers with Unallocated Meters</button>
      &nbsp;&nbsp;<button class="export-btn" @click="exportCSVOrder">Unallocated Meters</button>
      </div>
    </div>

    <!-- FILTERS -->
    <div class="filters">
      <input v-model="filters.search" placeholder="Search" @input="debouncedSearch"/>
      <button class="reset-btn" @click="resetFilters">Reset</button>
    </div>

    <!-- LOADER -->
    <div v-if="loading" class="loader">
      <div class="skeleton-row" v-for="i in 8" :key="i"></div>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper" v-if="!loading && orders.length">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Order</th>
            <th>Meter Number</th>
            <th>Power Code</th>
            <th>Token</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Product</th>
            <th>City</th>
            <th>Amount</th>
            <th>Status</th>
            <th width="160">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(o,i) in orders" :key="o.id">
            <td>{{ (currentPage-1)*perPage + i + 1 }}</td>
            <td><b>{{ o.order_id }}</b></td>
            <td><b>{{ o.meter?.serial_number || '-' }}</b></td>
            <td>{{ o.power_code || '-' }}</td>
            <td>{{ o.token || '-' }}</td>
            <td>{{ o.first_name }}</td>
            <td>{{ o.last_name }}</td>
            <td>{{ o.email }}</td>
            <td>{{ o.product_meta?.[0]?.product_name }}</td>
            <td>{{ o.billing_address?.city }}</td>
            <td class="amount">{{ Number(o.amount) }}NGN</td>
            <td>
              <span class="status" :class="o.status">{{ o.status }}</span>
            </td>
            <td>
              <button class="view-btn" @click="openView(o)">View</button>

              <button 
                v-if="o.type === 'meter_order'"
                class="assign-btn" 
                @click="openAssign(o)">
                Assign Meter
              </button>

            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!loading && !orders.length" class="no-data">No records found</p>

    <!-- PAGINATION -->
    <div class="pagination" v-if="lastPage > 1">
      <button @click="changePage(currentPage-1)" :disabled="currentPage===1">Prev</button>

      <button
        v-for="p in pages"
        :key="p"
        @click="changePage(p)"
        :class="{active:p===currentPage}"
      >
        {{ p }}
      </button>

      <button @click="changePage(currentPage+1)" :disabled="currentPage===lastPage">Next</button>
      <span class="total">Total: {{ totalRecords }}</span>
    </div>

    <!-- VIEW MODAL -->
    <div v-if="showView" class="modal-overlay">
      <div class="modal">
        <div class="modal-header">
          <h3>Order Details</h3>
          <span class="close" @click="showView=false">×</span>
        </div>

        <div v-if="selectedOrder" class="modal-body">
          <p><b>Order ID:</b> {{ selectedOrder.order_id }}</p>
          <p><b>Power Code:</b> {{ selectedOrder.power_code || '-' }}</p>
          <p><b>Token Number:</b> {{ selectedOrder.token || '-' }}</p>

          <p><b>Name:</b> {{ selectedOrder.first_name }} {{ selectedOrder.last_name }}</p>
          <p><b>Email:</b> {{ selectedOrder.email }}</p>
          <p><b>Phone:</b> {{ selectedOrder.phone_number }}</p>
          <p><b>Status:</b> {{ selectedOrder.status }}</p>
          <p><b>Amount:</b> ${{ selectedOrder.amount }}</p>

          <h4>Address</h4>
          <p>
            {{ selectedOrder.billing_address?.address1 }},
            {{ selectedOrder.billing_address?.city }},
            {{ selectedOrder.billing_address?.state }}
          </p>

          <h4>Products</h4>
          <ul>
            <li v-for="(p,i) in selectedOrder.product_meta" :key="i">
              {{ p.product_name }} (Qty: {{ p.quantity }})
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- ASSIGN METER MODAL -->
    <div v-if="showAssign" class="modal-overlay">
      <div class="modal">
        <div class="modal-header">
          <h3>Assign Meter</h3>
          <span class="close" @click="closeAssign">×</span>
        </div>

        <div class="modal-body">

          <label>
            External Customer ID <span style="color:red">*</span>
          </label>
          <input v-model="assignForm.external_customer_id" />
          <p v-if="assignError" class="error-msg">{{ assignError }}</p>

          <label>Meter Number</label>
          <input v-model="assignForm.serial_number" />

          <label>Max Current</label>
          <input type="number" v-model="assignForm.max_current" />

          <label>Phase</label>
          <select v-model="assignForm.phase">
            <option :value="1">1</option>
            <option :value="2">2</option>
            <option :value="3">3</option>
          </select>

          <br/><br/>
          <button 
            class="save-btn" 
            @click="assignMeter"
            :disabled="assigning || !assignForm.external_customer_id">

            <span v-if="assigning" class="btn-loader"></span>
            <span v-else>Save</span>

          </button>

        </div>
      </div>
    </div>

  </div>
</template>

<script>
import _ from "lodash"
import { OrderListService } from "@/services/OrderListService"

export default {
  data(){
    return{
      service:new OrderListService(),
      orders:[],
      loading:false,

      currentPage:1,
      lastPage:1,
      totalRecords:0,
      perPage:15,

      showView:false,
      selectedOrder:null,

      showAssign:false,
      assigning:false,
      selectedAssignOrder:null,
      assignError:"",

      assignForm:{
        external_customer_id:"",
        serial_number:"",
        max_current:null,
        phase:1
      },

      filters:{
        search:""
      }
    }
  },

  created(){
    this.debouncedSearch = _.debounce(this.searchOrders,500)
  },

  mounted(){
    this.loadOrders()
  },

  computed:{
    pages(){
      let arr=[]
      for(let i=1;i<=this.lastPage;i++) arr.push(i)
      return arr
    }
  },

  methods:{
    async loadOrders(page=1){
      this.loading=true
      const res = await this.service.fetchOrderList(page,this.filters)

      if(!res){ this.loading=false; return }

      this.orders = res.data
      this.totalRecords = res.total
      this.currentPage = res.current_page
      this.lastPage = res.last_page
      this.perPage = res.per_page

      this.loading=false
    },

    changePage(p){
      if(p<1||p>this.lastPage) return
      this.loadOrders(p)
    },

    searchOrders(){
      this.loadOrders(1)
    },

    resetFilters(){
      Object.keys(this.filters).forEach(k=>this.filters[k]="")
      this.loadOrders(1)
    },

    openView(o){
      this.selectedOrder=o
      this.showView=true
    },

    openAssign(order){
      this.selectedAssignOrder = order

      this.assignForm = {
        external_customer_id: order.external_customer_id || "",
        serial_number: order.meter?.serial_number || "",
        max_current: order.meter?.max_current || null,
        phase: order.meter?.phase || 1
      }

      this.assignError = ""
      this.showAssign = true
    },

    closeAssign(){
      this.showAssign = false
      this.assignForm = {
        external_customer_id:"",
        serial_number:"",
        max_current:null,
        phase:1
      }
      this.assignError=""
    },

    async assignMeter(){

      if(!this.assignForm.external_customer_id){
        this.assignError = "External Customer ID is required"
        return
      }

      this.assignError = ""

      try{
        this.assigning = true
        const token = localStorage.getItem("token")

        const response = await fetch(
          `http://139.59.181.1:8000/api/orders/${this.selectedAssignOrder.id}/assign-external-details`,
          {
            method: "POST",
            headers: {
              "Content-Type":"application/json",
              Authorization: `Bearer ${token}`
            },
            body: JSON.stringify(this.assignForm)
          }
        )

        const data = await response.json()

        if(!response.ok){
          alert(data.message || "Assignment failed")
          this.assigning = false
          return
        }

        // SUCCESS MESSAGE FROM API
        alert(data.message)

        // update table instantly
        this.selectedAssignOrder.external_customer_id = data.order.external_customer_id

        if(!this.selectedAssignOrder.meter){
          this.selectedAssignOrder.meter = {}
        }

        this.selectedAssignOrder.meter.serial_number = this.assignForm.serial_number
        this.selectedAssignOrder.meter.max_current = this.assignForm.max_current
        this.selectedAssignOrder.meter.phase = this.assignForm.phase

        this.showAssign = false

      }catch(e){
        console.log(e)
        alert("Something went wrong")
      }

      this.assigning = false
    },

    async exportCSVOrder(){
      try{
        const token = localStorage.getItem("token")
        const response = await fetch(
          `http://139.59.181.1:8000/api/orders/export/excel/?debug=0&template=1`,
          { method:"GET", headers:{ Authorization:`Bearer ${token}` } }
        )
        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement("a")
        a.href = url
        a.download = "orders.xlsx"
        document.body.appendChild(a)
        a.click()
        a.remove()
        window.URL.revokeObjectURL(url)
      }catch(e){
        alert("Excel download failed")
      }
    },

    async exportCSVCustomer(){
      try{
        const token = localStorage.getItem("token")
        const response = await fetch(
          `http://139.59.181.1:8000/api/orders/export/excel?debug=0&template=2`,
          { method:"GET", headers:{ Authorization:`Bearer ${token}` } }
        )
        const blob = await response.blob()
        const url = window.URL.createObjectURL(blob)
        const a = document.createElement("a")
        a.href = url
        a.download = "customers.xlsx"
        document.body.appendChild(a)
        a.click()
        a.remove()
        window.URL.revokeObjectURL(url)
      }catch(e){
        alert("Excel download failed")
      }
    }

  }
}
</script>


<style scoped>
.order-page{padding:25px;font-family:Arial}
.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.export-btn{background:#6c2bd9;color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer}
.filters{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px}
.filters input{padding:8px;border:1px solid #ddd;border-radius:6px}
.reset-btn{background:#f1f1f1;border:none;padding:8px 12px;border-radius:6px}
.table-wrapper{background:#fff;border-radius:10px;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden}
table{width:100%;border-collapse:collapse}
th{background:#f7f7f7;padding:12px;text-align:left}
td{padding:12px;border-top:1px solid #eee}
.amount{font-weight:bold;color:#6c2bd9}
.status{padding:4px 8px;border-radius:6px;font-size:12px;background:#eee}
.status.pending{background:#fff3cd;color:#856404}
.view-btn{background:#6c2bd9;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer}
.assign-btn{background:#10b981;color:#fff;border:none;padding:6px 10px;border-radius:6px;cursor:pointer;margin-left:6px}
.pagination{margin-top:20px;display:flex;gap:6px;align-items:center}
.pagination button{padding:6px 10px;border:1px solid #ccc;background:#fff}
.pagination button.active{background:#6c2bd9;color:#fff}
.total{margin-left:10px;font-weight:bold}
.loader .skeleton-row{height:45px;background:linear-gradient(90deg,#eee,#f7f7f7,#eee);margin-bottom:8px;border-radius:6px;animation:shimmer 1.2s infinite;}
@keyframes shimmer{0%{background-position:-200px}100%{background-position:200px}}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;z-index:999}
.modal{background:#fff;width:450px;border-radius:10px;padding:20px}
.modal-header{display:flex;justify-content:space-between;margin-bottom:10px}
.close{cursor:pointer;font-size:22px}
.no-data{text-align:center;margin-top:40px}
.modal-body label{display:block;margin-top:10px;font-size:13px;font-weight:bold}
.modal-body input,.modal-body select{width:100%;padding:8px;margin-top:4px;border:1px solid #ddd;border-radius:6px}
.save-btn{background:#6c2bd9;color:#fff;border:none;padding:8px 14px;border-radius:6px;cursor:pointer}
.error-msg{color:red;font-size:12px;margin-top:4px}

.btn-loader{
  width:16px;
  height:16px;
  border:2px solid #fff;
  border-top:2px solid transparent;
  border-radius:50%;
  display:inline-block;
  animation:spin 0.6s linear infinite;
}

@keyframes spin{
  from{transform:rotate(0deg)}
  to{transform:rotate(360deg)}
}
</style>
