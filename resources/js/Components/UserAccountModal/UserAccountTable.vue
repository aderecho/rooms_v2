<script setup>
import { computed, ref } from 'vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import IconButton from '@/Components/IconButton.vue';

const props = defineProps({
  users: { type: Array, required: true },
  loading: { type: Boolean, default: false },
  colleges: { type: Array, default: () => [] },
  departments: { type: Array, default: () => [] },
  canManageUsers: { type: Boolean, default: false },
});

const emit = defineEmits(['openModal', 'search', 'searchQueryUpdated']);

const searchQuery = ref('');
const currentPage = ref(1);
const itemsPerPage = ref(10);

const filteredUsers = computed(() => {
  const query = searchQuery.value.toLowerCase().trim();

  if (!query) return props.users;

  return props.users.filter((user) => (
    user?.username?.toLowerCase().includes(query) ||
    user?.email?.toLowerCase().includes(query) ||
    user?.first_name?.toLowerCase().includes(query) ||
    user?.last_name?.toLowerCase().includes(query) ||
    user?.role?.toLowerCase().includes(query) ||
    user?.department?.toLowerCase().includes(query) ||
    user?.college?.toLowerCase().includes(query)
  ));
});

const totalPages = computed(() => Math.max(1, Math.ceil(filteredUsers.value.length / Number(itemsPerPage.value))));

const paginatedUsers = computed(() => {
  const start = (currentPage.value - 1) * Number(itemsPerPage.value);
  return filteredUsers.value.slice(start, start + Number(itemsPerPage.value));
});

const visiblePages = computed(() => {
  const total = totalPages.value;
  const current = currentPage.value;

  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1);
  }

  if (current <= 4) return [1, 2, 3, 4, 5, '...', total];
  if (current >= total - 3) return [1, '...', total - 4, total - 3, total - 2, total - 1, total];

  return [1, '...', current - 1, current, current + 1, '...', total];
});

const showingRange = computed(() => {
  const total = filteredUsers.value.length;

  if (total === 0) {
    return { start: 0, end: 0, total };
  }

  const start = (currentPage.value - 1) * Number(itemsPerPage.value) + 1;
  const end = Math.min(currentPage.value * Number(itemsPerPage.value), total);

  return { start, end, total };
});

const handleSearchInput = () => {
  currentPage.value = 1;
  emit('searchQueryUpdated', searchQuery.value);
};

const performSearch = () => {
  emit('searchQueryUpdated', searchQuery.value);
  emit('search');
};

const goToPage = (page) => {
  if (Number.isInteger(page) && page >= 1 && page <= totalPages.value) {
    currentPage.value = page;
  }
};

const previousPage = () => goToPage(currentPage.value - 1);
const nextPage = () => goToPage(currentPage.value + 1);

const resetPagination = () => {
  currentPage.value = 1;
};

const handleAddAccount = () => emit('openModal', 'add');
const handleView = (user) => emit('openModal', 'view', user);
const handleEdit = (user) => emit('openModal', 'edit', user);
const handleDelete = (user) => emit('openModal', 'delete', user);

const displayName = (user) => {
  const name = `${user?.first_name || ''} ${user?.last_name || ''}`.trim();
  return name || 'N/A';
};

const formatRole = (role) => {
  if (!role) return 'N/A';
  return role.charAt(0).toUpperCase() + role.slice(1);
};

const roleBadgeClass = (role) => {
  const roleLower = String(role || '').toLowerCase();

  if (roleLower === 'admin') return 'bg-purple-100 text-purple-700';
  if (roleLower === 'faculty') return 'bg-emerald-100 text-emerald-700';
  if (roleLower === 'staff') return 'bg-blue-100 text-blue-700';
  if (roleLower === 'student') return 'bg-amber-100 text-amber-700';

  return 'bg-slate-100 text-slate-700';
};
</script>

<template>
  <div class="account-management">
    <header class="account-header">
      <Breadcrumbs trail="UPCEBU > ACCOUNT" />
    </header>

    <section class="account-toolbar">
      <div class="account-search">
        <IconButton
          icon="search"
          size="sm"
          color="gray"
          class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2"
        />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search users..."
          @input="handleSearchInput"
          @keyup.enter="performSearch"
        />
      </div>

      <button
        v-if="canManageUsers"
        type="button"
        class="add-account-button"
        @click="handleAddAccount"
      >
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        Add User Account
      </button>
    </section>

    <section class="account-table-card modern-table-card">
      <div class="modern-table-header">
        <div>
          <div class="modern-table-title">Users</div>
          <p class="modern-table-subtitle">{{ filteredUsers.length }} user records</p>
        </div>
        <div v-if="filteredUsers.length > 0 && !loading" class="modern-table-controls">
          <span>Rows</span>
          <select v-model="itemsPerPage" @change="resetPagination">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="15">15</option>
            <option value="20">20</option>
            <option value="30">30</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Department</th>
              <th>College</th>
              <th class="text-center">Action</th>
            </tr>
          </thead>

          <tbody v-if="loading">
            <tr>
              <td colspan="7" class="py-10 text-center text-slate-500">
                <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-[#005740] border-t-transparent align-middle"></span>
                <span class="ml-3 align-middle">Loading users...</span>
              </td>
            </tr>
          </tbody>

          <tbody v-else>
            <tr v-for="user in paginatedUsers" :key="user.id">
              <td class="font-medium text-slate-900">{{ displayName(user) }}</td>
              <td>{{ user.username || 'N/A' }}</td>
              <td>{{ user.email || 'N/A' }}</td>
              <td>
                <span :class="['role-badge', roleBadgeClass(user.role)]">
                  {{ formatRole(user.role) }}
                </span>
              </td>
              <td>{{ user.department || 'N/A' }}</td>
              <td>{{ user.college || 'N/A' }}</td>
              <td>
                <div class="flex justify-center gap-2">
                  <IconButton icon="eye" title="View User" size="sm" color="blue" @click="handleView(user)" />
                  <template v-if="canManageUsers">
                    <IconButton icon="edit" title="Edit User" size="sm" color="green" @click="handleEdit(user)" />
                    <IconButton icon="delete" title="Delete User" size="sm" color="red" @click="handleDelete(user)" />
                  </template>
                </div>
              </td>
            </tr>

            <tr v-if="filteredUsers.length === 0">
              <td colspan="7" class="py-12 text-center text-slate-500">
                {{ users.length === 0 ? 'No users found in the database.' : 'No users found matching your search.' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <footer v-if="filteredUsers.length > 0 && !loading" class="account-pagination modern-table-footer">
        <div>
          Showing {{ showingRange.start }}-{{ showingRange.end }} of {{ showingRange.total }} users
        </div>

        <div class="flex items-center gap-1">
          <button class="modern-page-button" :disabled="currentPage === 1" @click="previousPage">Previous</button>
          <template v-for="(page, index) in visiblePages" :key="`${page}-${index}`">
            <button
              v-if="page !== '...'"
              class="modern-page-button"
              :class="{ 'modern-page-button-active': currentPage === page }"
              @click="goToPage(page)"
            >
              {{ page }}
            </button>
            <span v-else class="px-2 text-sm text-slate-500">...</span>
          </template>
          <button class="modern-page-button" :disabled="currentPage === totalPages" @click="nextPage">Next</button>
        </div>

        <div>
          Page {{ currentPage }} of {{ totalPages }}
        </div>
      </footer>

      <div v-if="filteredUsers.length > 0 && !loading" class="account-total">
        Total Users in Database:
        <span>{{ users.length }}</span>
      </div>
    </section>
  </div>
</template>

<style scoped>
.account-management {
  min-width: 0;
  padding: 1rem 1.25rem 1.5rem;
}

.account-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 2.75rem;
}

.account-header h1 {
  color: #005740;
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1.2;
}

.account-header div {
  color: #64748b;
  font-size: 0.82rem;
  font-weight: 500;
  text-transform: uppercase;
}

.account-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 2.35rem;
}

.account-search {
  position: relative;
  width: min(100%, 18rem);
}

.account-search input {
  width: 100%;
  border: 1px solid #cbd5e1;
  border-radius: 999px;
  background: #eaf6f1;
  padding: 0.65rem 1rem 0.65rem 2.5rem;
  color: #0f172a;
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.account-search input:focus {
  border-color: #005740;
  box-shadow: 0 0 0 3px rgba(0, 87, 64, 0.12);
}

.add-account-button {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  border-radius: 0.35rem;
  background: #16a34a;
  padding: 0.7rem 1.25rem;
  color: #ffffff;
  font-weight: 700;
  box-shadow: 0 12px 24px rgba(22, 163, 74, 0.16);
  transition: background-color 0.2s ease, transform 0.2s ease;
}

.add-account-button:hover {
  background: #15803d;
  transform: translateY(-1px);
}

.add-account-button svg {
  height: 1.25rem;
  width: 1.25rem;
}

.account-table-card {
  min-width: 0;
  overflow: hidden;
  border: 1px solid #dbe7e1;
  border-radius: 1rem;
  background: #ffffff;
  box-shadow: 0 14px 34px rgba(15, 23, 42, 0.05);
}

table {
  min-width: 72rem;
  border-collapse: separate;
  border-spacing: 0;
  font-size: 0.875rem;
}

thead {
  background: #005740;
  color: #ffffff;
}

th {
  padding: 0.95rem 1rem;
  text-align: left;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

td {
  padding: 0.78rem 1rem;
  color: #111827;
  white-space: nowrap;
}

tbody tr:nth-child(even) {
  background: #f8fafc;
}

tbody tr:hover {
  background: #eaf6f1;
}

.role-badge {
  display: inline-flex;
  align-items: center;
  border-radius: 999px;
  padding: 0.22rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.account-pagination {
  align-items: center;
}

.account-pagination select {
  border: 1px solid #cbd5e1;
  border-radius: 0.35rem;
  background: white;
  padding: 0.35rem 0.55rem;
  color: #0f172a;
}

.page-button,
.page-number {
  border: 1px solid #cbd5e1;
  border-radius: 0.35rem;
  background: white;
  padding: 0.45rem 0.75rem;
  color: #334155;
  font-size: 0.875rem;
  transition: background-color 0.2s ease, color 0.2s ease;
}

.page-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.page-number.active {
  border-color: #005740;
  background: #005740;
  color: #ffffff;
}

.account-total {
  border-top: 1px solid #dbe7e1;
  background: #f8fafc;
  padding: 0.85rem 1.5rem 1.1rem;
  text-align: center;
  color: #64748b;
  font-size: 0.875rem;
}

.account-total span {
  color: #005740;
  font-weight: 800;
}

@media (max-width: 1100px) {
  .account-pagination {
    grid-template-columns: 1fr;
    justify-items: center;
  }
}

@media (max-width: 768px) {
  .account-management {
    padding: 0.75rem;
  }

  .account-header,
  .account-toolbar {
    align-items: stretch;
    flex-direction: column;
  }

  .account-search {
    width: 100%;
  }

  .add-account-button {
    justify-content: center;
    width: 100%;
  }
}
</style>
