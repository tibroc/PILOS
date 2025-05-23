<template>
  <Button
    v-tooltip="$t('admin.server_pools.delete.item', { name: props.name })"
    :aria-label="$t('admin.server_pools.delete.item', { name: props.name })"
    :disabled="isBusy"
    icon="fa-solid fa-trash"
    severity="danger"
    data-test="server-pools-delete-button"
    @click="showModal"
  />

  <Dialog
    v-model:visible="modalVisible"
    :breakpoints="{ '575px': '90vw' }"
    :close-on-escape="!isBusy"
    :closable="!isBusy"
    :dismissable-mask="!isBusy"
    :draggable="false"
    :header="$t('admin.server_pools.delete.title')"
    :style="{ width: '500px' }"
    modal
    data-test="server-pools-delete-dialog"
    :pt="{
      pcCloseButton: {
        root: {
          'data-test': 'dialog-header-close-button',
        },
      },
    }"
  >
    <span>
      {{ $t("admin.server_pools.delete.confirm", { name: props.name }) }}
    </span>

    <div v-if="deleteFailedRoomTypes" class="mt-2">
      <Message
        severity="error"
        :pt="{
          icon: { class: 'hidden' },
        }"
      >
        {{ $t("admin.server_pools.delete.failed") }}
        <ul>
          <li v-for="roomType in deleteFailedRoomTypes" :key="roomType.id">
            {{ roomType.name }}
          </li>
        </ul>
      </Message>
    </div>

    <template v-if="deleteFailedRoomTypes == null" #footer>
      <Button
        :label="$t('app.no')"
        :disabled="isBusy"
        severity="secondary"
        data-test="dialog-cancel-button"
        @click="modalVisible = false"
      />
      <Button
        :label="$t('app.yes')"
        :loading="isBusy"
        severity="danger"
        data-test="dialog-continue-button"
        @click="deleteServerPool"
      />
    </template>
  </Dialog>
</template>
<script setup>
import { useApi } from "../composables/useApi.js";
import { ref } from "vue";
import env from "../env.js";

const api = useApi();

const props = defineProps({
  id: {
    type: Number,
    required: true,
  },
  name: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["deleted"]);
const modalVisible = ref(false);
const isBusy = ref(false);
const deleteFailedRoomTypes = ref(null);

/**
 * Shows the delete modal
 *
 */
function showModal() {
  deleteFailedRoomTypes.value = null;
  modalVisible.value = true;
}

/**
 * Deletes the server pool
 */
function deleteServerPool() {
  isBusy.value = true;

  api
    .call(`serverPools/${props.id}`, {
      method: "delete",
    })
    .then(() => {
      modalVisible.value = false;
      emit("deleted");
    })
    .catch((error) => {
      if (error.response && error.response.status === env.HTTP_STALE_MODEL) {
        deleteFailedRoomTypes.value = error.response.data.room_types;
      } else {
        api.error(error);
      }
    })
    .finally(() => {
      isBusy.value = false;
    });
}
</script>
