<!-- frontend\src\modules\Bluetti\BluettiDevicePage.vue -->
<template>
  <div class="bdp-wrapper">

    <!-- ── Header ── -->
    <div class="bdp-header">
      <button class="bdp-back-btn" @click="$router.back()">
        <md-icon>arrow_back</md-icon>
        Back
      </button>

      <div class="bdp-header-info">
        <div class="bdp-title">
          <md-icon style="color:#6c2bd9; margin-right:8px">bolt</md-icon>
          {{ deviceName }}
        </div>
        <div class="bdp-sub">S/N: {{ serialNumber }}</div>
      </div>
    </div>

    <!-- ── Payment Plan Summary ── -->
    <div class="bdp-card" v-if="devicePrice">
      <div class="bdp-card-title">Payment Plan</div>
      <div class="plan-summary">
        <div class="plan-stat">
          <span class="plan-stat-label">Device Price</span>
          <span class="plan-stat-value">₦{{ Number(devicePrice).toLocaleString() }}</span>
        </div>
        <div class="plan-stat">
          <span class="plan-stat-label">Payment Plan</span>
          <span class="plan-stat-value">{{ planType }}</span>
        </div>
        <div class="plan-stat">
          <span class="plan-stat-label">Installments Paid</span>
          <span class="plan-stat-value">{{ paidInstallments }} / {{ emiMonths }}</span>
        </div>
        <div class="plan-stat">
          <span class="plan-stat-label">Remaining Balance</span>
          <span class="plan-stat-value plan-stat-remaining">₦{{ Number(remainingBalance).toLocaleString() }}</span>
        </div>
      </div>
      <div class="plan-progress-track">
        <div class="plan-progress-fill" :style="{ width: progressPercent + '%' }"></div>
      </div>
    </div>

    <!-- ── Transaction History ── -->
    <div class="bdp-card">
      <div class="bdp-card-title">Transaction History</div>

      <div v-if="loadingList" class="bdp-loading">
        Loading transactions...
      </div>

      <div v-else-if="transactions.length === 0" class="bdp-empty">
        No transactions recorded yet. Add one below.
      </div>

      <template v-else>
        <div class="txn-table-head">
          <span>Month / Year</span>
          <span>Transaction ID</span>
          <span>Token Number</span>
          <span>Status</span>
          <span>Action</span>
        </div>

        <div
          v-for="t in transactions"
          :key="t.id"
          class="txn-table-row"
        >
          <span class="txn-month-badge">
            {{ monthName(t.month) }} {{ t.year }}
          </span>

          <span class="txn-id-val">{{ t.transaction_id }}</span>
          <span class="txn-id-val">#{{ t.token }}</span>

          <span :class="t.is_active ? 'status-badge status-active' : 'status-badge status-inactive'">
            {{ t.is_active ? 'Active' : 'Inactive' }}
          </span>

          <div class="txn-row-actions">
            <button
              v-if="!t.is_active"
              class="act-btn btn-activate sm"
              :disabled="togglingId === t.id"
              @click="toggleTransaction(t, true)"
            >
              <md-icon>power_settings_new</md-icon>
              {{ togglingId === t.id ? '...' : 'Activate' }}
            </button>
            <button
              v-else
              class="act-btn btn-deactivate sm"
              :disabled="togglingId === t.id"
              @click="toggleTransaction(t, false)"
            >
              <md-icon>power_off</md-icon>
              {{ togglingId === t.id ? '...' : 'Deactivate' }}
            </button>

            <button
              class="act-btn btn-delete sm"
              :disabled="deletingId === t.id"
              @click="deleteTransaction(t)"
            >
              <md-icon>delete</md-icon>
              {{ deletingId === t.id ? '...' : 'Delete' }}
            </button>

            <button
              v-if="t.request_code_response || t.query_code_history_response"
              class="act-btn btn-view-response sm"
              @click="viewResponse(t)"
            >
              <md-icon>visibility</md-icon>
              View Response
            </button>
          </div>
        </div>
      </template>
    </div>

    <!-- ── Add / Update Transaction ── -->
    <div class="bdp-card" v-if="!isPlanComplete">
      <div class="bdp-card-title">
        {{ emiMonths === 1 ? 'Full Payment' : 'Add / Update Transaction' }}
      </div>
      <div class="txn-form-row">
        <select v-model="txnMonth" class="field-select">
          <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
        </select>
        <select v-model="txnYear" class="field-select">
          <option v-for="y in yearRange" :key="y" :value="y">{{ y }}</option>
        </select>
        <input
          v-model="txnInput"
          placeholder="Enter Transaction ID"
          class="field-input"
          @keyup.enter="saveTxn"
        />
        <button
          class="btn-save"
          :disabled="!txnInput.trim() || savingTxn"
          @click="saveTxn"
        >
          {{ savingTxn ? 'Saving...' : 'Save Transaction' }}
        </button>
      </div>
    </div>

    <div class="bdp-card" v-else>
      <div class="plan-complete-msg">
        ✅ {{ emiMonths === 1 ? 'Fully Paid' : 'EMI Plan Completed' }} — no further transactions can be added.
      </div>
    </div>

    <!-- ── API Response Modal ── -->
    <div
      v-if="showResponseModal"
      class="resp-modal-overlay"
      @click.self="showResponseModal = false; selectedResponseTxn = null"
    >
      <div class="resp-modal-box" v-if="selectedResponseTxn">
        <div class="resp-modal-head">
          <div class="resp-modal-title">
            BLUETTI API Response — {{ monthName(selectedResponseTxn.month) }} {{ selectedResponseTxn.year }}
          </div>
          <button
            class="resp-modal-close"
            @click="showResponseModal = false; selectedResponseTxn = null"
          >×</button>
        </div>

        <div class="resp-modal-body">
          <div v-if="selectedResponseTxn.request_code_response" class="resp-section">
            <div class="resp-section-label">Get Code Serial Number Response</div>
            <pre class="resp-json">{{ JSON.stringify(selectedResponseTxn.request_code_response, null, 2) }}</pre>
          </div>

          <div v-if="selectedResponseTxn.query_code_history_response" class="resp-section">
            <div class="resp-section-label">Code History Response</div>
            <pre class="resp-json">{{ JSON.stringify(selectedResponseTxn.query_code_history_response, null, 2) }}</pre>
          </div>

          <div
            v-if="!selectedResponseTxn.request_code_response && !selectedResponseTxn.query_code_history_response"
            class="resp-empty"
          >
            No API response recorded for this transaction yet.
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import BluettiDeviceRepository from "@/repositories/BluettiDeviceRepository"

export default {
  name: "BluettiDevicePage",

  data() {
    return {
      deviceId:     null,
      deviceName:   "",
      serialNumber: "",
      devicePrice:      null,
      emiMonths:        null,
      planType:         null,
      installmentAmount: null,

      transactions: [],
      loadingList:  false,
      togglingId:   null,

      txnInput:  "",
      txnMonth:  new Date().getMonth() + 1,
      txnYear:   new Date().getFullYear(),
      savingTxn: false,
      deletingId: null,

      showResponseModal: false,
      selectedResponseTxn: null,
    }
  },

  computed: {
    yearRange() {
      const y = new Date().getFullYear()
      const arr = []
      for (let i = y - 3; i <= y + 1; i++) arr.push(i)
      return arr
    },
    paidInstallments() {
      return this.transactions.length
    },
    totalPaidAmount() {
      if (!this.installmentAmount) return 0
      return this.paidInstallments * this.installmentAmount
    },
    remainingBalance() {
      if (!this.devicePrice) return 0
      const remaining = this.devicePrice - this.totalPaidAmount
      return remaining > 0 ? remaining : 0
    },
    progressPercent() {
      if (!this.emiMonths) return 0
      const pct = (this.paidInstallments / this.emiMonths) * 100
      return Math.min(pct, 100).toFixed(0)
    },
    isPlanComplete() {
      if (!this.emiMonths) return false
      return this.paidInstallments >= this.emiMonths
    },
  },

  async created() {
    this.deviceId     = Number(this.$route.params.deviceId)
    this.deviceName   = this.$route.query.device_name   || "Device"
    this.serialNumber = this.$route.query.serial_number || ""

    await this.fetchDevice()
    await this.loadTransactions()
  },

  methods: {
    async fetchDevice() {
      try {
        const { data } = await BluettiDeviceRepository.getById(this.deviceId)
        const d = data?.data ?? data
        this.deviceName        = d.device_name        || this.deviceName
        this.serialNumber      = d.serial_number       || this.serialNumber
        this.devicePrice        = d.price               ?? null
        this.emiMonths          = d.emi_months           ?? null
        this.planType            = d.plan_type           ?? null
        this.installmentAmount  = d.installment_amount   ?? null
      } catch (e) {
        console.error("fetchDevice error:", e)
      }
    },

    async loadTransactions() {
      this.loadingList = true
      try {
        const { data } = await BluettiDeviceRepository.getTransactions(this.deviceId)
        this.transactions = data?.data ?? []
      } catch (e) {
        console.error("loadTransactions error:", e)
        this.transactions = []
      } finally {
        this.loadingList = false
      }
    },

    async toggleTransaction(txn, activate) {
      this.togglingId = txn.id
      try {
        let response
        if (activate) {
          response = await BluettiDeviceRepository.activateTransaction(this.deviceId, txn.id, this.planType)
        } else {
          response = await BluettiDeviceRepository.deactivateTransaction(this.deviceId, txn.id, this.planType)
        }

        const updated = response?.data?.data ?? null
        if (updated) {
          txn.is_active                    = updated.is_active
          txn.code_serial_number           = updated.code_serial_number
          txn.token                        = updated.token
          txn.request_code_response        = updated.request_code_response
          txn.query_code_history_response  = updated.query_code_history_response
        } else {
          txn.is_active = activate
        }

        this.$swal({
          type: "success",
          title: activate ? "Transaction Activated!" : "Transaction Deactivated!",
          timer: 1200,
          showConfirmButton: false,
        })
      } catch (e) {
        console.error("toggleTransaction error:", e)
        console.error("Response data:", e?.response?.data)

        const msg = e?.response?.data?.error
                 || e?.response?.data?.message
                 || e?.message
                 || "Could not update status."

        this.$swal("Error", msg, "error")
      } finally {
        this.togglingId = null
      }
    },

    async saveTxn() {
      if (!this.txnInput.trim()) return
      this.savingTxn = true
      try {
        await BluettiDeviceRepository.upsertTransaction(this.deviceId, {
          transaction_id: this.txnInput.trim(),
          month: this.txnMonth,
          year:  this.txnYear,
        })
        await this.loadTransactions()
        this.txnInput = ""
        this.$swal({
          type: "success",
          title: "Transaction saved!",
          timer: 1200,
          showConfirmButton: false,
        })
      } catch (e) {
        console.error("saveTxn error:", e)
        const msg = e?.response?.data?.error
                 || e?.response?.data?.message
                 || e?.message
                 || "Could not save Transaction ID."
        this.$swal("Error", msg, "error")
      } finally {
        this.savingTxn = false
      }
    },

    monthName(m) {
      return new Date(2000, m - 1, 1).toLocaleString("default", { month: "short" })
    },

    async deleteTransaction(txn) {
      const ok = await this.$swal({
        title: "Delete this transaction?",
        text: `Transaction "${txn.transaction_id}" for ${this.monthName(txn.month)} ${txn.year} will be permanently removed.`,
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, delete",
      })
      if (!ok) return

      this.deletingId = txn.id
      try {
        await BluettiDeviceRepository.deleteTransaction(this.deviceId, txn.id)
        await this.loadTransactions()
        await this.fetchDevice()
        this.$swal({
          type: "success",
          title: "Transaction deleted",
          timer: 1200,
          showConfirmButton: false,
        })
      } catch (e) {
        const msg = e?.response?.data?.error || "Could not delete transaction."
        this.$swal("Error", msg, "error")
      } finally {
        this.deletingId = null
      }
    },

    // ✅ Fixed — this now lives in methods (was incorrectly under computed before)
    viewResponse(txn) {
      this.selectedResponseTxn = txn
      this.showResponseModal = true
    },
  },
}
</script>

<style scoped>
.bdp-wrapper {
  max-width: 920px;
  margin: 32px auto;
  padding: 0 20px;
}

.bdp-header {
  display: flex;
  align-items: center;
  gap: 16px;
  margin-bottom: 28px;
  flex-wrap: wrap;
}

.bdp-back-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  background: #f5f5f5;
  border: none;
  border-radius: 8px;
  padding: 8px 16px;
  cursor: pointer;
  font-size: 14px;
  color: #444;
  flex-shrink: 0;
  transition: background 0.15s;
}
.bdp-back-btn:hover { background: #e0e0e0; }

.bdp-header-info { flex: 1; }
.bdp-title {
  font-size: 22px;
  font-weight: 700;
  color: #1a1a2e;
  display: flex;
  align-items: center;
}
.bdp-sub { font-size: 13px; color: #888; margin-top: 3px; }

.bdp-card {
  background: #fff;
  border-radius: 14px;
  box-shadow: 0 2px 16px rgba(0, 0, 0, 0.07);
  padding: 24px;
  margin-bottom: 20px;
}
.bdp-card-title {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #9e9e9e;
  margin-bottom: 18px;
}

.bdp-loading,
.bdp-empty {
  text-align: center;
  padding: 28px;
  color: #bbb;
  font-size: 14px;
}

.txn-table-head {
  display: grid;
  grid-template-columns: 0.8fr 1fr 1fr 0.8fr 2fr;  
  gap: 12px;
  padding: 8px 14px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #bbb;
  border-bottom: 2px solid #f0f0f0;
  margin-bottom: 4px;
}

.txn-table-row {
  display: grid;
  grid-template-columns: 0.8fr 1fr 1fr 0.8fr 2fr; 
  gap: 12px;
  align-items: center;
  padding: 14px;
  border-bottom: 1px solid #f5f5f5;
  transition: background 0.12s;
}
.txn-table-row:last-child { border-bottom: none; }
.txn-table-row:hover { background: #fafafa; }

.txn-month-badge {
  background: #6c2bd9;
  color: #fff;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 700;
  text-align: center;
  white-space: nowrap;
  display: inline-block;
}

.txn-id-val {
  font-size: 14px;
  color: #2d2d2d;
  font-weight: 500;
  word-break: break-all;
}

.status-badge {
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 700;
  text-align: center;
  white-space: nowrap;
  display: inline-block;
}
.status-badge.status-active   { background: #e8f5e9; color: #2e7d32; }
.status-badge.status-inactive { background: #fce4ec; color: #c62828; }

.txn-row-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
  flex-wrap: wrap;              
}

.act-btn {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 11px;               
  font-weight: 600;
  padding: 5px 9px;               
  transition: background 0.2s, opacity 0.2s;
  white-space: nowrap;
}
.act-btn .md-icon {
  font-size: 15px !important;     
  width: 15px !important;
  height: 15px !important;
  margin: 0 !important;
}
.act-btn.sm { padding: 5px 9px; font-size: 11px; }
.act-btn:disabled { opacity: 0.5; cursor: default; }
.act-btn.btn-activate {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #a5d6a7;
}
.act-btn.btn-activate:hover:not(:disabled) { background: #c8e6c9; }

.act-btn.btn-deactivate {
  background: #fce4ec;
  color: #c62828;
  border: 1px solid #ef9a9a;
}
.act-btn.btn-deactivate:hover:not(:disabled) { background: #ffcdd2; }

.txn-form-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  align-items: center;
}

.field-select {
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  background: #fff;
  min-width: 110px;
  transition: border-color 0.2s;
}
.field-select:focus { border-color: #6c2bd9; }

.field-input {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  transition: border-color 0.2s;
}
.field-input:focus { border-color: #6c2bd9; }

.btn-save {
  background: #6c2bd9;
  color: #fff;
  border: none;
  padding: 10px 24px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  transition: background 0.2s, opacity 0.2s;
}
.btn-save:hover:not(:disabled) { background: #5a23b8; }
.btn-save:disabled { opacity: 0.5; cursor: default; }

.plan-summary {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 14px;
}
.plan-stat { display: flex; flex-direction: column; gap: 4px; }
.plan-stat-label {
  font-size: 11px;
  color: #9e9e9e;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.plan-stat-value { font-size: 16px; font-weight: 700; color: #1a1a2e; }
.plan-stat-remaining { color: #c62828; }

.plan-progress-track {
  height: 8px;
  background: #f0f0f0;
  border-radius: 4px;
  overflow: hidden;
}
.plan-progress-fill {
  height: 100%;
  background: #6c2bd9;
  transition: width 0.3s ease;
}

.plan-complete-msg {
  background: #e8f5e9;
  color: #2e7d32;
  border: 1px solid #a5d6a7;
  padding: 14px 16px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  text-align: center;
}

.act-btn.btn-delete {
  background: #fce4ec;
  color: #c62828;
  border: 1px solid #ef9a9a;
}
.act-btn.btn-delete:hover:not(:disabled) { background: #ffcdd2; }

.act-btn.btn-view-response {
  background: #e3f2fd;
  color: #0d47a1;
  border: 1px solid #90caf9;
}
.act-btn.btn-view-response:hover:not(:disabled) { background: #bbdefb; }

.resp-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1100;
  padding: 16px;
}

.resp-modal-box {
  background: #fff;
  width: 100%;
  max-width: 640px;
  max-height: 85vh;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.resp-modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 18px 22px;
  border-bottom: 1px solid #f0f0f0;
  position: sticky;
  top: 0;
  background: #fff;
}
.resp-modal-title { font-size: 15px; font-weight: 700; color: #1a1a2e; }
.resp-modal-close {
  background: none;
  border: none;
  font-size: 24px;
  cursor: pointer;
  color: #aaa;
}
.resp-modal-close:hover { color: #333; }

.resp-modal-body { padding: 18px 22px; }
.resp-section { margin-bottom: 20px; }
.resp-section:last-child { margin-bottom: 0; }
.resp-section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #9e9e9e;
  margin-bottom: 8px;
}
.resp-json {
  background: #1a1a2e;
  color: #a5d6ff;
  padding: 14px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.5;
  overflow-x: auto;
  white-space: pre-wrap;
  word-break: break-all;
  margin: 0;
}
.resp-empty {
  text-align: center;
  padding: 24px;
  color: #bbb;
  font-size: 14px;
}
</style>