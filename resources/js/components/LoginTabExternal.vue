<template>
  <div data-test="login-tab-external">
    <h1 class="p-card-title">{{ props.title }}</h1>
    <Button
      class="mt-4"
      data-test="login-button"
      as="a"
      :href="redirectUrl"
      icon="fa-solid fa-arrow-up-right-from-square"
      :label="redirectLabel"
    />
  </div>
</template>

<script setup>
const props = defineProps({
  redirectLabel: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
  redirectUrl: {
    type: String,
    required: true,
  },
});
</script>
