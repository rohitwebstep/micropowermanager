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

              <md-table-cell md-label="BLUETTI Devices">
                <span
                  v-if="!getAssignedDevices(item.id).length"
                  class="no-device"
                >—</span>
                <span v-else class="device-chips">
                  <span
                    v-for="d in getAssignedDevices(item.id)"
                    :key="d.id"
                    class="device-chip"
                  >
                   
                    <span
                      v-for="d in getAssignedDevices(item.id)"
                      :key="d.id"
                      class="device-chip"
                      @click="showDeviceDetail(d)"
                      style="cursor:pointer; transition: 0.2s"
                    >
                      {{ d.device_name }}
                      <span class="chip-sn">{{ d.serial_number }}</span>
                    </span>
                  </span>
                </span>
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
    <div
      v-if="showAssignModal"
      class="modal-overlay"
      @click.self="closeModal"
    >
      <div class="modal-box">

        <!-- HEADER -->
        <div class="modal-head">
          <div>
            <div class="modal-title">Assign BLUETTI Device</div>
            <div class="modal-sub" v-if="selectedCustomer">
              Customer:
              <b>{{ selectedCustomer.name }} {{ selectedCustomer.surname }}</b>
            </div>
          </div>
          <button class="modal-close" @click="closeModal">x</button>
        </div>

        <!-- CURRENTLY ASSIGNED -->
        <div
          class="modal-section"
          v-if="
            selectedCustomer &&
            getAssignedDevices(selectedCustomer.id).length > 0
          "
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

          <div v-if="loadingFreeDevices" class="loading-msg">
            Loading devices...
          </div>

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
              <div class="device-card-icon">
                <md-icon>bolt</md-icon>
              </div>
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

        <!-- FOOTER -->
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

      // modal state
      showAssignModal: false,
      selectedCustomer: null,
      selectedDeviceId: null,
      freeDevices: [],
      loadingFreeDevices: false,
      assigning: false,
      unassigningId: null,
      deviceDetailModal: false,
      selectedDeviceDetail: null,

      // customerId -> [devices] cache
      assignedDevicesMap: {},
    }
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

  showDeviceDetail(device) {
    this.selectedDeviceDetail = device
    this.deviceDetailModal = true
  },

    // LIST
    async getClientList(pageNumber = 1) {
      this.loading = true
      try {
        const response = await this.paginator.loadPage(pageNumber)
        this.people.updateList(response.data)
        EventBus.$emit(
          "widgetContentLoaded",
          this.subscriber,
          this.people.list.length
        )
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
      EventBus.$emit(
        "widgetContentLoaded",
        this.subscriber,
        this.people.list.length
      )
      this.prefetchAssignedDevices(this.people.list)
    },

    onSearchEvent() {},

    onEndSearchEvent() {
      this.paginator = new Paginator(resources.person.list)
      this.getClientList()
    },

    // ASSIGNED DEVICES CACHE
    async prefetchAssignedDevices(customers) {
      await Promise.allSettled(
        customers.map((c) => this.fetchAssignedForCustomer(c.id))
      )
    },

    async fetchAssignedForCustomer(customerId) {
    try {
      const { data } = await BluettiDeviceRepository.byCustomer(customerId)
      // ✅ array bhi ho sakta hai, object bhi — dono handle karo
      let devices = data?.data ?? data ?? []
      if (!Array.isArray(devices)) {
        devices = [devices]
      }
      this.$set(this.assignedDevicesMap, customerId, devices)
    } catch (e) {
      this.$set(this.assignedDevicesMap, customerId, [])
    }
  },

    getAssignedDevices(customerId) {
      return this.assignedDevicesMap[customerId] || []
    },

    // MODAL
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

.device-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.device-chip {
  display: inline-block;
  align-items: center;
  gap: 5px;
  background: #ede7f6;
  color: #4a148c;
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
}
.chip-sn   { color: #7b1fa2; font-size: 11px; }
.no-device { color: #bbb; }

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
.device-card:hover         { border-color: #c4a0ff; background: #faf5ff; }
.device-card--selected     { border-color: #6c2bd9; background: #f3eeff; }

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
</style>