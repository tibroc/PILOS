<template>
  <div
    class="overlay-wrap relative"
    :style="{ 'min-height': props.show ? '3rem' : null }"
  >
    <slot />
    <div
      v-if="props.show"
      data-test="overlay"
      class="inset-0 backdrop-blur-sm"
      :class="fixed ? 'fixed' : 'absolute'"
      :style="{ 'z-index': props.zIndex }"
    >
      <div
        class="absolute bg-surface-100 dark:bg-surface-900"
        style="inset: 0"
        :style="{ opacity: props.opacity }"
      />

      <div class="overlay-wrapper absolute inset-0" :class="wrapperClass">
        <slot name="overlay">
          <i class="fa-solid fa-circle-notch fa-spin text-3xl" />
        </slot>
      </div>
    </div>
  </div>
</template>
<script setup>
import { computed } from "vue";

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  zIndex: {
    type: Number,
    required: false,
    default: null,
  },
  noCenter: {
    type: Boolean,
    default: false,
  },
  opacity: {
    type: Number,
    default: 0.85,
  },
  fixed: {
    type: Boolean,
    default: false,
  },
});

const wrapperClass = computed(() => {
  return {
    "flex justify-center items-center": !props.noCenter,
  };
});
</script>
