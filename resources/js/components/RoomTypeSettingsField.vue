<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [Boolean, Number],
    required: true,
  },
  enforced: {
    type: Boolean,
    required: true,
  },
  type: {
    type: String,
    required: true,
  },
  options: {
    type: Object,
    default: () => ({}),
  },
});
</script>

<template>
  <div class="field mb-4 grid grid-cols-12 gap-4">
    <span class="col-span-8 flex items-center gap-2">
      <RoomSettingEnforcedIcon v-if="enforced" />
      {{ label }}
    </span>

    <div class="col-span-4 flex items-center justify-center">
      <Tag
        v-if="type === 'switch' && value"
        class="h-6 w-6"
        rounded
        severity="primary"
        data-test="room-type-setting-enabled-icon"
      >
        <span class="fas fa-check" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.enabled") }}</span>
      </Tag>
      <Tag
        v-if="type === 'switch' && !value"
        class="h-6 w-6"
        rounded
        severity="secondary"
        data-test="room-type-setting-disabled-icon"
      >
        <span class="fa-solid fa-xmark" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.disabled") }}</span>
      </Tag>

      <Tag
        v-if="type === 'select'"
        severity="info"
        data-test="room-type-setting-info"
      >
        {{ options[value] }}
      </Tag>
    </div>
  </div>
</template>
