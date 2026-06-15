

import Client from "@/repositories/Client/AxiosClient"

const resource = `/api/bluetti-devices`

export default {
  getList(page = 1, filters = {}) {
    return Client.get(resource, {
      params: { page, limit: 15, search: filters.search || undefined },
    })
  },

  delete(id) {
    return Client.delete(`${resource}/${id}`)
  },

  create(payload) {
    return Client.post(resource, payload)
  },

  assignCustomer(deviceId, customerId) {
    return Client.post(`${resource}/${deviceId}/assign-customer`, {
      customer_id: customerId,
    })
  },

  unassignCustomer(deviceId) {
    return Client.delete(`${resource}/${deviceId}/unassign-customer`)
  },

  byCustomer(customerId) {
    return Client.get(`${resource}/by-customer/${customerId}`)
  },

  // ─── Monthly Transactions ──────────────────────────────────────────────────

  /**
   * Device ki saari monthly transactions fetch karo
   */
  getTransactions(deviceId) {
    return Client.get(`${resource}/${deviceId}/transactions`)
  },

  /**
   * Transaction save ya update (same month+year pe overwrite)
   * @param {number} deviceId
   * @param {{ transaction_id: string, month: number, year: number }} payload
   */
  upsertTransaction(deviceId, payload) {
    return Client.post(`${resource}/${deviceId}/transactions`, payload)
  },

  // ─── Legacy single-field methods (backward compat) ────────────────────────

  assignTransaction(deviceId, transactionId) {
    return Client.post(`${resource}/${deviceId}/assign-transaction`, {
      transaction_id: transactionId,
    })
  },

  assignCustomerNo(deviceId, customerNo) {
    return Client.post(`${resource}/${deviceId}/assign-customer-no`, {
      customer_no: customerNo,
    })
  },

  activateTransaction(deviceId, txnId) {
    return Client.post(`${resource}/${deviceId}/transactions/${txnId}/activate`)
  },
  deactivateTransaction(deviceId, txnId) {
    return Client.post(`${resource}/${deviceId}/transactions/${txnId}/deactivate`)
  },
}
