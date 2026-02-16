<template>
  <div class="import-list-page">
    <h2>People List</h2>

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
          <th>Name</th>
          <th>Type</th>
          <th>Active</th>
          <th>Devices</th>
          <th>City</th>
          <th>Created At</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(item, index) in imports" :key="item.id">
          <td>{{ index + 1 }}</td>

          <td>
            {{ item.name }} {{ item.surname }}
          </td>

          <td>{{ item.type }}</td>

          <td>
            <span :class="item.is_active ? 'active' : 'inactive'">
              {{ item.is_active ? "Active" : "Inactive" }}
            </span>
          </td>

          <td>{{ item.devices.length }}</td>

          <td>{{ getPrimaryCity(item.addresses) }}</td>

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
      return new Date(date).toLocaleString()
    },

    getPrimaryCity(addresses = []) {
      const primary = addresses.find(a => a.is_primary)
      return primary?.city?.name || "-"
    },
  },
}
</script>

<style scoped>
.import-list-page {
  padding: 20px;
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
</style>
