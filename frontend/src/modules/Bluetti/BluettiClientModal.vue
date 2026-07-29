<template>
  <div v-if="show" class="modal-overlay" @click.self="cancel">
    <div class="modal-box">

      <div class="modal-head">
        <div class="modal-title">Add BLUETTI User</div>
        <button class="modal-close" @click="cancel">×</button>
      </div>

      <div v-if="loading" class="loading-msg">Saving...</div>

      <div v-else class="modal-section">
        <div class="form-grid">

          <div class="field-group">
            <label>Title</label>
            <input v-model="person.title" class="field-input" placeholder="Mr / Mrs / Dr" />
          </div>

          <div class="field-group">
            <label>Name *</label>
            <input v-model="person.name" class="field-input" />
            <span v-if="errors.name" class="field-error">{{ errors.name }}</span>
          </div>

          <div class="field-group">
            <label>Surname *</label>
            <input v-model="person.surname" class="field-input" />
            <span v-if="errors.surname" class="field-error">{{ errors.surname }}</span>
          </div>

          <div class="field-group">
            <label>Birth Date</label>
            <input v-model="person.birthDate" type="date" class="field-input" />
          </div>

          <div class="field-group">
            <label>Gender</label>
            <select v-model="person.gender" class="field-input">
              <option value="">-- Select --</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non-binary">Non-binary</option>
            </select>
          </div>

          <div class="field-group">
            <label>Education</label>
            <input v-model="person.education" class="field-input" />
          </div>

          <div class="field-group">
            <label>Email</label>
            <input v-model="person.email" type="email" class="field-input" />
            <span v-if="errors.email" class="field-error">{{ errors.email }}</span>
          </div>

          <div class="field-group">
            <label>Phone *</label>
            <input v-model="person.phone" class="field-input" placeholder="+2348012345678" />
            <span v-if="errors.phone" class="field-error">{{ errors.phone }}</span>
          </div>

          <div class="field-group full-width">
            <label>City *</label>
            <select v-model="person.cityId" class="field-input">
              <option :value="null">-- Select City --</option>
              <option v-for="city in cityService.list" :key="city.id" :value="city.id">
                {{ city.name }}
              </option>
            </select>
            <span v-if="errors.cityId" class="field-error">{{ errors.cityId }}</span>
          </div>

          <div class="field-group full-width">
            <label>Street</label>
            <input v-model="person.street" class="field-input" />
          </div>

          <!-- User Type — locked to BLUETTI, not editable -->
          <div class="field-group full-width">
            <label>User Type</label>
            <input value="BLUETTI" class="field-input" disabled />
          </div>

        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-cancel" @click="cancel">Cancel</button>
        <button class="btn-save" :disabled="loading" @click="save">
          {{ loading ? 'Saving...' : 'Save' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script>
import { PersonService } from "@/services/PersonService"
import { CityService } from "@/services/CityService"

export default {
  name: "BluettiClientModal",
  props: {
    show: {
      required: true,
      type: Boolean,
    },
  },
  data() {
    return {
      personService: new PersonService(),
      cityService: new CityService(),
      loading: false,
      errors: {},
      person: {
        title: "",
        name: "",
        surname: "",
        birthDate: "",
        gender: "",
        education: "",
        email: "",
        phone: "",
        cityId: null,
        street: "",
      },
    }
  },
  beforeMount() {
    this.cityService.getCities()
  },
  methods: {
    validate() {
      this.errors = {}
      if (!this.person.name || this.person.name.length < 2) this.errors.name = "Name is required"
      if (!this.person.surname || this.person.surname.length < 2) this.errors.surname = "Surname is required"
      if (!this.person.phone || this.person.phone.length < 11) this.errors.phone = "Valid phone is required"
      if (!this.person.cityId) this.errors.cityId = "City is required"
      if (this.person.email && !/\S+@\S+\.\S+/.test(this.person.email)) this.errors.email = "Invalid email"
      return Object.keys(this.errors).length === 0
    },

    async save() {
      if (!this.validate()) return
      this.loading = true
      try {
        const personParams = {
          title: this.person.title,
          name: this.person.name,
          surname: this.person.surname,
          birthDate: this.person.birthDate || null,
          gender: this.person.gender,
          education: this.person.education,
          email: this.person.email,
          phone: this.person.phone,
          cityId: this.person.cityId,
          street: this.person.street,
          isPrimary: true,
          isCustomer: true,
          bluettiType: "BLUETTI",   // ✅ locked field
        }
        const result = await this.personService.createPerson(personParams)

        if (result && result.id) {
          this.$emit("saved")
          this.reset()
        } else {
          this.$swal("Error", result?.message || "Could not create user", "error")
        }
      } catch (e) {
        this.$swal("Error", e.message || "Something went wrong", "error")
      } finally {
        this.loading = false
      }
    },

    reset() {
      this.person = {
        title: "", name: "", surname: "", birthDate: "", gender: "",
        education: "", email: "", phone: "", cityId: null, street: "",
      }
      this.errors = {}
    },

    cancel() {
      this.reset()
      this.$emit("close")
    },
  },
}
</script>

<style scoped>
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
  max-width: 640px;
  border-radius: 14px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
  max-height: 90vh;
  overflow-y: auto;
}
.modal-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px 16px;
  border-bottom: 1px solid #f0f0f0;
}
.modal-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
.modal-close {
  background: none;
  border: none;
  font-size: 26px;
  cursor: pointer;
  color: #aaa;
}
.modal-close:hover { color: #333; }
.modal-section { padding: 20px 24px; }

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.field-group { display: flex; flex-direction: column; gap: 4px; }
.field-group.full-width { grid-column: 1 / -1; }
.field-group label { font-size: 12px; font-weight: 600; color: #666; }
.field-input {
  padding: 9px 12px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 14px;
  outline: none;
}
.field-input:focus { border-color: #6c2bd9; }
.field-input:disabled { background: #f5f5f5; color: #888; }
.field-error { font-size: 11px; color: #c62828; }

.modal-footer {
  display: flex;
  justify-content: flex-end;
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
}
.btn-save {
  background: #6c2bd9;
  color: #fff;
  border: none;
  padding: 9px 22px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
}
.btn-save:disabled { opacity: 0.5; }
.loading-msg { text-align: center; padding: 40px; color: #888; }
</style>