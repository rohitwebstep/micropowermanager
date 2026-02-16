<template>
  <div class="import-export-page">
    <h2>Mini Grid Import</h2>

    <!-- Download Sample File -->
    <div class="sample-section">
      <button class="sample-btn" @click="downloadSample">
        Download Sample XLS
      </button>
    </div>

    <!-- Select File Button -->
    <button @click="selectFile" :disabled="loading">
      Select File
    </button>

    <!-- Selected file name -->
    <p v-if="selectedFile" class="file-name">
      Selected file: <strong>{{ selectedFile.name }}</strong>
    </p>

    <!-- Upload Button -->
    <button
      @click="submitUpload"
      :disabled="!selectedFile || loading"
    >
      {{ loading ? "Importing..." : "Upload & Import" }}
    </button>

    <!-- Hidden File Input -->
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
import { MiniGridImportService } from "@/services/MiniGridImportService"

export default {
  name: "MiniGridImport",

  data() {
    return {
      importService: new MiniGridImportService(),
      loading: false,
      selectedFile: null,
      successMessage: "",
      errorMessage: "",
    }
  },

  methods: {
    // Open file picker
    selectFile() {
      this.$refs.fileInput.click()
    },

    // Handle file select
    handleFileSelect(event) {
      this.selectedFile = event.target.files[0] || null
      this.successMessage = ""
      this.errorMessage = ""
    },

    // Download sample XLS
    downloadSample() {
      const link = document.createElement("a")
      link.href = "/files/mini-grid-sample.xls"
      link.download = "mini-grid-sample.xls"
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
    },

    // Upload file
    async submitUpload() {
      if (!this.selectedFile) return

      try {
        this.loading = true
        this.successMessage = ""
        this.errorMessage = ""

        await this.importService.importMiniGrid(this.selectedFile)

        this.successMessage = "Mini Grid imported successfully"
        this.selectedFile = null
      } catch (e) {
        this.errorMessage = e.message || "Import failed"
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

.sample-btn {
  background: #4caf50;
  color: #fff;
  border: none;
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
