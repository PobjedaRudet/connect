<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  employee: { type: Object, default: null },
  departments: { type: Array, default: () => [] },
  funkcije: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  cancelUrl: { type: String, default: '/employees' },
})

const isEdit = computed(() => Boolean(props.employee?.id))

const radnaMjestaOptions = [
  'Upravnik pogona proizvodnje detonatora',
  'Zamjenik Upravnika',
  'Administrativni radnik za poslove kontrole i mjern...',
  'Izrada, kompletiranje i kontrola DK',
  'Izrada EZG',
  'Tehnolog konstruktor (310.002)',
  'Administrativni radnik za poslove investicija i up...',
  'Glavni tehnolog konstruktor',
  'Referent pravnih i kadrovskih poslova',
  'Vodeći poslovi operativne pripreme',
  'Poslovi expedita, distribucije pošte i arhivski po...',
  'Reglaža automata i složenih mašina',
  'Građevinsko održavanje (330.006)',
  'Rukovođenje i reglaža na izradi inicirajuće cjevči...',
  'Sječenje i regulisanje vremana gorenja usporača',
  'Doziranje i presovanje inicijalnog punjenja DK',
  'Priprema olovnih cijevi i topljenje olova',
  'Reglaža na laboraciji',
  'Vođa poligona za međuoperacijsku i završnu kontrol...',
  'Izrada i priprema pirotehničkih smješa',
  'Vođa odjeljenja',
  'Izrada inicirajuće cjevčice',
  'Reglaža mašina mehaničke obrade',
  'Poslovi operativne pripreme',
  'Izrada i kontrola mehaničkih elemenata',
]

const selectedRadnoMjesto = computed({
  get() {
    const value = form.radno_mjesto ?? ''
    if (!value) return ''
    return radnaMjestaOptions.includes(value) ? value : '__custom__'
  },
  set(val) {
    if (!val) {
      form.radno_mjesto = ''
      return
    }
    if (val === '__custom__') {
      if (radnaMjestaOptions.includes(form.radno_mjesto)) {
        form.radno_mjesto = ''
      }
      return
    }
    form.radno_mjesto = val
  },
})

const form = useForm({
  empID: props.employee?.empID ?? '',
  rfid_code: props.employee?.rfid_code ?? '',
  middle_name: props.employee?.middle_name ?? '',
  firstName: props.employee?.firstName ?? '',
  lastName: props.employee?.lastName ?? '',
  radno_mjesto: props.employee?.radno_mjesto ?? '',
  department_id: props.employee?.department_id ?? '',
  email: props.employee?.email ?? '',
  sex: props.employee?.sex ?? '',
  risk: props.employee?.risk ?? false,
  period: props.employee?.period ?? '',
  status: props.employee?.status ?? '',
  active: props.employee?.Active ?? true,
  profesionalno_oboljenje: props.employee?.profesionalno_oboljenje ?? '',
  invalidnost_radnika: props.employee?.invalidnost_radnika ?? '',
  nadlezne_osobe: props.employee?.nadlezne_osobe ?? [],
})

const hasErrors = computed(() => Object.keys(form.errors || {}).length > 0)

const fieldClass = (field) => {
  const base = 'mt-1 block w-full rounded-md shadow-sm text-sm'
  const ok = 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'
  const bad = 'border-red-300 text-red-900 placeholder-red-300 focus:border-red-500 focus:ring-red-500'
  return `${base} ${form.errors?.[field] ? bad : ok}`
}

const selectClass = (field) => fieldClass(field)

const submit = () => {
  const action = isEdit.value
    ? route('hr.uposlenici.update', props.employee.id)
    : route('hr.uposlenici.store')

  const method = isEdit.value ? 'put' : 'post'
  form[method](action, {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout :title="isEdit ? 'Uredi uposlenika' : 'Novi uposlenik'">
    <Head :title="isEdit ? 'Uredi uposlenika' : 'Novi uposlenik'" />

    <div class="max-w-6xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
      <div class="flex items-center justify-between">
        <div class="flex gap-2">
          <Link
            :href="route('sector.hr')"
            class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          >
            Nazad na HR
          </Link>
          <Link
            :href="cancelUrl"
            class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          >
            Nazad na pregled
          </Link>
        </div>

        <span
          class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold"
          :class="isEdit ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
        >
          {{ isEdit ? 'Uređivanje' : 'Novi unos' }}
        </span>
      </div>

      <div>
        <h1 class="text-2xl font-semibold text-gray-800">{{ isEdit ? 'Uredi uposlenika' : 'Dodaj uposlenika' }}</h1>
        <p class="text-sm text-gray-500">Unos i izmjena podataka uposlenika.</p>
      </div>

      <div v-if="hasErrors" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        Provjerite označena polja i pokušajte ponovo.
      </div>

      <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
        <form @submit.prevent="submit" enctype="multipart/form-data">
          <!-- Identifikacija -->
          <div class="px-6 py-5 border-b border-gray-200 bg-white">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-gray-800">Identifikacija</h2>
                <p class="text-xs text-gray-500 mt-0.5">Osnovni identifikatori uposlenika.</p>
              </div>
              <p class="text-xs text-gray-500">Polja označena sa <span class="text-red-600">*</span> su obavezna.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Šifra (empID) <span class="text-red-600">*</span></label>
                <input v-model="form.empID" type="text" :class="fieldClass('empID')" placeholder="1234" inputmode="numeric" />
                <p v-if="form.errors.empID" class="text-sm text-red-600 mt-1">{{ form.errors.empID }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">RFID kod</label>
                <input v-model="form.rfid_code" type="text" :class="fieldClass('rfid_code')" placeholder="(opciono)" />
                <p v-if="form.errors.rfid_code" class="text-sm text-red-600 mt-1">{{ form.errors.rfid_code }}</p>
              </div>
            </div>
          </div>

          <!-- Lični podaci -->
          <div class="px-6 py-5 border-b border-gray-200 bg-white">
            <h2 class="text-sm font-semibold text-gray-800">Lični podaci</h2>
            <p class="text-xs text-gray-500 mt-0.5">Podaci za identifikaciju i kontakt.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Ime <span class="text-red-600">*</span></label>
                <input v-model="form.firstName" type="text" :class="fieldClass('firstName')" placeholder="Ime" />
                <p v-if="form.errors.firstName" class="text-sm text-red-600 mt-1">{{ form.errors.firstName }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Prezime <span class="text-red-600">*</span></label>
                <input v-model="form.lastName" type="text" :class="fieldClass('lastName')" placeholder="Prezime" />
                <p v-if="form.errors.lastName" class="text-sm text-red-600 mt-1">{{ form.errors.lastName }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Srednje ime</label>
                <input v-model="form.middle_name" type="text" :class="fieldClass('middle_name')" placeholder="Srednje ime" />
                <p v-if="form.errors.middle_name" class="text-sm text-red-600 mt-1">{{ form.errors.middle_name }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input v-model="form.email" type="email" :class="fieldClass('email')" placeholder="email@firma.ba" />
                <p v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Spol</label>
                <select v-model="form.sex" :class="selectClass('sex')">
                  <option value="">Odaberite</option>
                  <option value="M">Muški</option>
                  <option value="F">Ženski</option>
                </select>
                <p v-if="form.errors.sex" class="text-sm text-red-600 mt-1">{{ form.errors.sex }}</p>
              </div>
            </div>
          </div>

          <!-- Radni podaci -->
          <div class="px-6 py-5 border-b border-gray-200 bg-white">
            <h2 class="text-sm font-semibold text-gray-800">Radni podaci</h2>
            <p class="text-xs text-gray-500 mt-0.5">Odjel, radno mjesto i nadležne osobe.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Odjel</label>
                <select v-model="form.department_id" :class="selectClass('department_id')">
                  <option value="">Odaberite odjel</option>
                  <option v-for="dept in departments" :key="dept.id" :value="dept.id">
                    {{ dept.name }}
                  </option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Prikazuje se naziv odjela, a u pozadini se sprema ID iz tabele departments.</p>
                <p v-if="form.errors.department_id" class="text-sm text-red-600 mt-1">{{ form.errors.department_id }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Radno mjesto</label>
                <select v-model="selectedRadnoMjesto" :class="selectClass('radno_mjesto')" class="max-w-md">
                  <option value="">Odaberite radno mjesto</option>
                  <option v-for="o in radnaMjestaOptions" :key="o" :value="o">{{ o }}</option>
                  <option value="__custom__">Drugo (ručni unos)...</option>
                </select>

                <div v-if="selectedRadnoMjesto === '__custom__'" class="mt-2">
                  <input v-model="form.radno_mjesto" type="text" :class="fieldClass('radno_mjesto')" class="max-w-md" placeholder="Unesite radno mjesto" />
                </div>

                <p class="text-xs text-gray-500 mt-1">Možete odabrati sa liste ili ručno unijeti vrijednost.</p>
                <p v-if="form.errors.radno_mjesto" class="text-sm text-red-600 mt-1">{{ form.errors.radno_mjesto }}</p>
              </div>

              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Nadležne osobe</label>
                <select
                  v-model="form.nadlezne_osobe"
                  multiple
                  :class="selectClass('nadlezne_osobe')"
                  class="h-32"
                >
                  <option v-for="u in supervisors" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Izaberite korisnike koji su nadležni za ovog uposlenika.</p>
                <p v-if="form.errors.nadlezne_osobe" class="text-sm text-red-600 mt-1">{{ form.errors.nadlezne_osobe }}</p>
              </div>
            </div>
          </div>

          <!-- Status i period -->
          <div class="px-6 py-5 border-b border-gray-200 bg-white">
            <h2 class="text-sm font-semibold text-gray-800">Status i period</h2>
            <p class="text-xs text-gray-500 mt-0.5">Aktivnost, rizik, period i status ugovora.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Period (mjeseci)</label>
                <select v-model="form.period" :class="selectClass('period')">
                  <option value="">Odaberite</option>
                  <option value="12">12</option>
                  <option value="18">18</option>
                  <option value="24">24</option>
                  <option value="36">36</option>
                </select>
                <p v-if="form.errors.period" class="text-sm text-red-600 mt-1">{{ form.errors.period }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Status</label>
                <select v-model="form.status" :class="selectClass('status')">
                  <option value="">Odaberite status</option>
                  <option value="1">Neodređeno</option>
                  <option value="2">Određeno</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Prikazuje se naziv statusa, a sprema se vrijednost 1 ili 2.</p>
                <p v-if="form.errors.status" class="text-sm text-red-600 mt-1">{{ form.errors.status }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Aktivan</label>
                <select v-model="form.active" :class="selectClass('active')">
                  <option :value="true">Da</option>
                  <option :value="false">Ne</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Određuje da li je uposlenik aktivan i prikazuje se u listama.</p>
                <p v-if="form.errors.active" class="text-sm text-red-600 mt-1">{{ form.errors.active }}</p>
              </div>

              <div class="md:col-span-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="rounded-md border border-gray-200 p-4">
                    <div class="flex items-start gap-3">
                      <input v-model="form.risk" type="checkbox" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                      <div>
                        <p class="text-sm font-medium text-gray-800">Rizik</p>
                        <p class="text-xs text-gray-500">Označi kao rizično (po potrebi).</p>
                        <p v-if="form.errors.risk" class="text-sm text-red-600 mt-1">{{ form.errors.risk }}</p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Ostalo -->
          <div class="px-6 py-5 bg-white">
            <h2 class="text-sm font-semibold text-gray-800">Ostalo</h2>
            <p class="text-xs text-gray-500 mt-0.5">Dodatne napomene/oznake.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
              <div>
                <label class="block text-sm font-medium text-gray-700">Profesionalno oboljenje</label>
                <input v-model="form.profesionalno_oboljenje" type="text" :class="fieldClass('profesionalno_oboljenje')" placeholder="Unesite opis" />
                <p v-if="form.errors.profesionalno_oboljenje" class="text-sm text-red-600 mt-1">{{ form.errors.profesionalno_oboljenje }}</p>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Invalidnost radnika</label>
                <input v-model="form.invalidnost_radnika" type="text" :class="fieldClass('invalidnost_radnika')" placeholder="Unesite opis" />
                <p v-if="form.errors.invalidnost_radnika" class="text-sm text-red-600 mt-1">{{ form.errors.invalidnost_radnika }}</p>
              </div>
            </div>
          </div>

          <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between gap-3 bg-gray-50">
            <Link
              :href="cancelUrl"
              class="inline-flex items-center px-3 py-2 bg-white text-gray-700 border border-gray-200 rounded-md text-sm font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
            >
              Odustani
            </Link>

            <button
              type="submit"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 disabled:opacity-50"
              :disabled="form.processing"
            >
              {{ form.processing ? 'Spremanje...' : (isEdit ? 'Sačuvaj izmjene' : 'Sačuvaj uposlenika') }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </AppLayout>
</template>
