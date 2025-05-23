<template>
  <div>
    <form class="flex flex-col gap-4" @submit="changePassword">
      <div
        v-if="isOwnUser"
        class="field grid grid-cols-12 gap-4"
        data-test="security-tab-current-password-field"
      >
        <label
          for="current_password"
          class="col-span-12 mb-2 md:col-span-3 md:mb-0"
          >{{ $t("auth.current_password") }}</label
        >
        <div class="col-span-12 md:col-span-9">
          <InputText
            id="current_password"
            v-model="currentPassword"
            autocomplete="current-password"
            type="password"
            required
            :disabled="isBusy"
            class="w-full"
            :invalid="formErrors.fieldInvalid('current_password')"
          />
          <FormError :errors="formErrors.fieldError('current_password')" />
        </div>
      </div>

      <div class="field grid grid-cols-12 gap-4" data-test="new-password-field">
        <label
          for="new_password"
          class="col-span-12 mb-2 md:col-span-3 md:mb-0"
          >{{ $t("auth.new_password") }}</label
        >
        <div class="col-span-12 md:col-span-9">
          <InputText
            id="new_password"
            v-model="newPassword"
            :autocomplete="isOwnUser ? 'new-password' : 'off'"
            type="password"
            required
            :disabled="isBusy"
            class="w-full"
            :invalid="formErrors.fieldInvalid('new_password')"
          />
          <FormError :errors="formErrors.fieldError('new_password')" />
        </div>
      </div>

      <div
        class="field grid grid-cols-12 gap-4"
        data-test="new-password-confirmation-field"
      >
        <label
          for="new_password_confirmation"
          class="col-span-12 mb-2 md:col-span-3 md:mb-0"
          >{{ $t("auth.new_password_confirmation") }}</label
        >
        <div class="col-span-12 md:col-span-9">
          <InputText
            id="new_password_confirmation"
            v-model="newPasswordConfirmation"
            :autocomplete="isOwnUser ? 'new-password' : 'off'"
            type="password"
            required
            :disabled="isBusy"
            class="w-full"
            :invalid="formErrors.fieldInvalid('new_password_confirmation')"
          />
          <FormError
            :errors="formErrors.fieldError('new_password_confirmation')"
          />
        </div>
      </div>
      <div class="flex justify-end">
        <Button
          :disabled="isBusy"
          type="submit"
          :loading="isBusy"
          :label="$t('auth.change_password')"
          icon="fa-solid fa-save"
          data-test="change-password-save-button"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import env from "../env";
import { useAuthStore } from "../stores/auth";
import { computed, ref } from "vue";
import { useApi } from "../composables/useApi.js";
import { useFormErrors } from "../composables/useFormErrors.js";
import { useToast } from "../composables/useToast.js";
import { useI18n } from "vue-i18n";

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(["updateUser", "notFoundError"]);

const api = useApi();
const formErrors = useFormErrors();
const authStore = useAuthStore();
const toast = useToast();
const { t } = useI18n();

const currentPassword = ref("");
const newPassword = ref("");
const newPasswordConfirmation = ref("");
const isBusy = ref(false);

const isOwnUser = computed(() => {
  return authStore.currentUser?.id === props.user.id;
});

function changePassword(event) {
  if (event) {
    event.preventDefault();
  }

  isBusy.value = true;
  formErrors.clear();

  const data = {
    new_password: newPassword.value,
    new_password_confirmation: newPasswordConfirmation.value,
  };

  if (isOwnUser.value) {
    data.current_password = currentPassword.value;
  }

  api
    .call("users/" + props.user.id + "/password", {
      method: "PUT",
      data,
    })
    .then((response) => {
      emit("updateUser", response.data.data);
      toast.success(t("auth.flash.password_changed"));
    })
    .catch((error) => {
      if (error.response && error.response.status === env.HTTP_NOT_FOUND) {
        emit("notFoundError", error);
      } else if (error.response.status === env.HTTP_UNPROCESSABLE_ENTITY) {
        formErrors.set(error.response.data.errors);
      } else {
        api.error(error);
      }
    })
    .finally(() => {
      isBusy.value = false;
      currentPassword.value = null;
      newPassword.value = null;
      newPasswordConfirmation.value = null;
    });
}
</script>
