<template>
  <div class="order-page">

    <!-- HEADER -->
    <div class="top-bar">
      <h2>Orders Management</h2>
      <button class="export-btn" @click="exportCSV">Export CSV</button>
    </div>

    <!-- FILTERS -->
    <div class="filters">
      <input v-model="filters.search" placeholder="Search" @input="debouncedSearch"/>
      <!-- <input v-model="filters.order_id" placeholder="Order ID" @input="debouncedSearch"/>
      <input v-model="filters.first_name" placeholder="First Name" @input="debouncedSearch"/>
      <input v-model="filters.last_name" placeholder="Last Name" @input="debouncedSearch"/>
      <input v-model="filters.email" placeholder="Email" @input="debouncedSearch"/>
      <input v-model="filters.phone_number" placeholder="Phone" @input="debouncedSearch"/>
      <input v-model="filters.status" placeholder="Status" @input="debouncedSearch"/>
      <input v-model="filters.type" placeholder="Type" @input="debouncedSearch"/>
      <input v-model="filters.power_code" placeholder="Power Code" @input="debouncedSearch"/>
      <input v-model="filters.token" placeholder="Token" @input="debouncedSearch"/>
      <input v-model="filters.amount" placeholder="Amount" @input="debouncedSearch"/> -->

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
            <th>Power Code</th>
            <th>Token</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Product</th>
            <th>City</th>
            <th>Amount</th>
            <th>Status</th>
            <th width="120">Action</th>
          </tr>
        </thead>

        <tbody>
          <tr v-for="(o,i) in orders" :key="o.id">
            <td>{{ (currentPage-1)*perPage + i + 1 }}</td>
            <td><b>{{ o.order_id }}</b></td>
            <td>{{ o.power_code || '-' }}</td>
            <td>{{ o.token || '-' }}</td>
            <td>{{ o.first_name }}</td>
            <td>{{ o.last_name }}</td>
            <td>{{ o.email }}</td>
            <td>{{ o.product_meta?.[0]?.product_name }}</td>
            <td>{{ o.billing_address?.city }}</td>
            <td class="amount">${{ Number(o.amount).toFixed(2) }}</td>
            <td>
              <span class="status" :class="o.status">{{ o.status }}</span>
            </td>
            <td>
              <button class="view-btn" @click="openView(o)">View</button>
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

      filters:{
        search:"",
        // order_id:"",
        // first_name:"",
        // last_name:"",
        // email:"",
        // phone_number:"",
        // status:"",
        // notes:"",
        // type:"",
        // amount:"",
        // power_code:"",
        // token:""
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

    async exportCSV(){
      try{
        const token = localStorage.getItem("token")

        const response = await fetch(
          `http://139.59.181.1:8000/api/orders/export/excel`,
          {
            method: "GET",
            headers: {
              Authorization: `Bearer ${token}`
            }
          }
        )

        if(!response.ok){
          alert("Export failed")
          return
        }

        const blob = await response.blob()
        const downloadUrl = window.URL.createObjectURL(blob)

        const a = document.createElement("a")
        a.href = downloadUrl
        a.download = "orders.xlsx"

        document.body.appendChild(a)
        a.click()
        a.remove()

        window.URL.revokeObjectURL(downloadUrl)

      }catch(e){
        console.log(e)
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

.pagination{margin-top:20px;display:flex;gap:6px;align-items:center}
.pagination button{padding:6px 10px;border:1px solid #ccc;background:#fff}
.pagination button.active{background:#6c2bd9;color:#fff}
.total{margin-left:10px;font-weight:bold}

.loader .skeleton-row{
height:45px;background:linear-gradient(90deg,#eee,#f7f7f7,#eee);
margin-bottom:8px;border-radius:6px;animation:shimmer 1.2s infinite;
}
@keyframes shimmer{0%{background-position:-200px}100%{background-position:200px}}

.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center}
.modal{background:#fff;width:450px;border-radius:10px;padding:20px}
.modal-header{display:flex;justify-content:space-between;margin-bottom:10px}
.close{cursor:pointer;font-size:22px}
.no-data{text-align:center;margin-top:40px}
</style>
