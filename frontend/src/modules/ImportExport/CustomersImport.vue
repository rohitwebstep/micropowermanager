<template>
  <div class="import-export-page">
    <h2>Customer Import</h2>

    <!-- MINI GRID DROPDOWN -->
    <div class="form-group">
      <label>Mini Grid</label> &nbsp;
      <select v-model="selectedMiniGridId" @change="handleMiniGridChange">
        <option value="">Select Mini Grid</option>
        <option
          v-for="grid in miniGrids"
          :key="grid.id"
          :value="grid.id"
        >
          {{ grid.name }}
        </option>
      </select>
    </div>

    <!-- CLUSTER DROPDOWN -->
    <div class="form-group">
      <label>Cluster</label> &nbsp;
      <select v-model="selectedClusterId">
        <option value="">Select Cluster</option>
        <option
          v-for="cluster in clusters"
          :key="cluster.id"
          :value="cluster.id"
        >
          {{ cluster.name }}
        </option>
      </select>
    </div>

    <!-- FILE SELECT -->
    <button @click="selectFile" :disabled="loading">
      Select File
    </button>

    <p v-if="selectedFile" class="file-name">
      Selected file: <strong>{{ selectedFile.name }}</strong>
    </p>

    <!-- UPLOAD -->
    <button
      @click="submitUpload"
      :disabled="!selectedFile || !selectedMiniGridId || loading"
    >
      {{ loading ? "Importing..." : "Upload & Import Customers" }}
    </button>

    <input
      type="file"
      ref="fileInput"
      accept=".csv,.xlsx,.xls"
      style="display:none"
      @change="handleFileSelect"
    />

    <p v-if="successMessage" class="success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
  </div>
</template>

<script>
import axios from "axios"
import { config } from "@/config"
import { CustomersImportService } from "@/services/CustomersImportService"

export default {
  name: "CustomersImport",

  data() {
    return {
      importService: new CustomersImportService(),

      miniGrids: [],
      clusters: [],

      selectedMiniGridId: "",
      selectedClusterId: "",

      selectedFile: null,
      loading: false,
      successMessage: "",
      errorMessage: "",
    }
  },

  mounted() {
    console.log("Component Mounted")
    this.fetchMiniGrids()
    this.fetchClusters()
  },

  methods: {

    // ✅ FETCH MINI GRIDS
    async fetchMiniGrids() {
      try {
        const token = localStorage.getItem("token")

        const res = await axios.get(
          config.mpmBackendUrl + "/api/mini-grids",
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          }
        )

        console.log("MINIGRIDS API:", res.data)

        this.miniGrids = res.data.data

      } catch (e) {
        console.error("Mini grids failed", e)
      }
    },

    // ✅ FETCH CLUSTERS
    async fetchClusters() {
      try {
        const token = localStorage.getItem("token")

        const res = await axios.get(
          config.mpmBackendUrl + "/api/clusters",
          {
            headers: {
              Authorization: `Bearer ${token}`,
            },
          }
        )

        console.log("CLUSTERS API:", res.data)

        this.clusters = res.data.data

      } catch (e) {
        console.error("Clusters failed", e)
      }
    },

    // ✅ AUTO SELECT CLUSTER WHEN MINI GRID CHANGES
    handleMiniGridChange() {
      const selected = this.miniGrids.find(
        g => g.id == this.selectedMiniGridId
      )

      if (selected) {
        this.selectedClusterId = selected.cluster_id
      }
    },

    selectFile() {
      this.$refs.fileInput.click()
    },

    handleFileSelect(event) {
      this.selectedFile = event.target.files[0] || null
      this.successMessage = ""
      this.errorMessage = ""
    },

    async submitUpload() {
      if (!this.selectedFile) return

      try {
        this.loading = true
        this.successMessage = ""
        this.errorMessage = ""

        await this.importService.importCustomers(
          this.selectedFile,
          this.selectedClusterId,
          this.selectedMiniGridId
        )

        this.successMessage = "Customers imported successfully"
        this.selectedFile = null

      } catch (e) {
        this.errorMessage = e.message || "Customer import failed"
      } finally {
        this.loading = false
        this.$refs.fileInput.value = ""
      }
    },
  },
}
</script>

<style scoped>
.import-export-page {
  padding: 20px;
}

.form-group {
  margin-bottom: 15px;
}

select {
  padding: 8px;
  width: 300px;
}

button {
  padding: 10px 18px;
  margin-top: 10px;
  cursor: pointer;
}

.file-name {
  margin: 10px 0;
}

.success {
  color: green;
  margin-top: 10px;
  font-weight: bold;
}

.error {
  color: red;
  margin-top: 10px;
  font-weight: bold;
}
</style>