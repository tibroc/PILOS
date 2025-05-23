<script setup>
defineProps({
  label: {
    type: String,
    required: true,
  },
  currentValue: {
    type: [Boolean, Number],
    required: true,
  },
  currentEnforced: {
    type: Boolean,
    required: true,
  },
  newValue: {
    type: [Boolean, Number],
    required: true,
  },
  newEnforced: {
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
  <div class="field mx-0 mb-6 grid grid-cols-12 gap-4">
    <span class="col-span-12">{{ label }}</span>

    <div
      class="col-span-3 flex items-center justify-center"
      :aria-label="
        currentEnforced
          ? $t('rooms.change_type.current_setting_enforced')
          : $t('rooms.change_type.current_setting')
      "
    >
      <Tag
        v-if="type === 'switch' && currentValue"
        class="h-6 w-6"
        rounded
        severity="primary"
        data-test="current-enabled"
      >
        <span class="fas fa-check" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.enabled") }}</span>
      </Tag>
      <Tag
        v-if="type === 'switch' && !currentValue"
        class="h-6 w-6"
        rounded
        severity="secondary"
        data-test="current-disabled"
      >
        <span class="fa-solid fa-xmark" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.disabled") }}</span>
      </Tag>

      <Tag v-if="type === 'select'" severity="info" data-test="current-info">
        {{ options[currentValue] }}
      </Tag>
    </div>

    <div
      class="col-span-2 flex items-center justify-center"
      data-test="current-enforced"
    >
      <RoomSettingEnforcedIcon v-if="currentEnforced" />
    </div>

    <div
      class="fa-solid fa-arrow-right col-span-2 flex items-center justify-center"
    />

    <div
      class="col-span-3 flex items-center justify-center"
      :aria-label="
        newEnforced
          ? $t('rooms.change_type.resulting_setting_enforced')
          : $t('rooms.change_type.resulting_setting')
      "
    >
      <Tag
        v-if="type === 'switch' && newValue"
        class="h-6 w-6"
        rounded
        severity="primary"
        data-test="new-enabled"
      >
        <span class="fas fa-check" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.enabled") }}</span>
      </Tag>
      <Tag
        v-if="type === 'switch' && !newValue"
        class="h-6 w-6"
        rounded
        severity="secondary"
        data-test="new-disabled"
      >
        <span class="fa-solid fa-xmark" aria-hidden="true"></span>
        <span class="sr-only">{{ $t("app.disabled") }}</span>
      </Tag>

      <Tag v-if="type === 'select'" severity="info" data-test="new-info">
        {{ options[newValue] }}
      </Tag>
    </div>

    <div
      class="col-span-2 flex items-center justify-center"
      data-test="new-enforced"
    >
      <RoomSettingEnforcedIcon v-if="newEnforced" />
    </div>
  </div>
</template>
