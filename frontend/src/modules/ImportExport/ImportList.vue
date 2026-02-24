<template>
  <div class="import-list-page">
    <div class="header">
      <h2>People List</h2>
      <button
        class="refresh-btn"
        @click="loadImports(meta?.current_page || 1)"
      >
        🔄 Refresh
      </button>
    </div>

    <!-- Loading -->
    <p v-if="loading">Loading...</p>

    <!-- Error -->
    <p v-if="errorMessage" class="error">
      {{ errorMessage }}
    </p>

    <!-- Table -->
    <table v-if="!loading && imports.length">
      <thead>
        <tr>
          <th>#</th>
          <th>Customer No</th>
          <th>Name</th>
          <th>NIN</th>
          <th>Phone</th>
          <th>Meter No</th>
          <th>Type</th>
          <!-- <th>Active</th> -->
          <th>City</th>
          <th>Devices</th>
          <th>Created At</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(item, index) in imports" :key="item.id">
          <td>{{ index + 1 }}</td>

          <!-- Customer Number -->
          <td>{{ item.external_customer_id || "-" }}</td>

          <!-- Name -->
          <td>{{ item.name }} {{ item.surname }}</td>

          <!-- NIN -->
          <td>{{ item.national_id_number || "-" }}</td>

          <!-- Phone -->
          <td>{{ getPrimaryPhone(item.addresses) }}</td>

          <!-- Meter Number -->
          <td>{{ getMeterNumbers(item.devices) }}</td>

          <!-- Type -->
          <td>{{ item.type }}</td>

          <!-- Active -->
          <!-- <td>
            <span :class="item.is_active ? 'active' : 'inactive'">
              {{ item.is_active ? "Active" : "Inactive" }}
            </span>
          </td> -->

          <!-- City -->
          <td>{{ getPrimaryCity(item.addresses) }}</td>

          <!-- Total Devices -->
          <td>{{ item.devices?.length || 0 }}</td>

          <!-- Created -->
          <td>{{ formatDate(item.created_at) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Empty -->
    <p v-if="!loading && !imports.length">
      No records found
    </p>

    <!-- Pagination -->
    <div v-if="meta" class="pagination">
      <button
        :disabled="meta.current_page === 1"
        @click="loadImports(meta.current_page - 1)"
      >
        Prev
      </button>

      <span>
        Page {{ meta.current_page }} of {{ meta.last_page }}
      </span>

      <button
        :disabled="meta.current_page === meta.last_page"
        @click="loadImports(meta.current_page + 1)"
      >
        Next
      </button>
    </div>
  </div>
</template>

<script>
import { MiniGridImportListService } from "@/services/MiniGridImportListService"

export default {
  name: "ImportList",

  data() {
    return {
      service: new MiniGridImportListService(),
      imports: [],
      meta: null,
      loading: false,
      errorMessage: "",
    }
  },

  mounted() {
    this.loadImports()
  },

  methods: {
    async loadImports(page = 1) {
      try {
        this.loading = true
        this.errorMessage = ""

        const result = await this.service.fetchImportList(page)

        if (result instanceof Error) {
          this.errorMessage = result.message
          return
        }

        this.imports = result.data
        this.meta = {
          current_page: result.current_page,
          last_page: result.last_page,
        }
      } catch (e) {
        this.errorMessage = "Failed to load list"
      } finally {
        this.loading = false
      }
    },

    formatDate(date) {
      return date ? new Date(date).toLocaleString() : "-"
    },

    getPrimaryCity(addresses = []) {
      const primary = addresses.find(a => a.is_primary)
      return primary?.city?.name || "-"
    },

    getPrimaryPhone(addresses = []) {
      const primary = addresses.find(a => a.is_primary)
      return primary?.phone || "-"
    },

    getMeterNumbers(devices = []) {
      if (!devices.length) return "-"

      return (
        devices
          .filter(d => d.device_type === "meter")
          .map(d => d.device_serial)
          .join(", ") || "-"
      )
    },
  },
}
</script>

<style scoped>
.import-list-page {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.refresh-btn {
  padding: 6px 12px;
  background: #3498db;
  color: white;
  border: none;
  cursor: pointer;
  border-radius: 4px;
  font-size: 14px;
}

.refresh-btn:hover {
  background: #2980b9;
}

table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
}

th,
td {
  border: 1px solid #ddd;
  padding: 10px;
  text-align: left;
}

th {
  background: #f5f5f5;
}

.error {
  color: red;
}

.active {
  color: green;
  font-weight: 600;
}

.inactive {
  color: red;
  font-weight: 600;
}

.pagination {
  margin-top: 15px;
  display: flex;
  gap: 10px;
  align-items: center;
}

button:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>