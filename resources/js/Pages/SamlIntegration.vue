<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import axios from 'axios';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
  configurations: { type: Array, default: () => [] },
  defaultAttributes: { type: Array, default: () => [] },
  metadataUrl: { type: String, default: '' },
  acsUrl: { type: String, default: '' },
  sloUrl: { type: String, default: '' },
  stats: { type: Object, default: () => ({}) },
});

const configurations = ref([...props.configurations]);
const saving = ref(false);
const deletingId = ref(null);
const message = ref('');
const error = ref('');
const editingId = ref(null);

const emptyForm = () => ({
  name: '',
  mode: 'idp',
  entity_id: '',
  sso_url: '',
  acs_url: props.acsUrl,
  slo_url: '',
  x509_cert: '',
  signing_algo: 'rsa-sha256',
  default_relay_state: '/MainDashboard',
  attribute_release: [...props.defaultAttributes],
  require_signed_requests: false,
  sign_responses: true,
  sign_assertions: true,
  status: 'draft',
  is_active: false,
  metadata_xml: '',
  notes: '',
});

const form = reactive(emptyForm());

const activeConfig = computed(() => configurations.value.find((item) => item.is_active && item.status === 'active'));

const resetForm = (clearMessages = true) => {
  Object.assign(form, emptyForm());
  editingId.value = null;
  if (clearMessages) {
    error.value = '';
    message.value = '';
  }
};

const editConfiguration = (configuration) => {
  Object.assign(form, {
    ...emptyForm(),
    ...configuration,
    attribute_release: configuration.attribute_release?.length
      ? [...configuration.attribute_release]
      : [...props.defaultAttributes],
  });
  editingId.value = configuration.id;
  error.value = '';
  message.value = '';
};

const toggleAttribute = (attribute) => {
  if (form.attribute_release.includes(attribute)) {
    form.attribute_release = form.attribute_release.filter((item) => item !== attribute);
    return;
  }

  form.attribute_release = [...form.attribute_release, attribute];
};

const saveConfiguration = async () => {
  saving.value = true;
  error.value = '';
  message.value = '';

  try {
    const url = editingId.value ? `/saml-configurations/${editingId.value}` : '/saml-configurations';
    const method = editingId.value ? 'put' : 'post';
    const { data } = await axios[method](url, form);

    if (data.configuration.is_active && data.configuration.status === 'active') {
      configurations.value = configurations.value.map((item) =>
        item.mode === data.configuration.mode ? { ...item, is_active: false } : item
      );
    }

    if (editingId.value) {
      configurations.value = configurations.value.map((item) =>
        item.id === data.configuration.id ? data.configuration : item
      );
    } else {
      configurations.value = [data.configuration, ...configurations.value];
    }

    resetForm(false);
    message.value = data.message || 'SAML configuration saved.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to save the SAML configuration.';
  } finally {
    saving.value = false;
  }
};

const deleteConfiguration = async (configuration) => {
  if (!window.confirm(`Delete ${configuration.name}?`)) {
    return;
  }

  deletingId.value = configuration.id;
  error.value = '';
  message.value = '';

  try {
    await axios.delete(`/saml-configurations/${configuration.id}`);
    configurations.value = configurations.value.filter((item) => item.id !== configuration.id);
    if (editingId.value === configuration.id) {
      resetForm();
    }
    message.value = 'SAML configuration deleted.';
  } catch (err) {
    error.value = err.response?.data?.message || 'Unable to delete the SAML configuration.';
  } finally {
    deletingId.value = null;
  }
};

const copyValue = async (value) => {
  if (!value || !navigator?.clipboard) {
    return;
  }

  await navigator.clipboard.writeText(value);
  message.value = 'Copied to clipboard.';
};
</script>

<template>
  <AppLayout>
    <div class="space-y-5">
      <div class="app-page-header">
        <Breadcrumbs trail="UPCEBU > SAML" />
      </div>

      <div v-if="message" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
        {{ message }}
      </div>
      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
        {{ error }}
      </div>

      <section class="grid gap-4 lg:grid-cols-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ stats.total || configurations.length }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Active</div>
          <div class="mt-2 text-2xl font-bold text-[#005740]">{{ activeConfig ? 1 : 0 }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">IdP Configs</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ configurations.filter((item) => item.mode === 'idp').length }}</div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-4">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">SP Configs</div>
          <div class="mt-2 text-2xl font-bold text-slate-950">{{ configurations.filter((item) => item.mode === 'sp').length }}</div>
        </div>
      </section>

      <section class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_25rem]">
        <div class="space-y-5">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <h2 class="text-lg font-bold text-slate-950">Saved Configurations</h2>
                <p class="text-sm text-slate-500">Use active IdP records for SAML login; use SP records for connected applications.</p>
              </div>
              <a class="app-button-secondary" href="/saml2/login">Test SAML Login</a>
            </div>

            <div class="modern-table-card overflow-x-auto">
              <table class="app-table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Mode</th>
                    <th>Entity ID</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                  <tr v-if="configurations.length === 0">
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">No SAML configurations have been saved.</td>
                  </tr>
                  <tr v-for="configuration in configurations" :key="configuration.id">
                    <td>
                      <div class="font-semibold text-slate-950">{{ configuration.name }}</div>
                      <div v-if="configuration.is_active" class="mt-1 text-xs font-semibold text-[#005740]">Active provider</div>
                    </td>
                    <td class="uppercase">{{ configuration.mode }}</td>
                    <td class="max-w-xs truncate" :title="configuration.entity_id">{{ configuration.entity_id }}</td>
                    <td>
                      <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="configuration.status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'">
                        {{ configuration.status }}
                      </span>
                    </td>
                    <td>{{ configuration.updated_at || '-' }}</td>
                    <td class="text-right">
                      <div class="flex justify-end gap-2">
                        <button type="button" class="app-button-secondary px-3 py-1.5" @click="editConfiguration(configuration)">Edit</button>
                        <button
                          type="button"
                          class="rounded-xl border border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 transition hover:bg-red-50 disabled:opacity-60"
                          :disabled="deletingId === configuration.id"
                          @click="deleteConfiguration(configuration)"
                        >
                          Delete
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <form class="rounded-lg border border-slate-200 bg-white p-4" @submit.prevent="saveConfiguration">
            <div class="mb-4 flex items-center justify-between gap-3">
              <div>
                <h2 class="text-lg font-bold text-slate-950">{{ editingId ? 'Edit Configuration' : 'Create Configuration' }}</h2>
                <p class="text-sm text-slate-500">Paste metadata XML to prefill the IdP entity ID, SSO URL, SLO URL, and certificate.</p>
              </div>
              <button v-if="editingId" type="button" class="app-button-secondary" @click="resetForm">New</button>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">Name</span>
                <input v-model="form.name" class="app-field mt-1 w-full" required />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">Mode</span>
                <select v-model="form.mode" class="app-field mt-1 w-full">
                  <option value="idp">Identity Provider</option>
                  <option value="sp">Service Provider</option>
                </select>
              </label>
              <label class="block lg:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Entity ID</span>
                <input v-model="form.entity_id" class="app-field mt-1 w-full" placeholder="Prefilled from metadata XML when available" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">SSO URL</span>
                <input v-model="form.sso_url" type="url" class="app-field mt-1 w-full" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">ACS URL</span>
                <input v-model="form.acs_url" type="url" class="app-field mt-1 w-full" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">SLO URL</span>
                <input v-model="form.slo_url" type="url" class="app-field mt-1 w-full" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">Signing Algorithm</span>
                <select v-model="form.signing_algo" class="app-field mt-1 w-full">
                  <option value="rsa-sha256">RSA SHA-256</option>
                  <option value="rsa-sha384">RSA SHA-384</option>
                  <option value="rsa-sha512">RSA SHA-512</option>
                </select>
              </label>
              <label class="block lg:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Metadata XML</span>
                <textarea v-model="form.metadata_xml" class="app-field mt-1 min-h-32 w-full font-mono text-xs" placeholder="Paste IdP or SP metadata XML here"></textarea>
              </label>
              <label class="block lg:col-span-2">
                <span class="text-sm font-semibold text-slate-700">X.509 Certificate</span>
                <textarea v-model="form.x509_cert" class="app-field mt-1 min-h-28 w-full font-mono text-xs"></textarea>
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">Default Relay State</span>
                <input v-model="form.default_relay_state" class="app-field mt-1 w-full" />
              </label>
              <label class="block">
                <span class="text-sm font-semibold text-slate-700">Status</span>
                <select v-model="form.status" class="app-field mt-1 w-full">
                  <option value="draft">Draft</option>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </label>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
              <div>
                <div class="text-sm font-semibold text-slate-700">Attribute Release</div>
                <div class="mt-2 flex flex-wrap gap-2">
                  <label v-for="attribute in defaultAttributes" :key="attribute" class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input
                      type="checkbox"
                      :checked="form.attribute_release.includes(attribute)"
                      class="rounded border-slate-300 text-[#005740] focus:ring-[#005740]"
                      @change="toggleAttribute(attribute)"
                    />
                    {{ attribute }}
                  </label>
                </div>
              </div>
              <div>
                <div class="text-sm font-semibold text-slate-700">Policy Flags</div>
                <div class="mt-2 space-y-2">
                  <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-slate-300 text-[#005740] focus:ring-[#005740]" />
                    Use as active provider
                  </label>
                  <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.require_signed_requests" type="checkbox" class="rounded border-slate-300 text-[#005740] focus:ring-[#005740]" />
                    Require signed requests
                  </label>
                  <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.sign_responses" type="checkbox" class="rounded border-slate-300 text-[#005740] focus:ring-[#005740]" />
                    Sign responses
                  </label>
                  <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input v-model="form.sign_assertions" type="checkbox" class="rounded border-slate-300 text-[#005740] focus:ring-[#005740]" />
                    Sign assertions
                  </label>
                </div>
              </div>
              <label class="block lg:col-span-2">
                <span class="text-sm font-semibold text-slate-700">Notes</span>
                <textarea v-model="form.notes" class="app-field mt-1 min-h-20 w-full"></textarea>
              </label>
            </div>

            <div class="mt-5 flex justify-end gap-3">
              <button type="button" class="app-button-secondary" @click="resetForm">Clear</button>
              <button type="submit" class="app-button-primary" :disabled="saving">
                {{ saving ? 'Saving...' : 'Save Configuration' }}
              </button>
            </div>
          </form>
        </div>

        <aside class="space-y-4">
          <div class="rounded-lg border border-slate-200 bg-white p-4">
            <h2 class="text-lg font-bold text-slate-950">Local Endpoints</h2>
            <div class="mt-4 space-y-3">
              <button type="button" class="w-full rounded-lg border border-slate-200 p-3 text-left transition hover:bg-slate-50" @click="copyValue(metadataUrl)">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Metadata</div>
                <div class="mt-1 break-all text-sm font-semibold text-slate-800">{{ metadataUrl }}</div>
              </button>
              <button type="button" class="w-full rounded-lg border border-slate-200 p-3 text-left transition hover:bg-slate-50" @click="copyValue(acsUrl)">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">ACS</div>
                <div class="mt-1 break-all text-sm font-semibold text-slate-800">{{ acsUrl }}</div>
              </button>
              <button type="button" class="w-full rounded-lg border border-slate-200 p-3 text-left transition hover:bg-slate-50" @click="copyValue(sloUrl)">
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Logout</div>
                <div class="mt-1 break-all text-sm font-semibold text-slate-800">{{ sloUrl }}</div>
              </button>
            </div>
          </div>

          <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            ACS currently records and rejects inbound SAML responses until a maintained SAML toolkit is installed for XML signature and assertion validation.
          </div>
        </aside>
      </section>
    </div>
  </AppLayout>
</template>
