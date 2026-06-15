<!-- micropowermanager-main\frontend\src\modules\Bluetti\DeviceList.vue -->
<template>
  <div class="device-page">

    <!-- HEADER -->
    <div class="top-bar">
      <h2>BLUETTI Device Management</h2>
      <button class="add-btn" @click="goToAddDevice">+ Add Device</button>
    </div>

    <!-- FILTERS -->
    <div class="filters">
      <input v-model="filters.search" placeholder="Search by Device Name, S/N, Client..." @input="debouncedSearch" />
      <button class="reset-btn" @click="resetFilters">Reset</button>
    </div>

    <!-- LOADER -->
    <div v-if="loading" class="loader">
      <div class="skeleton-row" v-for="i in 8" :key="i"></div>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper" v-if="!loading && devices.length">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Device Name</th>
            <th>S/N</th>
            <th>Client</th>
            <th>Assigned To</th>
            <th>Style</th>
            <th>Created Date</th>
            <th width="140">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(d, i) in devices" :key="d.id">
            <td>{{ (currentPage - 1) * perPage + i + 1 }}</td>
            <td><b>{{ d.device_name }}</b></td>
            <td><b>{{ d.serial_number }}</b></td>
            <td>{{ d.client }}</td>
           <td>
              <span v-if="d.customer" class="badge-assigned">
                {{ d.customer.name }} {{ d.customer.surname }}
              </span>
              <span v-else class="badge-free">Free</span>
            </td>
            <td>{{ d.style }}</td>
            <td>{{ formatDate(d.created_date || d.created_at) }}</td>
            <td>
              <button class="view-btn" @click="openView(d)">View</button>
              <button class="delete-btn" @click="deleteDevice(d)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!loading && !devices.length" class="no-data">No records found</p>

    <!-- PAGINATION -->
    <div class="pagination" v-if="lastPage > 1">
      <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1">Prev</button>
      <button
        v-for="p in pages"
        :key="p"
        @click="changePage(p)"
        :class="{ active: p === currentPage }"
      >{{ p }}</button>
      <button @click="changePage(currentPage + 1)" :disabled="currentPage === lastPage">Next</button>
      <span class="total">Total: {{ totalRecords }}</span>
    </div>

    <!-- VIEW MODAL -->
    <div v-if="showView" class="modal-overlay">
      <div class="modal">
        <div class="modal-header">
          <h3>Device Details</h3>
          <span class="close" @click="showView = false">×</span>
        </div>
        <div v-if="selectedDevice" class="modal-body">
          <p><b>Device Name:</b> {{ selectedDevice.device_name }}</p>
          <p><b>S/N:</b> {{ selectedDevice.serial_number }}</p>
          <p><b>Client:</b> {{ selectedDevice.client }}</p>
          <p><b>Style:</b> {{ selectedDevice.style }}</p>
          <p><b>Created Date:</b> {{ formatDate(selectedDevice.created_date || selectedDevice.created_at) }}</p>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import _ from "lodash"
import { DeviceListService } from "@/services/DeviceListService"

export default {
  data() {
    return {
      service: new DeviceListService(),
      devices: [],
      loading: false,
      currentPage: 1,
      lastPage: 1,
      totalRecords: 0,
      perPage: 15,
      showView: false,
      selectedDevice: null,
      filters: { search: "" },
    }
  },

  created() {
    this.debouncedSearch = _.debounce(this.searchDevices, 500)
  },

  mounted() {
    this.loadDevices()
  },

  computed: {
    pages() {
      let arr = []
      for (let i = 1; i <= this.lastPage; i++) arr.push(i)
      return arr
    },
  },

  methods: {
    formatDate(dt) {
      if (!dt) return "-"
      return new Date(dt).toLocaleDateString()
    },

    async loadDevices(page = 1) {
      this.loading = true
      const res = await this.service.fetchDeviceList(page, this.filters)

      if (!res) {
        this.loading = false
        return
      }

      this.devices      = res.data || res || []
      this.totalRecords = res.total || this.devices.length
      this.currentPage  = res.current_page || page
      this.lastPage     = res.last_page || 1
      this.perPage      = res.per_page || 15

      this.loading = false
    },

    changePage(p) {
      if (p < 1 || p > this.lastPage) return
      this.loadDevices(p)
    },

    searchDevices() {
      this.loadDevices(1)
    },

    resetFilters() {
      this.filters.search = ""
      this.loadDevices(1)
    },

    openView(d) {
      this.selectedDevice = d
      this.showView = true
    },

    goToAddDevice() {
      this.$router.push("/dashboards/bluetti/add-device")
    },

   
    async deleteDevice(d) {
      const result = await this.$swal({
        title: "Are you sure?",
        text: `Delete device "${d.device_name}"?`,
        type: "warning",              // ✅
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete",
      })

      if (!result) return             // ✅ SweetAlert1 mein value = true/false

      const res = await this.service.deleteDevice(d.id)

      if (res === true) {
        this.$swal({
          type: "success",            // ✅
          title: "Deleted",
          timer: 1200,
          showConfirmButton: false,
        })
        this.loadDevices(this.currentPage)
      } else {
        this.$swal("Error", "Could not delete device", "error")
      }
    },
  },
}
</script>

<style scoped>
.device-page { padding: 25px; font-family: Arial; background: #f5f6fa; }

.top-bar {
  display: flex; justify-content: space-between; align-items: center;
  margin-bottom: 15px; flex-wrap: wrap; gap: 10px;
}

.add-btn {
  background: #6c2bd9; color: #fff; border: none;
  padding: 8px 14px; border-radius: 6px; cursor: pointer; transition: .2s ease;
}
.add-btn:hover { opacity: .9; }

.filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
.filters input {
  flex: 1; min-width: 220px; padding: 8px;
  border: 1px solid #ddd; border-radius: 6px;
}
.reset-btn {
  background: #f1f1f1; border: none;
  padding: 8px 12px; border-radius: 6px; cursor: pointer;
}

.table-wrapper {
  background: #fff; border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,.06); overflow-x: auto;
}

table { width: 100%; min-width: 800px; border-collapse: collapse; }

th {
  background: #f7f7f7; padding: 12px; text-align: left;
  position: sticky; top: 0; z-index: 2; font-size: 14px; border: 1px solid #ddd;
}

td {
  padding: 12px; border: 1px solid #eee;
  font-size: 14px; white-space: nowrap; vertical-align: middle;
}

.view-btn {
  background: #6c2bd9; color: #fff; border: none;
  padding: 6px 10px; border-radius: 6px; cursor: pointer;
}
.delete-btn {
  background: #e53935; color: #fff; border: none;
  padding: 6px 10px; border-radius: 6px; cursor: pointer; margin-left: 6px;
}

.pagination {
  margin-top: 20px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
}
.pagination button {
  padding: 6px 10px; border: 1px solid #ccc; background: #fff; cursor: pointer;
}
.pagination button.active { background: #6c2bd9; color: #fff; }
.total { margin-left: 10px; font-weight: bold; }

.loader .skeleton-row {
  height: 45px;
  background: linear-gradient(90deg, #eee, #f7f7f7, #eee);
  margin-bottom: 8px; border-radius: 6px; animation: shimmer 1.2s infinite;
}
@keyframes shimmer {
  0% { background-position: -200px }
  100% { background-position: 200px }
}

.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.4);
  display: flex; align-items: center; justify-content: center; z-index: 999; padding: 15px;
}
.modal {
  background: #fff; width: 90%; max-width: 500px; border-radius: 10px;
  padding: 20px; box-shadow: 0 10px 30px rgba(0,0,0,.15);
  max-height: 90vh; overflow-y: auto;
}
.modal-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
.close { cursor: pointer; font-size: 22px; }
.modal-body p { margin: 8px 0; font-size: 14px; }
.no-data { text-align: center; margin-top: 40px; }

.badge-assigned {
  background: #e8f5e9;
  color: #2e7d32;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
.badge-free {
  background: #f5f5f5;
  color: #aaa;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
}
</style>
