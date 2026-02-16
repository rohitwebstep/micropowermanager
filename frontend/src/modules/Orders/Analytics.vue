<template>
  <div class="orders-analytics">

    <h2>Orders Analytics</h2>

    <!-- Filters -->
    <div class="filters">
      <input type="date" v-model="filters.from" />
      <input type="date" v-model="filters.to" />
      <button @click="loadAnalytics">Apply</button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="loading">Loading...</div>

    <!-- Summary Cards -->
    <div v-if="analytics" class="summary">

      <div class="card">
        <p>Total Orders</p>
        <h3>{{ analytics.grand_total_orders }}</h3>
      </div>

      <div class="card">
        <p>Total Revenue</p>
        <h3>{{ analytics.grand_total_amount }}</h3>
      </div>

    </div>

    <!-- Type Summary -->
    <div v-if="analytics && analytics.summary && analytics.summary.length" class="types">

      <h3>Order Types</h3>

      <div
        v-for="item in analytics.summary"
        :key="item.type"
        class="type-box"
      >
        <p><strong>{{ item.type }}</strong></p>
        <p>Orders: {{ item.total_orders }}</p>
        <p>Amount: {{ item.total_amount }}</p>
      </div>

    </div>

    <!-- No Data -->
    <div v-if="!loading && analytics && !analytics.summary.length">
      No analytics data found.
    </div>

  </div>
</template>

<script>
import { AnalyticsService } from "@/services/AnalyticsService"

export default {
  name: "OrdersAnalytics",

  data() {
    return {
      loading: false,
      analytics: null,
      filters: {
        from: "",
        to: ""
      }
    }
  },

  methods: {

    async loadAnalytics() {
      try {
        this.loading = true

        const data = await AnalyticsService.getAnalytics(this.filters)

        this.analytics = data

      } catch (error) {
        console.error("Analytics load failed:", error)
      } finally {
        this.loading = false
      }
    }

  },

  mounted() {
    // default: last 7 days
    const today = new Date()
    const past = new Date()
    past.setDate(today.getDate() - 7)

    this.filters.from = past.toISOString().split("T")[0]
    this.filters.to = today.toISOString().split("T")[0]

    this.loadAnalytics()
  }

}
</script>

<style scoped>
.orders-analytics {
  padding: 20px;
}

.filters {
  display: flex;
  gap: 10px;
  margin-bottom: 20px;
}

.loading {
  margin: 20px 0;
}

.summary {
  display: flex;
  gap: 20px;
  margin-bottom: 20px;
}

.card {
  background: #ffffff;
  padding: 16px;
  border-radius: 8px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.05);
  min-width: 180px;
}

.types {
  margin-top: 20px;
}

.type-box {
  background: #f7f7f7;
  padding: 12px;
  border-radius: 6px;
  margin-bottom: 10px;
}
</style>
