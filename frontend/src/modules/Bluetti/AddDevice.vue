<!-- micropowermanager-main\frontend\src\modules\Bluetti\AddDevice.vue -->
<template>
  <div class="md-layout md-gutter add-device-page">

    <!-- TITLE -->
    <div class="md-layout-item md-size-100">
      <h2>Add Device</h2>
    </div>

    <!-- DEVICE NAME -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.device_name }">
        <label>Device Name</label>
        <md-input v-model="form.device_name" />
        <span class="md-error" v-if="errors.device_name">{{ errors.device_name }}</span>
      </md-field>
    </div>

    <!-- SERIAL NUMBER -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.serial_number }">
        <label>S/N (Serial Number)</label>
        <md-input v-model="form.serial_number" />
        <span class="md-error" v-if="errors.serial_number">{{ errors.serial_number }}</span>
      </md-field>
    </div>

    <!-- CLIENT -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.client }">
        <label>Client</label>
        <md-input v-model="form.client" />
        <span class="md-error" v-if="errors.client">{{ errors.client }}</span>
      </md-field>
    </div>

    <!-- STYLE -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.style }">
        <label>Style</label>
        <md-input v-model="form.style" />
        <span class="md-error" v-if="errors.style">{{ errors.style }}</span>
      </md-field>
    </div>

    <!-- PRICE -->
    <div class="md-layout-item md-size-50">
      <md-field :class="{ 'md-invalid': submitted && errors.price }">
        <label>Price (₦)</label>
        <md-input v-model.number="form.price" type="number" min="0" />
        <span class="md-error" v-if="errors.price">{{ errors.price }}</span>
      </md-field>
    </div>

    <!-- CREATED DATE -->
    <div class="md-layout-item md-size-50">
      <md-datepicker v-model="form.created_date">
        <label>Created Date</label>
      </md-datepicker>
    </div>

    <!-- ACTIONS -->
    <div class="md-layout-item md-size-100 actions">
      <md-button
        class="md-raised md-primary"
        :disabled="loading || !isFormValid"
        @click="saveDevice"
      >
        {{ loading ? "Saving..." : "Save" }}
      </md-button>
      <md-button class="md-raised" @click="goBack">Close</md-button>
    </div>

  </div>
</template>

<script>
import { AddDeviceService } from "@/services/AddDeviceService"

export default {
  data() {
    return {
      service: new AddDeviceService(),
      submitted: false,
      loading: false,
      errors: {},
      form: {
        device_name: "",
        serial_number: "",
        client: "",
        style: "",
        created_date: new Date(),
        price: null, 
      },
    }
  },

  computed: {
    isFormValid() {
      return (
        this.form.device_name &&
        this.form.serial_number &&
        this.form.client &&
        this.form.style &&
        this.form.created_date &&
        (this.form.price || this.form.price === 0)
      )
    },
  },

  methods: {
    validateForm() {
      this.errors = {}
      if (!this.form.device_name)   this.errors.device_name   = "Device Name is required"
      if (!this.form.serial_number) this.errors.serial_number = "Serial Number is required"
      if (!this.form.client)        this.errors.client        = "Client is required"
      if (!this.form.style)         this.errors.style         = "Style is required"
      if (!this.form.price && this.form.price !== 0) this.errors.price = "Price is required"
      return !Object.keys(this.errors).length
    },

    formatDateTime(date) {
      const d = new Date(date)
      const pad = (n) => (n < 10 ? "0" + n : n)
      return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
    },

    async saveDevice() {
      this.submitted = true
      this.loading = true

      if (!this.validateForm()) {
        this.loading = false
        return
      }

      const payload = {
        device_name:   this.form.device_name,
        serial_number: this.form.serial_number,
        client:        this.form.client,
        style:         this.form.style,
        created_date:  this.formatDateTime(this.form.created_date),
        price:         this.form.price,   // ✅ new
      }

      try {
        const res = await this.service.createDevice(payload)
        this.loading = false

        // SUCCESS
        if (res && res.id) {
          this.$swal.fire({
            icon: "success",
            title: "Device added successfully",
            timer: 1500,
            showConfirmButton: false,
          })
          setTimeout(() => {
            this.$router.push("/dashboards/bluetti/device-list")
          }, 1200)
          return
        }

        // VALIDATION ERRORS
        if (res?.errors) {
          const firstError = Object.values(res.errors)[0][0]
          this.$swal.fire("Validation Error", firstError, "error")
          return
        }

        // OTHER ERROR MESSAGE
        if (res?.message) {
          this.$swal.fire("Error", res.message, "error")
          return
        }

        this.$swal.fire("Error", "Something went wrong", "error")

      } catch (e) {
        this.loading = false
        this.$swal.fire("Error", "Server error", "error")
      }
    },

    goBack() {
      this.$router.push("/dashboards/bluetti/device-list")
    },
  },
}
</script>

<style>
.add-device-page { padding: 20px; }
.actions { margin-top: 30px; display: flex; gap: 10px; }
</style>
