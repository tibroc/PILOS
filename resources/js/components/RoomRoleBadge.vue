<template>
  <Tag
    class="text-break room-role-badge shrink"
    :pt="{
      root: { class: role.class },
    }"
  >
    {{ role.description }}
  </Tag>
</template>

<script setup>
import { computed } from "vue";
import { useI18n } from "vue-i18n";

const { t } = useI18n();

const props = defineProps({
  role: {
    type: Number,
    required: true,
  },
});

const role = computed(() => {
  switch (props.role) {
    case 0:
      return {
        description: t("rooms.roles.guest"),
        class: "room-role-guest",
      };
    case 1:
      return {
        description: t("rooms.roles.participant"),
        class: "room-role-participant",
      };
    case 2:
      return {
        description: t("rooms.roles.moderator"),
        class: "room-role-moderator",
      };
    case 3:
      return {
        description: t("rooms.roles.co_owner"),
        class: "room-role-co-owner",
      };
    default:
      return {
        description: t("rooms.roles.no_role"),
        class: "room-role-no-role",
      };
  }
});
</script>
