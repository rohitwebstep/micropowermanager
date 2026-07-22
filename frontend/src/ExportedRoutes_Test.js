// micropowermanager-main\frontend\src\ExportedRoutes.js
import LoginHeader from "@/modules/Login/LoginHeader"
import LoginFooter from "@/modules/Login/LoginFooter"
import Welcome from "@/modules/Welcome/index.vue"
import Login from "@/modules/Login/Login.vue"
import Register from "@/modules/Register/Register.vue"
import ForgotPassword from "@/modules/ForgotPassword/ForgotPassword.vue"
import UserPasswordResetConfirm from "./modules/UserPasswordReset/UserPasswordResetConfirm.vue"
import UnauthorizedPage from "@/modules/Unauthorized/index.vue"

import ChildRouteWrapper from "./shared/ChildRouteWrapper.vue"

import ClusterOverviewPage from "@/modules/Dashboard/Dashboard.vue"
import ClusterDetailPage from "@/modules/Cluster/Dashboard.vue"
// MiniGridOverviewPage just redirects to MiniGridDetailPage for first Mini-Grid


// ExportedRoutes.js mein ye imports add karo
import BluettiDeviceList from "@/modules/Bluetti/DeviceList.vue"
import BluettiAddDevice from "@/modules/Bluetti/AddDevice.vue"
import BluettiUserList from "@/modules/Bluetti/BluettiUserList.vue"

import BluettiDevicePage from "@/modules/Bluetti/BluettiDevicePage.vue"



export const exportedRoutes = [

  // Add BLUETTI route
  {
    path: "/dashboards",
    component: ChildRouteWrapper,
    meta: {
      layout: "default",
      sidebar: {
        enabled: true,
        name: "BLUETTI",
        icon: "bolt",
      },
    },
    children: [
      {
        path: "bluetti/device-list",
        component: BluettiDeviceList,
        meta: {
          layout: "default",
          sidebar: { enabled: true, name: "Device List" },
        },
      },
      {
        path: "bluetti/add-device",
        component: BluettiAddDevice,
        meta: {
          layout: "default",
          sidebar: { enabled: true, name: "Add Device" },
        },
      },

      // ✅ CustomerList
      {
        path: "bluetti/users",
        component: BluettiUserList,
        meta: {
          layout: "default",
          sidebar: { enabled: true, name: "Users" },
        },
      },
      {
        path: "bluetti/users/:id",
        component: CustomerDetail,
        meta: {
          layout: "default",
          sidebar: { enabled: false },
        },
      },
      {
        path: "bluetti/device/:deviceId",
        name: "BluettiDevicePage",
        component: BluettiDevicePage,
        meta: {
          layout: "default",
          sidebar: { enabled: false },
        },
      },
    ],
  }

]
