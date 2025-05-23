<template>
  <!-- button -->
  <Button
    v-tooltip="$t('rooms.recordings.delete_recording')"
    :aria-label="$t('rooms.recordings.delete_recording')"
    :disabled="disabled"
    severity="danger"
    icon="fa-solid fa-trash"
    data-test="room-recordings-delete-button"
    @click="modalVisible = true"
  />

  <!-- modal -->
  <Dialog
    v-model:visible="modalVisible"
    modal
    :header="$t('rooms.recordings.modals.delete.title')"
    :style="{ width: '500px' }"
    :breakpoints="{ '575px': '90vw' }"
    :draggable="false"
    :close-on-escape="!isLoadingAction"
    :dismissable-mask="false"
    :closable="!isLoadingAction"
    data-test="room-recordings-delete-dialog"
  >
    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          :label="$t('app.no')"
          severity="secondary"
          :disabled="isLoadingAction"
          data-test="dialog-cancel-button"
          @click="modalVisible = false"
        />
        <Button
          :label="$t('app.yes')"
          severity="danger"
          :loading="isLoadingAction"
          :disabled="isLoadingAction"
          data-test="dialog-continue-button"
          @click="deleteRecording"
        />
      </div>
    </template>

    <span style="overflow-wrap: break-word">
      {{ $t("rooms.recordings.modals.delete.confirm") }}
    </span>
  </Dialog>
</template>
<script setup>
import env from "../env";
import { useApi } from "../composables/useApi.js";
import { ref } from "vue";
import { useToast } from "../composables/useToast.js";
import { useI18n } from "vue-i18n";

const props = defineProps({
  recordingId: {
    type: String,
    required: true,
  },
  roomId: {
    type: String,
    required: true,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["deleted", "notFound"]);

const api = useApi();
const toast = useToast();
const { t } = useI18n();

const modalVisible = ref(false);
const isLoadingAction = ref(false);

/*
 * Delete recording
 */
function deleteRecording() {
  isLoadingAction.value = true;

  api
    .call("rooms/" + props.roomId + "/recordings/" + props.recordingId, {
      method: "delete",
    })
    .then(() => {
      // operation successful, close modal and reload list
      modalVisible.value = false;
      emit("deleted");
    })
    .catch((error) => {
      // editing failed
      if (error.response) {
        // recording not found
        if (error.response.status === env.HTTP_NOT_FOUND) {
          toast.error(t("rooms.flash.recording_gone"));
          emit("notFound");
          return;
        }
      }
      api.error(error, { redirectOnUnauthenticated: false });
    })
    .finally(() => {
      isLoadingAction.value = false;
    });
}
</script>
