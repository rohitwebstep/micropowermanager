<template>
  <div class="import-export-page">
    <h2>Meter Import</h2>

    <!-- Select File -->
    <button @click="selectFile" :disabled="loading">
      Select File
    </button>

    <!-- Selected File -->
    <p v-if="selectedFile" class="file-name">
      Selected file: <strong>{{ selectedFile.name }}</strong>
    </p>

    <!-- Upload -->
    <button
      @click="submitUpload"
      :disabled="!selectedFile || loading"
    >
      {{ loading ? "Importing..." : "Upload & Import Meters" }}
    </button>

    <!-- Hidden Input -->
    <input
      type="file"
      ref="fileInput"
      accept=".csv,.xlsx,.xls"
      style="display: none"
      @change="handleFileSelect"
    />

    <!-- Messages -->
    <p v-if="successMessage" class="success">{{ successMessage }}</p>
    <p v-if="errorMessage" class="error">{{ errorMessage }}</p>
  </div>
</template>

<script>
import { MeterImportService } from "@/services/MeterImportService"

export default {
  name: "MeterImport",

  data() {
    return {
      importService: new MeterImportService(),
      loading: false,
      selectedFile: null,
      successMessage: "",
      errorMessage: "",
    }
  },

  methods: {
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

        await this.importService.importMeters(this.selectedFile)

        this.successMessage = "Meters imported successfully"
        this.selectedFile = null
      } catch (e) {
        this.errorMessage = e.message || "Meter import failed"
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

button {
  padding: 10px 18px;
  margin-right: 10px;
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
