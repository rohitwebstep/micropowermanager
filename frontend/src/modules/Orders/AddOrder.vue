<template>
  <div class="md-layout md-gutter add-order-page">

    <!-- TITLE -->
    <div class="md-layout-item md-size-100">
      <h2>Add Order</h2>
    </div>

    <!-- ORDER TYPE -->
    <div class="md-layout-item md-size-50">
      <md-field>
        <label>Order Type</label>
        <md-select v-model="form.type">
          <md-option value="product_order">Product Order</md-option>
          <md-option value="meter_order">Meter Order</md-option>
        </md-select>
      </md-field>
    </div>

    <!-- CLUSTER -->
    <div class="md-layout-item md-size-50">
      <md-field>
        <label>Cluster</label>
        <md-select v-model="selectedClusterId">
          <md-option value="">Select Cluster</md-option>
          <md-option
            v-for="cluster in clusters"
            :key="cluster.id"
            :value="cluster.id"
          >
            {{ cluster.name }}
          </md-option>
        </md-select>
      </md-field>
    </div>

    <!-- MINI GRID (AUTO FILLED INPUT) -->
    <div class="md-layout-item md-size-50">
      <md-field>
        <label>Mini Grid</label>
        <md-input v-model="selectedMiniGridName" readonly />
        <span class="md-error" v-if="selectedClusterId && !selectedMiniGridId">
          No Mini Grid available for this cluster
        </span>
      </md-field>
    </div>

    <!-- CITY (AUTO FILLED INPUT) -->
    <div class="md-layout-item md-size-50">
      <md-field>
        <label>City</label>
        <md-input v-model="selectedCity" readonly />
        <span class="md-error" v-if="selectedMiniGridId && !selectedCity">
          No City available for this mini grid
        </span>
      </md-field>
    </div>

    <!-- CUSTOMER INFO -->
    <div class="md-layout-item md-size-100"><h3>Customer Info</h3></div>

    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.first_name }">
        <label>First Name</label>
        <md-input v-model="form.customer.first_name"/>
        <span class="md-error" v-if="errors.first_name">{{errors.first_name}}</span>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-field><label>Last Name</label>
        <md-input v-model="form.customer.last_name"/>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-field><label>Email</label>
        <md-input v-model="form.customer.email"/>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.phone }">
        <label>Phone</label>
        <md-input v-model="form.customer.phone_number"/>
        <span class="md-error" v-if="errors.phone">
          {{ errors.phone }}
        </span>
      </md-field>
    </div>

    <!-- NIN -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': errors.nin }">
        <label>NIN</label>
        <md-input
          v-model="form.customer.nin"
          maxlength="11"
          placeholder="Enter your National Identification Number"
          @input="validateNinLive"
        />
        <span class="md-error" v-if="errors.nin">{{errors.nin}}</span>
      </md-field>
    </div>

    <!-- ORDER INFO -->
    <div class="md-layout-item md-size-100"><h3>Order Info</h3></div>

    <div class="md-layout-item md-size-50">
      <md-field><label>Product Name</label>
        <md-input v-model="form.order.product_name"/>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-field><label>Quantity</label>
        <md-input type="number" v-model.number="form.order.quantity"/>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.amount }">
        <label>Amount</label>
        <md-input type="number" v-model.number="form.order.amount"/>
      </md-field>
    </div>

    <div class="md-layout-item md-size-50">
      <md-datepicker v-model="form.order.purchased_at">
        <label>Purchased Date</label>
      </md-datepicker>
    </div>

    <div class="md-layout-item md-size-100">
      <md-field>
        <label>Notes</label>
        <md-textarea v-model="form.order.notes"/>
      </md-field>
    </div>

    <!-- BILLING -->
    <div class="md-layout-item md-size-100"><h3>Billing Address</h3></div>

    <div class="md-layout-item md-size-50"><md-field><label>First Name</label><md-input v-model="form.billing.first_name"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Last Name</label><md-input v-model="form.billing.last_name"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Address 1</label><md-input v-model="form.billing.address1"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Address 2</label><md-input v-model="form.billing.address2"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>City</label><md-input v-model="form.billing.city"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>State</label><md-input v-model="form.billing.state"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Phone</label><md-input v-model="form.billing.phone_number"/></md-field></div>

    <!-- SHIPPING -->
    <div class="md-layout-item md-size-100">
      <md-checkbox v-model="form.sameShipping">Shipping same as Billing</md-checkbox>
    </div>

    <div class="md-layout-item md-size-100"><h3>Shipping Address</h3></div>

    <div class="md-layout-item md-size-50"><md-field><label>First Name</label><md-input v-model="form.shipping.first_name" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Last Name</label><md-input v-model="form.shipping.last_name" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Address 1</label><md-input v-model="form.shipping.address1" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Address 2</label><md-input v-model="form.shipping.address2" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>City</label><md-input v-model="form.shipping.city" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>State</label><md-input v-model="form.shipping.state" :disabled="form.sameShipping"/></md-field></div>
    <div class="md-layout-item md-size-50"><md-field><label>Phone</label><md-input v-model="form.shipping.phone_number" :disabled="form.sameShipping"/></md-field></div>

    <!-- ACTIONS -->
    <div class="md-layout-item md-size-100 actions">
      <md-button class="md-raised md-primary" :disabled="loading || !isFormValid" @click="saveOrder">
        {{ loading ? "Saving..." : "Save" }}
      </md-button>
      <md-button class="md-raised" @click="goBack">Close</md-button>
    </div>

  </div>
</template>

<script>
import { AddOrderService } from "@/services/AddOrderService"
import { ErrorHandler } from "@/Helpers/ErrorHandler"
import axios from "axios"
import { config } from "@/config"

export default {
  data() {
    return {
      service: new AddOrderService(),
      submitted:false,
      loading:false,
      errors:{},

      clusters: [],
      selectedClusterId: "",
      selectedMiniGridId: "",
      selectedMiniGridName: "",
      selectedCity: "",

      form:{
        type:"product_order",
        customer:{first_name:"",last_name:"",email:"",phone_number:"",nin:""},
        order:{product_name:"",quantity:1,amount:null,purchased_at:new Date(),notes:""},
        billing:{first_name:"",last_name:"",address1:"",address2:"",city:"",state:"",phone_number:""},
        shipping:{first_name:"",last_name:"",address1:"",address2:"",city:"",state:"",phone_number:""},
        sameShipping:false
      }
    }
  },

  mounted(){
    this.fetchClusters()
  },

  watch:{
    selectedClusterId(newVal){

      // ALWAYS RESET FIRST
      this.selectedMiniGridId=""
      this.selectedMiniGridName=""
      this.selectedCity=""

      const cluster = this.clusters.find(c => c.id == newVal)
      if(!cluster) return

      if(cluster.mini_grids && cluster.mini_grids.length){

        const miniGrid = cluster.mini_grids[0]
        this.selectedMiniGridId = miniGrid.id
        this.selectedMiniGridName = miniGrid.name

        if(miniGrid.cities && miniGrid.cities.length){
          this.selectedCity = miniGrid.cities[0].name
        }
      }
    },

    'form.sameShipping'(val){
      if(val){
        this.form.shipping = { ...this.form.billing }
      }
    },

    'form.billing':{
      deep:true,
      handler(newVal){
        if(this.form.sameShipping){
          this.form.shipping = { ...newVal }
        }
      }
    }
  },

  computed:{
    isFormValid(){
      return (
        this.form.customer.first_name &&
        this.form.customer.phone_number &&
        /^[0-9]{11}$/.test(this.form.customer.nin) &&
        this.form.order.product_name &&
        this.form.order.amount &&
        this.selectedClusterId &&
        this.selectedMiniGridId !== "" &&
        this.selectedMiniGridName !== "" &&
        this.selectedCity !== ""
      )
    }
  },

  methods:{
    async fetchClusters(){
      const token = localStorage.getItem("token")
      const res = await axios.get(
        `${config.mpmBackendUrl}/api/clusters?type=dropdown`,
        { headers:{Authorization:`Bearer ${token}`}}
      )
      this.clusters = res.data.data || []
    },

    validateNinLive(){
      const nin=this.form.customer.nin
      if(!nin) this.errors.nin="NIN is required"
      else if(!/^[0-9]+$/.test(nin)) this.errors.nin="NIN must contain only digits"
      else if(nin.length!==11) this.errors.nin="NIN must be exactly 11 digits"
      else this.errors.nin=""
    },

    validateForm(){
      this.errors={}
      if(!this.form.customer.first_name) this.errors.first_name="Required"
      if(!this.form.customer.phone_number) this.errors.phone="Required"
      if(!this.form.customer.nin || !/^[0-9]{11}$/.test(this.form.customer.nin)) this.errors.nin="Invalid NIN"
      if(!this.form.order.product_name) this.errors.product="Required"
      if(!this.form.order.amount) this.errors.amount="Required"
      return !Object.keys(this.errors).length
    },

    async saveOrder(){
      this.submitted = true
      this.loading = true

      if(!this.validateForm()){
        this.loading = false
        return
      }

      const cluster = this.clusters.find(c => c.id == this.selectedClusterId)

      const payload = {
        type:this.form.type,
        mini_grid_id:this.selectedMiniGridId,
        state_name:cluster ? cluster.name : "",
        city_name:this.selectedCity,
        first_name:this.form.customer.first_name,
        last_name:this.form.customer.last_name,
        email:this.form.customer.email,
        phone_number:this.form.customer.phone_number,
        national_id_number:this.form.customer.nin,
        status:"pending",
        amount:this.form.order.amount,
        purchased_at:this.formatDateTime(this.form.order.purchased_at),
        notes:this.form.order.notes,
        product_meta:[
          {
            product_name:this.form.order.product_name,
            quantity:this.form.order.quantity
          }
        ],
        billing_address:{...this.form.billing},
        shipping_address:{...this.form.shipping}
      }

      try{
        const res = await this.service.createOrder(payload)
        this.loading = false

        // ✅ SUCCESS
        if(res && res.data && res.data.id){
          this.$swal.fire({
            icon:"success",
            title:"Order added successfully",
            timer:1500,
            showConfirmButton:false
          })

          setTimeout(()=>{
            this.$router.push("/dashboards/ordersList")
          },1200)

          return
        }

        // ❌ ERROR
        if(res?.errors){
          const firstError = Object.values(res.errors)[0][0]
          this.$swal.fire("Validation Error", firstError, "error")
          return
        }

        // 🔥 HANDLE NORMAL ERROR MESSAGE
        if(res?.message){
          this.$swal.fire("Error", res.message, "error")
          return
        }

        // 🔥 FALLBACK
        this.$swal.fire("Error", "Something went wrong", "error")

      }catch(e){
        this.loading = false

        if(e.response && e.response.data?.errors){
          const firstError = Object.values(e.response.data.errors)[0][0]
          this.$swal.fire("Validation Error", firstError, "error")
          return
        }

        this.$swal.fire("Error","Server error","error")
      }
    },

    formatDateTime(date){
      const d=new Date(date)
      const pad=n=>n<10?"0"+n:n
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    },

    goBack(){
      this.$router.push("/dashboards/ordersList")
    }
  }
}
</script>

<style>
.add-order-page{padding:20px}
.actions{margin-top:30px;display:flex;gap:10px}
.pagination .active {
    width: auto !important;
  }
</style>