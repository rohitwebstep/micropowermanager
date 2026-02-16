<template>
  <div class="import-list">
    <h1>Import Listss</h1>

    <table class="import-table">
      <thead>
        <tr>
          <th>#</th>
          <th>File Name</th>
          <th>Type</th>
          <th>Status</th>
          <th>Imported At</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
        <tr v-if="imports.length === 0">
          <td colspan="6" class="empty">
            No imports found
          </td>
        </tr>

        <tr v-for="(item, index) in imports" :key="item.id">
          <td>{{ index + 1 }}</td>
          <td>{{ item.fileName }}</td>
          <td>{{ item.type }}</td>
          <td>
            <span :class="['status', item.status.toLowerCase()]">
              {{ item.status }}
            </span>
          </td>
          <td>{{ item.importedAt }}</td>
          <td>
            <button @click="view(item)">View</button>
            <button class="danger" @click="remove(item.id)">Delete</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { ref } from "vue";

const imports = ref([
  {
    id: 1,
    fileName: "products.csv",
    type: "Products",
    status: "Completed",
    importedAt: "2026-01-20",
  },
  {
    id: 2,
    fileName: "customers.xlsx",
    type: "Customers",
    status: "Pending",
    importedAt: "2026-01-21",
  },
  {
    id: 3,
    fileName: "orders.csv",
    type: "Orders",
    status: "Failed",
    importedAt: "2026-01-22",
  },
]);

const view = (item) => {
  console.log("Viewing import:", item);
};

const remove = (id) => {
  imports.value = imports.value.filter(item => item.id !== id);
};
</script>

<style scoped>
.import-list {
  padding: 20px;
}

.import-table {
  width: 100%;
  border-collapse: collapse;
}

.import-table th,
.import-table td {
  padding: 10px;
  border: 1px solid #ddd;
  text-align: left;
}

.import-table th {
  background: #f5f5f5;
}

.empty {
  text-align: center;
  color: #777;
}

.status {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 13px;
}

.status.completed {
  background: #e6f4ea;
  color: #1e7e34;
}

.status.pending {
  background: #fff3cd;
  color: #856404;
}

.status.failed {
  background: #f8d7da;
  color: #842029;
}

button {
  margin-right: 6px;
  padding: 5px 10px;
  cursor: pointer;
}

button.danger {
  background: #dc3545;
  color: #fff;
  border: none;
}
</style>
