<!-- micropowermanager-main\frontend\src\modules\Bluetti\BluettiUserList.vue -->
<template>
  <div>
    <widget
      :id="'bluetti-user-list-widget'"
      :title="'BLUETTI Users'"
      :search="true"
      :subscriber="subscriber"
      :button="false"
      :paginator="paginator"
      :show_per_page="true"
      :route_name="'/dashboards/bluetti/users'"
      color="primary"
    >
      <div class="md-layout md-gutter">

        <!-- TABLE -->
        <div class="md-layout-item md-size-100" v-if="people.list.length > 0">
          <md-table
            v-model="people.list"
            md-card
            style="margin-left: 0"
            md-sort="created_at"
            md-sort-order="desc"
          >
            <md-table-row
              slot="md-table-row"
              slot-scope="{ item }"
              style="cursor: default"
            >
              <md-table-cell :md-label="$tc('words.name')" md-sort-by="name">
                {{ item.name }} {{ item.surname }}
              </md-table-cell>

              <md-table-cell :md-label="$tc('words.phone')">
                {{ item.addresses.length > 0 ? item.addresses[0].phone : "-" }}
              </md-table-cell>

              <md-table-cell md-label="NIN">
                {{ item.nin || "-" }}
              </md-table-cell>

              <md-table-cell :md-label="$tc('words.city')" class="hidden-xs">
                {{
                  item.addresses.length > 0 && item.addresses[0].city
                    ? item.addresses[0].city.name
                    : "-"
                }}
              </md-table-cell>

              <md-table-cell :md-label="$tc('words.isActive')">
                <span :class="item.is_active ? 'badge-active' : 'badge-inactive'">
                  {{ item.is_active ? $tc("words.yes") : $tc("words.no") }}
                </span>
              </md-table-cell>

              <!-- BLUETTI Devices column -->
              <md-table-cell md-label="BLUETTI Devices" style="min-width: 320px">
                <span v-if="!getAssignedDevices(item.id).length" class="no-device">—</span>
                <div v-else class="device-list">
                  <div
                    v-for="d in getAssignedDevices(item.id)"
                    :key="d.id"
                    class="device-row"
                  >
                    <!-- Device name + SN -->
                    <span class="device-name-chip" @click="showDeviceDetail(d)">
                      {{ d.device_name }}
                      <span class="chip-sn">{{ d.serial_number }}</span>
                    </span>

                    <!-- Transaction ID — latest badge ya button -->
                    <template v-if="getLatestTxn(d)">
                      <span
                        class="badge-txn"
                        @click="openTxnModal(d)"
                        title="Click to manage Transactions"
                      >
                        TXN {{ monthName(getLatestTxn(d).month) }}/{{ getLatestTxn(d).year }}:
                        {{ getLatestTxn(d).transaction_id }}
                      </span>
                    </template>
                    <button
                      v-else
                      class="chip-action-btn txn-btn"
                      @click.stop="openTxnModal(d)"
                    >
                      + Assign TXN
                    </button>

                    <!-- Customer No -->
                    <!--  <span
                      v-if="d.customer_no"
                      class="badge-cno"
                      @click="openCnoModal(d)"
                      title="Click to edit Customer No"
                    >
                      #{{ d.customer_no }}
                    </span>
                    <button
                      v-else
                      class="chip-action-btn cno-btn"
                      @click.stop="openCnoModal(d)"
                    >
                      + Customer No
                    </button> -->
                  </div>
                </div>
              </md-table-cell>

              <md-table-cell md-label="Action">
                <button class="assign-btn" @click.stop="openAssignModal(item)">
                  <md-icon style="font-size: 16px; margin-right: 4px">bolt</md-icon>
                  Assign Device
                </button>
              </md-table-cell>
            </md-table-row>
          </md-table>
        </div>

        <!-- EMPTY STATE -->
        <div
          class="md-layout-item md-size-100"
          v-if="!loading && people.list.length === 0"
        >
          <div class="empty-state">
            <md-icon style="font-size: 48px; color: #ccc">people</md-icon>
            <div>No customers found.</div>
          </div>
        </div>

      </div>
    </widget>

    <!-- ====================================================
         ASSIGN DEVICE MODAL
    ==================================================== -->
    <div v-if="showAssignModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-box">

        <div class="modal-head">
          <div>
            <div class="modal-title">Assign BLUETTI Device</div>
            <div class="modal-sub" v-if="selectedCustomer">
              Customer: <b>{{ selectedCustomer.name }} {{ selectedCustomer.surname }}</b>
            </div>
          </div>
          <button class="modal-close" @click="closeModal">×</button>
        </div>

        <!-- CURRENTLY ASSIGNED -->
        <div
          class="modal-section"
          v-if="selectedCustomer && getAssignedDevices(selectedCustomer.id).length > 0"
        >
          <div class="section-label">Currently Assigned</div>
          <div class="assigned-list">
            <div
              v-for="d in getAssignedDevices(selectedCustomer.id)"
              :key="d.id"
              class="assigned-item"
            >
              <div class="assigned-info">
                <b>{{ d.device_name }}</b>
                <span>{{ d.serial_number }}</span>
              </div>
              <button
                class="unassign-btn"
                :disabled="unassigningId === d.id"
                @click="unassignDevice(d)"
              >
                {{ unassigningId === d.id ? "..." : "Unassign" }}
              </button>
            </div>
          </div>
        </div>

        <!-- FREE DEVICES -->
        <div class="modal-section">
          <div class="section-label">Available Devices (unassigned)</div>
          <div v-if="loadingFreeDevices" class="loading-msg">Loading devices...</div>
          <div v-else-if="freeDevices.length === 0" class="empty-msg">
            No unassigned devices available. Add a new device first.
          </div>
          <div v-else class="device-grid">
            <div
              v-for="d in freeDevices"
              :key="d.id"
              class="device-card"
              :class="{ 'device-card--selected': selectedDeviceId === d.id }"
              @click="selectedDeviceId = d.id"
            >
              <div class="device-card-icon"><md-icon>bolt</md-icon></div>
              <div class="device-card-info">
                <div class="device-card-name">{{ d.device_name }}</div>
                <div class="device-card-sn">S/N: {{ d.serial_number }}</div>
                <div class="device-card-style">{{ d.style }}</div>
              </div>
              <div class="device-card-check" v-if="selectedDeviceId === d.id">
                <md-icon style="color: #6c2bd9">check_circle</md-icon>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-cancel" @click="closeModal">Cancel</button>
          <button
            class="btn-assign"
            :disabled="!selectedDeviceId || assigning"
            @click="confirmAssign"
          >
            <span v-if="assigning">Assigning...</span>
            <span v-else>Assign Device</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ====================================================
         DEVICE DETAIL MODAL
    ==================================================== -->
    <div v-if="deviceDetailModal" class="modal-overlay" @click.self="deviceDetailModal=false">
      <div class="modal-box" style="max-width:400px">
        <div class="modal-head">
          <div class="modal-title">Device Details</div>
          <button class="modal-close" @click="deviceDetailModal=false">×</button>
        </div>
        <div class="modal-section" v-if="selectedDeviceDetail">
          <p><b>Device Name:</b> {{ selectedDeviceDetail.device_name }}</p>
          <p><b>S/N:</b> {{ selectedDeviceDetail.serial_number }}</p>
          <p><b>Client:</b> {{ selectedDeviceDetail.client }}</p>
          <p><b>Style:</b> {{ selectedDeviceDetail.style }}</p>
          <p><b>Created:</b> {{ selectedDeviceDetail.created_date }}</p>
          <p><b>Customer No:</b> {{ selectedDeviceDetail.customer_no || '—' }}</p>
        </div>
      </div>
    </div>

    <!-- ====================================================
         TRANSACTION ID MODAL — Monthly
    ==================================================== -->
    <div v-if="showTxnModal" class="modal-overlay" @click.self="showTxnModal=false">
      <div class="modal-box" style="max-width:500px">
        <div class="modal-head">
          <div class="modal-title">Transaction History</div>
          <button class="modal-close" @click="showTxnModal=false">×</button>
        </div>

        <div class="modal-section" v-if="txnDevice">
          <!-- Device info -->
          <p class="info-line">
            Device: <b>{{ txnDevice.device_name }}</b> &nbsp;|&nbsp;
            S/N: <b>{{ txnDevice.serial_number }}</b>
          </p>

          <!-- Transaction history list -->
          <div class="section-label">Past Transactions</div>
          <div v-if="txnLoadingList" class="loading-msg">Loading...</div>
          <div v-else-if="txnTransactions.length === 0" class="empty-msg">
            No transactions recorded yet.
          </div>
          <div v-else class="txn-history-list">
            <div
              v-for="t in txnTransactions"
              :key="t.id"
              class="txn-history-row"
            >
              <span class="txn-month-badge">
                {{ monthName(t.month) }} {{ t.year }}
              </span>
              <span class="txn-id-val">{{ t.transaction_id }}</span>
            </div>
          </div>

          <!-- Add / Update section -->
          <div class="section-label" style="margin-top: 20px">
            Add / Update Transaction
          </div>

          <!-- Month + Year selectors -->
          <div class="txn-date-row">
            <select v-model="txnMonth" class="field-select">
              <option v-for="m in 12" :key="m" :value="m">
                {{ monthName(m) }}
              </option>
            </select>
            <select v-model="txnYear" class="field-select">
              <option v-for="y in yearRange" :key="y" :value="y">{{ y }}</option>
            </select>
          </div>

          <!-- Transaction ID input -->
          <input
            v-model="txnInput"
            placeholder="Enter Transaction ID"
            class="field-input"
            style="margin-top: 10px"
            @keyup.enter="saveTxn"
          />

          <div class="modal-footer" style="border: none; padding: 16px 0 0">
            <button class="btn-cancel" @click="showTxnModal=false">Cancel</button>
            <button
              class="btn-assign"
              :disabled="!txnInput.trim() || savingTxn"
              @click="saveTxn"
            >
              {{ savingTxn ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ====================================================
         CUSTOMER NO MODAL
    ==================================================== -->
    <div v-if="showCnoModal" class="modal-overlay" @click.self="showCnoModal=false">
      <div class="modal-box" style="max-width:440px">
        <div class="modal-head">
          <div class="modal-title">Assign Customer No</div>
          <button class="modal-close" @click="showCnoModal=false">×</button>
        </div>
        <div class="modal-section" v-if="cnoDevice">
          <p class="info-line">
            Device: <b>{{ cnoDevice.device_name }}</b> &nbsp;|&nbsp;
            S/N: <b>{{ cnoDevice.serial_number }}</b>
          </p>
          <div v-if="cnoDevice.customer_no" class="current-val current-cno">
            Current: <b>{{ cnoDevice.customer_no }}</b>
          </div>
          <input
            v-model="cnoInput"
            placeholder="Enter Customer No"
            class="field-input"
            @keyup.enter="saveCno"
          />
          <div class="modal-footer" style="border:none; padding: 16px 0 0">
            <button class="btn-cancel" @click="showCnoModal=false">Cancel</button>
            <button
              class="btn-assign cno-save"
              :disabled="!cnoInput.trim() || savingCno"
              @click="saveCno"
            >
              {{ savingCno ? 'Saving...' : 'Save' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

<script>
import { resources } from "@/resources"
import { Paginator } from "@/Helpers/Paginator"
import { EventBus } from "@/shared/eventbus"
import Widget from "@/shared/Widget.vue"
import { People } from "@/services/PersonService"
import { timing } from "@/mixins/timing"
import { notify } from "@/mixins/notify"
import BluettiDeviceRepository from "@/repositories/BluettiDeviceRepository"

export default {
  name: "BluettiUserList",
  mixins: [timing, notify],
  components: { Widget },

  data() {
    return {
      subscriber: "bluetti.user.list",
      people: new People(),
      paginator: new Paginator(resources.person.list),
      loading: false,

      // Assign device modal
      showAssignModal: false,
      selectedCustomer: null,
      selectedDeviceId: null,
      freeDevices: [],
      loadingFreeDevices: false,
      assigning: false,
      unassigningId: null,

      // Device detail modal
      deviceDetailModal: false,
      selectedDeviceDetail: null,

      // Transaction ID modal — monthly
      showTxnModal: false,
      txnDevice: null,
      txnTransactions: [],
      txnLoadingList: false,
      txnInput: "",
      txnMonth: new Date().getMonth() + 1,
      txnYear: new Date().getFullYear(),
      savingTxn: false,

      // Customer No modal
      showCnoModal: false,
      cnoDevice: null,
      cnoInput: "",
      savingCno: false,

      // customerId -> [devices] cache
      assignedDevicesMap: {},
    }
  },

  computed: {
    yearRange() {
      const currentYear = new Date().getFullYear()
      const years = []
      for (let y = currentYear - 3; y <= currentYear + 1; y++) {
        years.push(y)
      }
      return years
    },
  },

  mounted() {
    this.getClientList()
    EventBus.$on("pageLoaded", this.reloadList)
    EventBus.$on("searching", this.onSearchEvent)
    EventBus.$on("end_searching", this.onEndSearchEvent)
  },

  beforeDestroy() {
    EventBus.$off("pageLoaded", this.reloadList)
    EventBus.$off("searching", this.onSearchEvent)
    EventBus.$off("end_searching", this.onEndSearchEvent)
  },

  methods: {

    // ─── LIST ─────────────────────────────────────────────────────────────────
    async getClientList(pageNumber = 1) {
      this.loading = true
      try {
        const response = await this.paginator.loadPage(pageNumber)
        this.people.updateList(response.data)
        EventBus.$emit("widgetContentLoaded", this.subscriber, this.people.list.length)
        this.prefetchAssignedDevices(this.people.list)
      } catch (e) {
        console.error("Error loading client list:", e)
      } finally {
        this.loading = false
      }
    },

    reloadList(subscriber, data) {
      if (subscriber !== this.subscriber) return
      this.people.updateList(data)
      EventBus.$emit("widgetContentLoaded", this.subscriber, this.people.list.length)
      this.prefetchAssignedDevices(this.people.list)
    },

    onSearchEvent() {},

    onEndSearchEvent() {
      this.paginator = new Paginator(resources.person.list)
      this.getClientList()
    },

    // ─── ASSIGNED DEVICES CACHE ───────────────────────────────────────────────
    async prefetchAssignedDevices(customers) {
      await Promise.allSettled(
        customers.map((c) => this.fetchAssignedForCustomer(c.id))
      )
    },

    async fetchAssignedForCustomer(customerId) {
      try {
        const { data } = await BluettiDeviceRepository.byCustomer(customerId)
        let devices = data?.data ?? data ?? []
        if (!Array.isArray(devices)) devices = [devices]
        this.$set(this.assignedDevicesMap, customerId, devices)
      } catch (e) {
        this.$set(this.assignedDevicesMap, customerId, [])
      }
    },

    getAssignedDevices(customerId) {
      return this.assignedDevicesMap[customerId] || []
    },

    /**
     * Device ki latest transaction return karo
     * Backend already desc sorted (year desc, month desc) return karta hai
     * Agar koi bhi transaction hai toh badge show hoga
     */
    getLatestTxn(device) {
      const transactions = device.transactions
      if (!transactions || !transactions.length) return null
      return transactions[0]
    },

    // ─── DEVICE DETAIL ────────────────────────────────────────────────────────
    showDeviceDetail(device) {
      this.selectedDeviceDetail = device
      this.deviceDetailModal = true
    },

    // ─── ASSIGN DEVICE MODAL ──────────────────────────────────────────────────
    async openAssignModal(customer) {
      this.selectedCustomer = customer
      this.selectedDeviceId = null
      this.showAssignModal = true
      await this.loadFreeDevices()
    },

    closeModal() {
      this.showAssignModal = false
      this.selectedCustomer = null
      this.selectedDeviceId = null
      this.freeDevices = []
    },

    async loadFreeDevices() {
      this.loadingFreeDevices = true
      try {
        const { data } = await BluettiDeviceRepository.getList(1, {})
        const all = data?.data?.data ?? data?.data ?? []
        this.freeDevices = all.filter((d) => !d.customer_id)
      } catch (e) {
        this.freeDevices = []
      } finally {
        this.loadingFreeDevices = false
      }
    },

    async confirmAssign() {
      if (!this.selectedDeviceId || !this.selectedCustomer) return
      this.assigning = true
      try {
        await BluettiDeviceRepository.assignCustomer(
          this.selectedDeviceId,
          this.selectedCustomer.id
        )
        await this.fetchAssignedForCustomer(this.selectedCustomer.id)
        this.$swal({
          type: "success",
          title: "Device assigned successfully!",
          timer: 1500,
          showConfirmButton: false,
        })
        this.closeModal()
      } catch (e) {
        this.$swal("Error", "Could not assign device. Please try again.", "error")
      } finally {
        this.assigning = false
      }
    },

    async unassignDevice(device) {
      const ok = await this.$swal({
        title: "Unassign device?",
        text: `"${device.device_name}" will be unlinked from this customer.`,
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#d33",
        confirmButtonText: "Yes, unassign",
      })
      if (!ok) return
      this.unassigningId = device.id
      try {
        await BluettiDeviceRepository.unassignCustomer(device.id)
        await this.fetchAssignedForCustomer(this.selectedCustomer.id)
        await this.loadFreeDevices()
        this.$swal({
          type: "success",
          title: "Unassigned",
          timer: 1200,
          showConfirmButton: false,
        })
      } catch (e) {
        this.$swal("Error", "Could not unassign device.", "error")
      } finally {
        this.unassigningId = null
      }
    },

    // ─── TRANSACTION ID — MONTHLY ─────────────────────────────────────────────
    openTxnModal(device) {
  this.$router.push({
    name: 'BluettiDevicePage',
    params: { deviceId: device.id },
    query: {
      device_name:   device.device_name,
      serial_number: device.serial_number,
      customer_id:   device.customer_id,
    },
  })
},

    async loadTxnList(deviceId) {
      this.txnLoadingList = true
      try {
        const { data } = await BluettiDeviceRepository.getTransactions(deviceId)
        this.txnTransactions = data?.data ?? []
      } catch {
        this.txnTransactions = []
      } finally {
        this.txnLoadingList = false
      }
    },

    async saveTxn() {
      if (!this.txnInput.trim()) return
      this.savingTxn = true
      try {
        await BluettiDeviceRepository.upsertTransaction(this.txnDevice.id, {
          transaction_id: this.txnInput.trim(),
          month: this.txnMonth,
          year:  this.txnYear,
        })
        // Modal list refresh
        await this.loadTxnList(this.txnDevice.id)
        // Table badge refresh — customer ke devices dobara fetch
        const customerId = this.txnDevice.customer_id
        if (customerId) await this.fetchAssignedForCustomer(customerId)
        this.txnInput = ""
        this.$swal({
          type: "success",
          title: "Transaction saved!",
          timer: 1200,
          showConfirmButton: false,
        })
      } catch (e) {
        this.$swal("Error", "Could not save Transaction ID", "error")
      } finally {
        this.savingTxn = false
      }
    },

    // ─── CUSTOMER NO ──────────────────────────────────────────────────────────
    openCnoModal(device) {
      this.cnoDevice = device
      this.cnoInput  = device.customer_no || ""
      this.showCnoModal = true
    },

    async saveCno() {
      if (!this.cnoInput.trim()) return
      this.savingCno = true
      try {
        await BluettiDeviceRepository.assignCustomerNo(
          this.cnoDevice.id,
          this.cnoInput.trim()
        )
        const customerId = this.cnoDevice.customer_id
        if (customerId) await this.fetchAssignedForCustomer(customerId)
        this.$swal({
          type: "success",
          title: "Customer No saved!",
          timer: 1200,
          showConfirmButton: false,
        })
        this.showCnoModal = false
      } catch (e) {
        this.$swal("Error", "Could not save Customer No", "error")
      } finally {
        this.savingCno = false
      }
    },

    // ─── HELPERS ──────────────────────────────────────────────────────────────
    monthName(m) {
      return new Date(2000, m - 1, 1).toLocaleString("default", { month: "short" })
    },
  },
}
</script>

<style scoped>
.badge-active,
.badge-inactive {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 12px;
  font-size: 12px;
  font-weight: 600;
}
.badge-active   { background: #e8f5e9; color: #2e7d32; }
.badge-inactive { background: #fce4ec; color: #c62828; }

/* ── Device list inside cell ── */
.device-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.device-row {
  display: block;
  align-items: center;
  flex-wrap: wrap;
  gap: 6px;
}

.device-name-chip {
  display: block;
  align-items: center;
  gap: 5px;
  background: #ede7f6;
  color: #4a148c;
  padding: 3px 10px;
  border-radius: 12px;
  font-size: 12px;
  cursor: pointer;
  font-weight: 600;
  transition: background 0.15s;
  margin-bottom: 5px;
}
.device-name-chip:hover { background: #d1c4e9; }

.chip-sn { color: #7b1fa2; font-size: 11px; }

.no-device { color: #bbb; }

/* TXN / CNo badges (already assigned) */
.badge-txn {
  display: inline-block;
  background: #f3eeff;
  color: #6c2bd9;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid #d1b3ff;
  transition: background 0.15s;
  margin-right: 5px;
}
.badge-txn:hover { background: #e0d0ff; }

.badge-cno {
  display: inline-block;
  background: #e3f2fd;
  color: #0d47a1;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  border: 1px solid #90caf9;
  transition: background 0.15s;
}
.badge-cno:hover { background: #bbdefb; }

/* TXN / CNo buttons (not yet assigned) */
.chip-action-btn {
  border: none;
  padding: 3px 9px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 11px;
  font-weight: 600;
  transition: background 0.15s;
}
.txn-btn {
  background: #ede7f6;
  color: #4a148c;
  border: 1px dashed #ce93d8;
  margin-right: 5px;
}
.txn-btn:hover { background: #d1c4e9; }

.cno-btn {
  background: #e3f2fd;
  color: #0d47a1;
  border: 1px dashed #90caf9;
}
.cno-btn:hover { background: #bbdefb; }

/* Assign Device button */
.assign-btn {
  display: inline-flex;
  align-items: center;
  background: #6c2bd9;
  color: #fff;
  border: none;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  transition: background 0.2s;
  white-space: nowrap;
}
.assign-btn:hover { background: #5a23b8; }

.empty-state {
  text-align: center;
  padding: 3rem;
  background: #f5f5f5;
  border-radius: 8px;
  margin: 1rem 0;
  color: #666;
}

/* ── Modals ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal-box {
  background: #fff;
  width: 100%;
  max-width: 560px;
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  max-height: 90vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
}
.modal-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.modal-sub   { font-size: 13px; color: #888; margin-top: 3px; }
.modal-close {
  background: none;
  border: none;
  font-size: 26px;
  cursor: pointer;
  color: #aaa;
  line-height: 1;
  padding: 0 4px;
  transition: color 0.15s;
}
.modal-close:hover { color: #333; }

.modal-section { padding: 16px 24px; }
.section-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #9e9e9e;
  margin-bottom: 12px;
}

.info-line {
  font-size: 13px;
  color: #666;
  padding: 8px 12px;
  background: #f9f9f9;
  border-radius: 6px;
  margin-bottom: 14px;
}

.current-val {
  margin-bottom: 12px;
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 13px;
}
.current-cno { background: #e3f2fd; color: #0d47a1; }

.field-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  box-sizing: border-box;
  outline: none;
  transition: border-color 0.2s;
}
.field-input:focus { border-color: #6c2bd9; }

/* ── Transaction history list ── */
.txn-history-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 200px;
  overflow-y: auto;
  margin-bottom: 4px;
  padding-right: 2px;
}

.txn-history-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: #f9f5ff;
  border-radius: 7px;
  border: 1px solid #e9d8fd;
}

.txn-month-badge {
  background: #6c2bd9;
  color: #fff;
  padding: 2px 10px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 700;
  white-space: nowrap;
  min-width: 72px;
  text-align: center;
  flex-shrink: 0;
}

.txn-id-val {
  font-size: 13px;
  color: #2d2d2d;
  font-weight: 500;
  word-break: break-all;
}

/* ── Month/Year picker row ── */
.txn-date-row {
  display: flex;
  gap: 10px;
  margin-top: 4px;
}

.field-select {
  flex: 1;
  padding: 9px 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
  background: #fff;
  transition: border-color 0.2s;
}
.field-select:focus { border-color: #6c2bd9; }

/* ── Assigned devices in modal ── */
.assigned-list { display: flex; flex-direction: column; gap: 8px; }
.assigned-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #f3eeff;
  border: 1px solid #d1b3ff;
  padding: 10px 14px;
  border-radius: 8px;
}
.assigned-info { display: flex; flex-direction: column; gap: 2px; }
.assigned-info b    { font-size: 14px; color: #1a1a2e; }
.assigned-info span { font-size: 12px; color: #7b1fa2; }

.unassign-btn {
  background: #fff;
  border: 1px solid #e53935;
  color: #e53935;
  padding: 5px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: background 0.15s, color 0.15s;
}
.unassign-btn:hover:not(:disabled) { background: #e53935; color: #fff; }
.unassign-btn:disabled { opacity: 0.5; cursor: default; }

/* ── Free device grid in assign modal ── */
.device-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 260px;
  overflow-y: auto;
  padding-right: 4px;
}

.device-card {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border: 2px solid #eee;
  border-radius: 10px;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.device-card:hover      { border-color: #c4a0ff; background: #faf5ff; }
.device-card--selected  { border-color: #6c2bd9; background: #f3eeff; }

.device-card-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: #ede7f6;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.device-card-icon .md-icon { color: #6c2bd9; font-size: 20px; }
.device-card-info  { flex: 1; min-width: 0; }
.device-card-name  { font-size: 14px; font-weight: 600; color: #1a1a2e; }
.device-card-sn    { font-size: 12px; color: #888; margin-top: 2px; }
.device-card-style { font-size: 12px; color: #aaa; }
.device-card-check { flex-shrink: 0; }

.loading-msg,
.empty-msg {
  text-align: center;
  padding: 24px;
  color: #aaa;
  font-size: 14px;
}

/* ── Modal footer ── */
.modal-footer {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid #f0f0f0;
}

.btn-cancel {
  background: #f5f5f5;
  border: none;
  color: #555;
  padding: 9px 20px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
}
.btn-cancel:hover { background: #eee; }

.btn-assign {
  display: inline-flex;
  align-items: center;
  background: #6c2bd9;
  color: #fff;
  border: none;
  padding: 9px 22px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: background 0.2s, opacity 0.2s;
}
.btn-assign:hover:not(:disabled) { background: #5a23b8; }
.btn-assign:disabled { opacity: 0.5; cursor: default; }
.btn-assign.cno-save { background: #1565c0; }
.btn-assign.cno-save:hover:not(:disabled) { background: #0d47a1; }
</style>