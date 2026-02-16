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

    <!-- CUSTOMER -->
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
      </md-field>
    </div>

    <!-- ORDER -->
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

    <div class="md-layout-item md-size-50"><md-field><label>First Name</label>
      <md-input v-model="form.billing.first_name"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Last Name</label>
      <md-input v-model="form.billing.last_name"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Address 1</label>
      <md-input v-model="form.billing.address1"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Address 2</label>
      <md-input v-model="form.billing.address2"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>City</label>
      <md-input v-model="form.billing.city"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>State</label>
      <md-input v-model="form.billing.state"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Phone</label>
      <md-input v-model="form.billing.phone_number"/></md-field></div>

    <!-- SAME SHIPPING -->
    <div class="md-layout-item md-size-100">
      <md-checkbox v-model="form.sameShipping" @change="copyBillingToShipping">
        Shipping same as Billing
      </md-checkbox>
    </div>

    <!-- SHIPPING -->
    <div class="md-layout-item md-size-100"><h3>Shippi  ng Address</h3></div>

    <div class="md-layout-item md-size-50"><md-field><label>First Name</label>
      <md-input v-model="form.shipping.first_name" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Last Name</label>
      <md-input v-model="form.shipping.last_name" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Address 1</label>
      <md-input v-model="form.shipping.address1" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Address 2</label>
      <md-input v-model="form.shipping.address2" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>City</label>
      <md-input v-model="form.shipping.city" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>State</label>
      <md-input v-model="form.shipping.state" :disabled="form.sameShipping"/></md-field></div>

    <div class="md-layout-item md-size-50"><md-field><label>Phone</label>
      <md-input v-model="form.shipping.phone_number" :disabled="form.sameShipping"/></md-field></div>

    <!-- ACTIONS -->
    <div class="md-layout-item md-size-100 actions">
      <md-button class="md-raised md-primary" :disabled="loading" @click="saveOrder">
        {{ loading ? "Saving..." : "Save" }}
      </md-button>
      <md-button class="md-raised" @click="goBack">Close</md-button>
    </div>

  </div>
</template>

<script>
import { AddOrderService } from "@/services/AddOrderService"
import { ErrorHandler } from "@/Helpers/ErrorHandler"

export default {
  data() {
    return {
      service: new AddOrderService(),
      submitted:false,
      loading:false,
      errors:{},

      form:{
        type:"product_order",
        customer:{first_name:"",last_name:"",email:"",phone_number:""},
        order:{product_name:"",quantity:1,amount:null,purchased_at:new Date(),notes:""},
        billing:{first_name:"",last_name:"",address1:"",address2:"",city:"",state:"",phone_number:""},
        shipping:{first_name:"",last_name:"",address1:"",address2:"",city:"",state:"",phone_number:""},
        sameShipping:false
      }
    }
  },

  methods:{
    copyBillingToShipping(){
      if(this.form.sameShipping){
        this.form.shipping={...this.form.billing}
      }
    },

    formatDateTime(date){
      const d=new Date(date)
      const pad=n=>n<10?"0"+n:n
      return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    },

    validateForm(){
      this.errors={}
      if(!this.form.customer.first_name) this.errors.first_name="Required"
      if(!this.form.customer.phone_number) this.errors.phone="Required"
      if(!this.form.order.product_name) this.errors.product="Required"
      if(!this.form.order.amount) this.errors.amount="Required"
      return !Object.keys(this.errors).length
    },

    async saveOrder(){
      this.submitted=true
      this.loading=true
      if(!this.validateForm()){this.loading=false;return}

      this.$swal.fire({title:"Please wait...",allowOutsideClick:false,didOpen:()=>this.$swal.showLoading()})

      const payload={
        type:this.form.type,
        first_name:this.form.customer.first_name,
        last_name:this.form.customer.last_name,
        email:this.form.customer.email,
        phone_number:this.form.customer.phone_number,
        status:"pending",
        amount:this.form.order.amount,
        purchased_at:this.formatDateTime(this.form.order.purchased_at),
        notes:this.form.order.notes,
        product_meta:[{product_name:this.form.order.product_name,quantity:this.form.order.quantity}],
        billing_address:{...this.form.billing},
        shipping_address:{...this.form.shipping}
      }

      try{
        const res = await this.service.createOrder(payload)

        if(res instanceof ErrorHandler){
          this.loading=false
          this.$swal.fire("Error", res.message, "error")
          return
        }

        if(res && res.data && res.data.id){
          this.$swal.close()
          this.$swal.fire({icon:"success",title:"Order added successfully",timer:1500,showConfirmButton:false})
          setTimeout(()=>this.$router.push("/dashboards/ordersList"),1200)
        }else{
          this.loading=false
          this.$swal.fire("Error","Order not created","error")
        }

      }catch(e){
        this.loading=false
        this.$swal.fire("Error","Server error","error")
      }
    },

    goBack(){
      this.$router.push("/dashboards/ordersList")
    }
  }
}
</script>

<style scoped>
.add-order-page{padding:20px}
.actions{margin-top:30px;display:flex;gap:10px}
</style>
