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
          </div>
        </div>
      </template>
    </div>

    <!-- ── Add / Update Transaction ── -->
    <div class="bdp-card">
      <div class="bdp-card-title">Add / Update Transaction</div>
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

      transactions: [],
      loadingList:  false,
      togglingId:   null,

      txnInput:  "",
      txnMonth:  new Date().getMonth() + 1,
      txnYear:   new Date().getFullYear(),
      savingTxn: false,
    }
  },

  computed: {
    yearRange() {
      const y = new Date().getFullYear()
      const arr = []
      for (let i = y - 3; i <= y + 1; i++) arr.push(i)
      return arr
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
        this.deviceName   = d.device_name   || this.deviceName
        this.serialNumber = d.serial_number || this.serialNumber
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
          response = await BluettiDeviceRepository.activateTransaction(this.deviceId, txn.id)
        } else {
          response = await BluettiDeviceRepository.deactivateTransaction(this.deviceId, txn.id)
        }

        // DB se fresh data local mein update karo
        const updated = response?.data?.data ?? null
        if (updated) {
          txn.is_active          = updated.is_active
          txn.code_serial_number = updated.code_serial_number
          txn.token              = updated.token
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
  },
}
</script>

<style scoped>
.bdp-wrapper {
  max-width: 920px;
  margin: 32px auto;
  padding: 0 20px;
}

/* Header */
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

/* Cards */
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

/* Transaction Table */
.txn-table-head {
  display: grid;
  grid-template-columns: 130px 1fr 90px auto;
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
  grid-template-columns: 130px 1fr 90px auto;
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

/* Status badges */
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

/* Action buttons */
.txn-row-actions {
  display: flex;
  gap: 6px;
  flex-shrink: 0;
}

.act-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: none;
  border-radius: 7px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  padding: 6px 12px;
  transition: background 0.2s, opacity 0.2s;
  white-space: nowrap;
}
.act-btn.sm { padding: 5px 10px; font-size: 12px; }
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

/* Add form */
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
</style>